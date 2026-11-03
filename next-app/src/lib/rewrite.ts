import { load } from "cheerio";
import { SYSTEM_PROMPT } from "./prompt";
import { sanitizeContent, type NaverContent } from "./naver";
import { serviceDb } from "./supabase";
import { decrypt } from "./crypto";

export async function rewriteWithAi(remote:NaverContent, model:string, encryptedKey:string|null) {
  const key=encryptedKey ? decrypt(encryptedKey) : "";
  if(!key) throw new Error("OpenAI API 키가 설정되지 않았습니다.");
  const images:string[]=[];
  const marked=remote.content.replace(/<img\b[^>]*>/gi, match=>{ const token=`<!--gallery-sync-img-${images.length}-->`; images.push(match); return token; });
  const { data: samples }=await serviceDb().from("posts").select("title,content_html").eq("status","publish").order("published_at",{ascending:false}).limit(2);
  const reference=(samples||[]).map(p=>`제목: ${p.title}\n${load(p.content_html).text().replace(/\s+/g," ").slice(0,400)}`).join("\n\n");
  const user=`[원문 제목]\n${remote.title}\n\n[원문 본문]\n${marked}${reference ? `\n\n[문체 참고용 최근 글 예시. 문장이나 내용을 현재 글에 섞어 넣지 말고 문장만 참고할 것]\n${reference}` : ""}`;
  const response=await fetch("https://api.openai.com/v1/chat/completions",{ method:"POST",signal:AbortSignal.timeout(180000),headers:{Authorization:`Bearer ${key}`,"Content-Type":"application/json"},body:JSON.stringify({model,response_format:{type:"json_object"},messages:[{role:"system",content:SYSTEM_PROMPT},{role:"user",content:user}]}) });
  const body=await response.json();
  if(!response.ok) {
    if(body.error?.code==="insufficient_quota"||/no credits remaining/i.test(body.error?.message||"")) throw new Error("OpenAI API 크레딧이 없습니다. 크레딧을 충전한 뒤 재시도해 주세요.");
    throw new Error(`OpenAI 재작성 실패: ${body.error?.message||`HTTP ${response.status}`}`);
  }
  let result:{title?:string;content?:string};
  try { result=JSON.parse(body.choices?.[0]?.message?.content||""); } catch { throw new Error("OpenAI 응답을 해석하지 못했습니다."); }
  if(!result.title||!result.content) throw new Error("OpenAI 응답에 제목이나 본문이 없습니다.");
  let content=result.content.replace(/[–—]/g,"-").replace(/·/g,",");
  images.forEach((img,i)=>{content=content.replace(new RegExp(`<!--\\s*gallery-sync-img-${i}\\s*-->`,"i"),img);});
  images.forEach(img=>{if(!content.includes(img)) content+=`\n${img}`;});
  content=sanitizeContent(content);
  const title=result.title.replace(/[–—]/g,"-").replace(/·/g,",").replace(/\s+/g," ").trim();
  const excerpt=load(content).text().replace(/\s+/g," ").trim().split(" ").slice(0,32).join(" ")+"...";
  return {title,content,excerpt};
}
