import { load } from "cheerio";
import sanitizeHtml from "sanitize-html";
import type { RemotePost } from "./types";

const BLOG_ID = "luxurylusso", CATEGORY_NO = "1";
export const PAGE_SIZE = 30, MAX_PAGES = 100;
export class NaverError extends Error { code:string; constructor(code: string, message: string) { super(message); this.code=code; } }
export function sourceUrl(logNo: string) { return `https://blog.naver.com/PostView.naver?blogId=${BLOG_ID}&logNo=${encodeURIComponent(logNo)}&categoryNo=${CATEGORY_NO}`; }
const clean = (value: string) => value.replace(/\s+/g," ").trim();
function decodeTitle(value:string) { try { return decodeURIComponent(value.replace(/\+/g," ")); } catch { return value; } }
export function normalizeImageUrl(input: string) {
  try { const url=new URL(input.replace(/&amp;/g,"&")); if(url.protocol!=="https:" || !/^(postfiles|blogfiles|storep-phinf|blogthumb)\.pstatic\.net$/.test(url.hostname)) return ""; if(url.hostname === "blogthumb.pstatic.net") url.searchParams.set("type","w966"); else url.searchParams.delete("type"); return url.toString(); } catch { return ""; }
}
export async function naverRequest(url: string, interval = 2500, lastRequest = 0): Promise<{ html: string; requestedAt: number }> {
  const delay=Math.max(0,interval-(Date.now()-lastRequest)); if(delay) await new Promise(resolve=>setTimeout(resolve,delay));
  const requestedAt=Date.now();
  let response: Response;
  try { response=await fetch(url,{ cache:"no-store", signal:AbortSignal.timeout(25000), headers:{ "User-Agent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36", Referer:`https://blog.naver.com/${BLOG_ID}`, "Accept-Language":"ko-KR,ko;q=0.9" }}); }
  catch(error) { throw new NaverError("network_error",`네이버 연결 실패: ${String(error)}`); }
  if(response.status===403||response.status===429) throw new NaverError("blocked",`네이버가 요청을 제한했습니다(HTTP ${response.status}). 약 30분 후에 다시 시도해 주세요.`);
  if(!response.ok) throw new NaverError("http_error",`네이버가 요청을 처리하지 못했습니다(HTTP ${response.status}).`);
  return { html:await response.text(), requestedAt };
}
export async function fetchListPage(page: number, lastRequest = 0): Promise<{ posts: RemotePost[]; requestedAt: number }> {
  const url=`https://blog.naver.com/PostTitleListAsync.naver?blogId=${BLOG_ID}&categoryNo=${CATEGORY_NO}&currentPage=${page}&countPerPage=${PAGE_SIZE}`;
  const { html, requestedAt }=await naverRequest(url,350,lastRequest);
  return { posts:parseListHtml(html,page), requestedAt };
}
export function parseListHtml(html:string,page:number):RemotePost[] {
  const match=html.match(/"postList":(\[[\s\S]*?\]),"countPerPage"/);
  if(!match) throw new NaverError("list_parse_error",`네이버 목록 ${page}페이지를 해석하지 못했습니다.`);
  let data: unknown;
  try { data=JSON.parse(match[1]); } catch { throw new NaverError("list_parse_error",`네이버 목록 ${page}페이지를 해석하지 못했습니다.`); }
  if(!Array.isArray(data)) throw new NaverError("list_parse_error","네이버 글 목록 형식이 올바르지 않습니다.");
  const posts=data.map((entry):RemotePost|null=>{ const item=entry as Record<string,unknown>; const log_no=String(item.logNo||"").replace(/\D/g,""); const title=clean(decodeTitle(String(item.title||""))); return log_no&&title ? { log_no,title:title.slice(0,180),date:clean(String(item.addDate||"")),thumbnail:"" } : null; }).filter((item):item is RemotePost=>!!item);
  return posts;
}
export async function fetchRssThumbnails():Promise<Record<string,string>> {
  const {html}=await naverRequest(`https://rss.blog.naver.com/${BLOG_ID}.xml`,350);
  const $=load(html,{xmlMode:true}), map:Record<string,string>={};
  $("item").each((_,item)=>{ const link=$(item).find("link").first().text(); const logNo=link.match(/\/(\d{8,20})(?:[?/]|$)/)?.[1]; const description=$(item).find("description").first().text(); const match=description.match(/https?:\/\/[^"'\s]+?pstatic\.net\/[^"'\s]+?\.(?:jpe?g|png|gif)[^"'\s]*/i); if(logNo&&match){const url=normalizeImageUrl(match[0]);if(url)map[logNo]=url;} });
  return map;
}
export type NaverContent = { title:string; content:string; excerpt:string; firstImage:string; publishedAt:string };
export async function fetchRemotePost(logNo: string, lastRequest = 0): Promise<{ post:NaverContent; requestedAt:number }> {
  if(!/^\d{8,20}$/.test(logNo)) throw new NaverError("invalid_log_no","올바르지 않은 네이버 글 번호입니다.");
  const { html,requestedAt }=await naverRequest(sourceUrl(logNo),2500,lastRequest);
  return { post:parsePostHtml(html,logNo),requestedAt };
}
export function parsePostHtml(html:string,logNo:string):NaverContent {
  const $=load(html); const main=$(".se-main-container").first();
  if(!main.length) throw new NaverError("content_missing","네이버 글 본문을 찾지 못했습니다.");
  const title=clean(($('meta[property="og:title"]').attr("content")||$(".se-title-text").first().text()||`네이버 블로그 글 ${logNo}`).replace(/\s*:\s*네이버 블로그\s*$/u,""));
  main.find("script,style,button").remove(); let firstImage="";
  main.find("img").each((_,element)=>{ const image=$(element), parent=image.parent(); let src=""; try { src=JSON.parse(parent.attr("data-linkdata")||"{}").src||""; } catch {} src=normalizeImageUrl(src||image.attr("data-lazy-src")||image.attr("src")||""); const width=Number(image.attr("data-width")||0),height=Number(image.attr("data-height")||0); if(!src||(width>0&&height>0&&Math.max(width,height)<220)){image.remove();return;} if(!firstImage&&src.includes("postfiles.pstatic.net")) firstImage=src; image.attr({src,loading:"lazy",decoding:"async",referrerpolicy:"no-referrer"}); for(const attr of ["id","data-lazy-src","data-width","data-height","class","style"]) image.removeAttr(attr); });
  main.find("*").each((_,el)=>{ const node=$(el); for(const attr of ["onclick","data-linkdata","data-linktype","area-hidden","data-module-v2"]) node.removeAttr(attr); });
  const content=sanitizeContent(`<div class="naver-post-content">${main.html()||""}</div>`);
  const excerpt=clean($("<div>").html(content).text()).split(" ").slice(0,32).join(" ")+"...";
  const date=clean($(".se_publishDate").first().text()); const match=date.match(/(\d{4})\.\s*(\d{1,2})\.\s*(\d{1,2})(?:\.\s*(\d{1,2}):(\d{2}))?/);
  const publishedAt=match ? new Date(`${match[1]}-${match[2].padStart(2,"0")}-${match[3].padStart(2,"0")}T${(match[4]||"09").padStart(2,"0")}:${match[5]||"00"}:00+09:00`).toISOString() : new Date().toISOString();
  return {title,content,excerpt,firstImage,publishedAt};
}
export function sanitizeContent(html:string) { return sanitizeHtml(html,{ allowedTags:[...sanitizeHtml.defaults.allowedTags,"img","figure","figcaption","div","span"], allowedAttributes:{ a:["href","title","target","rel","class"], img:["src","alt","title","width","height","loading","decoding","referrerpolicy"], div:["class","style"], span:["class","style"], p:["class","style"], figure:["class","style"], figcaption:["class"] }, allowedSchemes:["http","https","mailto","tel"] }); }
