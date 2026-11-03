import { NextRequest, NextResponse } from "next/server";
import { requireAdmin, serviceDb, sessionDb } from "@/lib/supabase";
import { encrypt } from "@/lib/crypto";
import { fetchListPage, fetchRemotePost, fetchRssThumbnails, MAX_PAGES, NaverError, PAGE_SIZE, sourceUrl, sanitizeContent } from "@/lib/naver";
import { rewriteWithAi } from "@/lib/rewrite";
import type { QueueItem } from "@/lib/types";
export const runtime="nodejs";
export const maxDuration=300;

function fail(error:unknown) { const message=error instanceof Error?error.message:String(error); const code=message==="UNAUTHORIZED"?401:message==="FORBIDDEN"?403:400; return NextResponse.json({error:message},{status:code}); }
function sameOrigin(req:NextRequest) { const origin=req.headers.get("origin"); if(origin && new URL(origin).host!==req.headers.get("host")) throw new Error("잘못된 요청 출처입니다."); }
async function state() { const {data,error}=await serviceDb().from("sync_state").select("*").eq("id",1).single(); if(error) throw error; return data; }
async function snapshot() {
  const db=serviceDb();
  const [s,remote,queue,posts]=await Promise.all([state(),db.from("remote_posts").select("*").order("list_page").order("list_position").limit(3000),db.from("sync_queue").select("*").order("created_at"),db.from("posts").select("id,log_no,slug,title,status,published_at").limit(3000)]);
  for(const result of [remote,queue,posts]) if(result.error) throw result.error;
  return {state:{next_page:s.next_page,complete:s.complete,paused_until:s.paused_until,model:s.model,post_status:s.post_status,has_api_key:!!s.api_key_ciphertext},remote:remote.data||[],queue:queue.data||[],posts:posts.data||[]};
}
export async function GET(_req:NextRequest,{params}:{params:Promise<{action:string}>}) {
  try { await requireAdmin(); const {action}=await params; if(action==="snapshot") return NextResponse.json(await snapshot()); throw new Error("알 수 없는 작업입니다."); } catch(error) { return fail(error); }
}
export async function POST(req:NextRequest,{params}:{params:Promise<{action:string}>}) {
  try {
    sameOrigin(req); const {action}=await params;
    if(action==="login") { const body=await req.json(); if(body.username!=="galleryadmin") throw new Error("로그인에 실패했습니다."); const db=await sessionDb(); const {data,error}=await db.auth.signInWithPassword({email:"galleryadmin@example.com",password:String(body.password||"")}); if(error||!data.user) throw new Error("로그인에 실패했습니다."); const {data:admin}=await serviceDb().from("admin_users").select("user_id").eq("user_id",data.user.id).maybeSingle(); if(!admin){await db.auth.signOut();throw new Error("관리자 권한이 없습니다.");} return NextResponse.json({ok:true}); }
    await requireAdmin(); const db=serviceDb(); const body=await req.json().catch(()=>({}));
    if(action==="logout") { await (await sessionDb()).auth.signOut(); return NextResponse.json({ok:true}); }
    if(action==="refresh") { const {error}=await db.from("remote_posts").delete().neq("log_no",""); if(error) throw error; await db.from("sync_state").update({next_page:1,complete:false,paused_until:null}).eq("id",1); return NextResponse.json(await snapshot()); }
    if(action==="list-next") {
      const s=await state(); if(s.complete) return NextResponse.json({complete:true,total:(await db.from("remote_posts").select("log_no",{count:"exact",head:true})).count||0});
      if(s.paused_until&&new Date(s.paused_until).getTime()>Date.now()) return NextResponse.json({paused:true});
      const page=Math.max(1,Math.min(MAX_PAGES,s.next_page));
      try { const result=await fetchListPage(page,s.last_request_at?new Date(s.last_request_at).getTime():0); const thumbs=page===1?await fetchRssThumbnails().catch(()=>({} as Record<string,string>)):{} as Record<string,string>; if(result.posts.length) { const {error}=await db.from("remote_posts").upsert(result.posts.map((p,index)=>({log_no:p.log_no,title:p.title,posted_at:p.date,thumbnail:thumbs[p.log_no]||p.thumbnail,list_page:page,list_position:index,updated_at:new Date().toISOString()})),{onConflict:"log_no"}); if(error) throw error; } const complete=result.posts.length<PAGE_SIZE||page>=MAX_PAGES; await db.from("sync_state").update({next_page:page+1,complete,last_request_at:new Date(result.requestedAt).toISOString()}).eq("id",1).eq("next_page",page); return NextResponse.json({page,complete,total:(await db.from("remote_posts").select("log_no",{count:"exact",head:true})).count||0}); }
      catch(error) { if(error instanceof NaverError&&error.code==="blocked") await db.from("sync_state").update({paused_until:new Date(Date.now()+30*60000).toISOString()}).eq("id",1); throw error; }
    }
    if(action==="settings") { if((await db.from("sync_queue").select("id",{count:"exact",head:true}).in("status",["pending","processing","cancelling"])).count) throw new Error("AI 작성 중에는 설정을 변경할 수 없습니다."); const models=["gpt-4o-mini","gpt-4o","gpt-4.1-mini","gpt-4.1","gpt-5-mini","gpt-5"]; const update:Record<string,unknown>={model:models.includes(body.model)?body.model:"gpt-4o-mini",post_status:body.post_status==="publish"?"publish":"draft"}; if(body.api_key==="--delete--") update.api_key_ciphertext=null; else if(typeof body.api_key==="string"&&body.api_key.trim()) update.api_key_ciphertext=encrypt(body.api_key.trim()); const {error}=await db.from("sync_state").update(update).eq("id",1); if(error) throw error; return NextResponse.json(await snapshot()); }
    if(action==="enqueue") { const ids:string[]=[...new Set<string>((Array.isArray(body.log_nos)?body.log_nos:[]).map((v:unknown)=>String(v).replace(/\D/g,"")))].filter(v=>/^\d{8,20}$/.test(v)); if(!ids.length) throw new Error("동기화할 글을 한 개 이상 선택해 주세요."); const s=await state(); if(!s.api_key_ciphertext) throw new Error("OpenAI API 키를 먼저 저장해 주세요."); if(s.paused_until&&new Date(s.paused_until).getTime()>Date.now()) throw new Error("네이버가 요청을 일시 제한한 상태입니다."); const {count:active}=await db.from("sync_queue").select("id",{count:"exact",head:true}).in("status",["pending","processing","cancelling"]); if(active) throw new Error("이미 AI 작성이 진행 중입니다."); const {error}=await db.from("sync_queue").upsert(ids.map(log_no=>({log_no,status:"pending",attempts:0,error:null,started_at:null})),{onConflict:"log_no"}); if(error) throw error; return NextResponse.json(await snapshot()); }
    if(action==="cancel") { const {data:active}=await db.from("sync_queue").select("log_no").eq("status","processing").maybeSingle(); await db.from("sync_queue").delete().eq("status","pending"); if(active) { await db.from("sync_state").update({cancel_log_no:active.log_no}).eq("id",1); await db.from("sync_queue").update({status:"cancelling"}).eq("log_no",active.log_no); } return NextResponse.json({status:active?"cancelling":"cancelled",message:active?"취소 요청을 받았습니다. 현재 AI 응답이 끝나면 글을 저장하지 않고 중단합니다.":"대기 중인 자동 작성 작업을 취소했습니다."}); }
    if(action==="retry") { const log_no=String(body.log_no||""); if(!/^\d{8,20}$/.test(log_no)) throw new Error("올바르지 않은 글 번호입니다."); if(body.remove) await db.from("sync_queue").delete().eq("log_no",log_no).eq("status","failed"); else { if(!(await state()).api_key_ciphertext) throw new Error("OpenAI API 키를 먼저 저장해 주세요."); const {count:active}=await db.from("sync_queue").select("id",{count:"exact",head:true}).in("status",["pending","processing","cancelling"]); if(active) throw new Error("이미 AI 작성이 진행 중입니다."); const {error}=await db.from("sync_queue").update({status:"pending",attempts:0,error:null,started_at:null}).eq("log_no",log_no).eq("status","failed"); if(error) throw error; } return NextResponse.json(await snapshot()); }
    if(action==="post-save") { const id=String(body.id||""); const title=String(body.title||"").trim(); if(!id||!title) throw new Error("제목을 입력해 주세요."); const status=["publish","draft","pending","private"].includes(body.status)?body.status:"draft"; const {error}=await db.from("posts").update({title,content_html:sanitizeContent(String(body.content_html||"")),excerpt:String(body.excerpt||""),status,updated_at:new Date().toISOString()}).eq("id",id); if(error) throw error; return NextResponse.json({ok:true}); }
    if(action==="post-status") { const id=String(body.id||""); if(!id) throw new Error("글을 찾지 못했습니다."); const status=["publish","draft","pending","private"].includes(body.status)?body.status:"draft"; const {error}=await db.from("posts").update({status,updated_at:new Date().toISOString()}).eq("id",id); if(error) throw error; return NextResponse.json(await snapshot()); }
    if(action==="process") return NextResponse.json(await processNext());
    throw new Error("알 수 없는 작업입니다.");
  } catch(error) { return fail(error); }
}
async function processNext() {
  const db=serviceDb(), s=await state();
  if(s.paused_until&&new Date(s.paused_until).getTime()>Date.now()) return {status:"paused"};
  const {data:claimed,error:claimError}=await db.rpc("claim_next_sync"); if(claimError) throw claimError;
  const item=claimed as QueueItem|null;
  if(!item?.id) { const {count}=await db.from("sync_queue").select("id",{count:"exact",head:true}).in("status",["processing","cancelling"]); return {status:count?"busy":"empty",remaining:count||0}; }
  const logNo=item.log_no;
  try {
    if(!s.api_key_ciphertext) throw new Error("OpenAI API 키가 설정되지 않았습니다.");
    const {post:remote,requestedAt}=await fetchRemotePost(logNo,s.last_request_at?new Date(s.last_request_at).getTime():0);
    await db.from("sync_state").update({last_request_at:new Date(requestedAt).toISOString()}).eq("id",1);
    const rewritten=await rewriteWithAi(remote,s.model,s.api_key_ciphertext);
    const latest=await state(); if(latest.cancel_log_no===logNo) { await db.from("sync_queue").delete().eq("id",item.id); await db.from("sync_state").update({cancel_log_no:null}).eq("id",1); return {status:"cancelled",log_no:logNo}; }
    const {data:existing}=await db.from("posts").select("id,slug,status").eq("log_no",logNo).maybeSingle();
    const {data:listed}=await db.from("remote_posts").select("thumbnail").eq("log_no",logNo).maybeSingle();
    const payload={title:rewritten.title,content_html:rewritten.content,excerpt:rewritten.excerpt,first_image:listed?.thumbnail||remote.firstImage||null,source_url:sourceUrl(logNo),updated_at:new Date().toISOString()};
    const {error:saveError}=existing?await db.from("posts").update(payload).eq("id",existing.id):await db.from("posts").insert({...payload,log_no:logNo,slug:logNo,status:s.post_status,published_at:remote.publishedAt});
    if(saveError) throw saveError; await db.from("sync_queue").delete().eq("id",item.id);
    const {count}=await db.from("sync_queue").select("id",{count:"exact",head:true}).eq("status","pending");
    return {status:existing?"updated":"done",log_no:logNo,remaining:count||0};
  } catch(error) {
    if(error instanceof NaverError&&["blocked","http_error"].includes(error.code)) { await db.from("sync_queue").update({status:"pending",started_at:null}).eq("id",item.id); await db.from("sync_state").update({paused_until:new Date(Date.now()+30*60000).toISOString()}).eq("id",1); return {status:"paused",message:error.message}; }
    const latest=await state(); if(latest.cancel_log_no===logNo) { await db.from("sync_queue").delete().eq("id",item.id); await db.from("sync_state").update({cancel_log_no:null}).eq("id",1); return {status:"cancelled",log_no:logNo}; }
    await db.from("sync_queue").update({status:"failed",attempts:item.attempts+1,error:error instanceof Error?error.message:String(error),started_at:null}).eq("id",item.id);
    const {count}=await db.from("sync_queue").select("id",{count:"exact",head:true}).eq("status","pending");
    return {status:"error",log_no:logNo,message:error instanceof Error?error.message:String(error),remaining:count||0};
  }
}
