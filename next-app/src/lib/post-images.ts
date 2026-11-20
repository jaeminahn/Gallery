import { createHash } from "node:crypto";
import { load } from "cheerio";
import { serviceDb } from "./supabase";

const BUCKET = "post-images";
const NAVER_HOSTS = new Set(["postfiles.pstatic.net", "blogfiles.pstatic.net", "storep-phinf.pstatic.net", "blogthumb.pstatic.net"]);

export function isNaverImage(input: string): boolean {
  try {
    const url = new URL(input);
    return url.protocol === "https:" && NAVER_HOSTS.has(url.hostname) && !url.username && !url.password;
  } catch { return false; }
}

export function publicImageUrl(input: string): string {
  return isNaverImage(input) ? `/api/post-image?url=${encodeURIComponent(input)}` : input;
}

export function publicPostImages<T extends { content_html: string; first_image: string | null }>(post: T): T {
  const $ = load(post.content_html, null, false);
  $("img[src]").each((_, image) => {
    const src = $(image).attr("src");
    if (src) $(image).attr("src", publicImageUrl(src));
  });
  return { ...post, content_html: $.html(), first_image: post.first_image ? publicImageUrl(post.first_image) : null };
}

export async function storeNaverImage(input: string): Promise<string> {
  if (!isNaverImage(input)) throw new Error("허용되지 않은 이미지 주소입니다.");
  const db = serviceDb();
  const path = createHash("sha256").update(input).digest("hex");
  const storage = db.storage.from(BUCKET);
  const { data: existing } = await storage.info(path);
  if (existing) return storage.getPublicUrl(path).data.publicUrl;

  const response = await fetch(input, {
    signal: AbortSignal.timeout(25000),
    headers: { Referer: "https://blog.naver.com/", "User-Agent": "Mozilla/5.0", Accept: "image/avif,image/webp,image/png,image/jpeg,image/gif,image/*" },
    cache: "no-store",
    redirect: "error",
  });
  if (!response.ok) throw new Error(`네이버 사진을 가져오지 못했습니다(HTTP ${response.status}).`);
  const contentType = response.headers.get("content-type")?.split(";")[0].trim().toLowerCase() || "";
  if (!/^image\/(jpeg|png|gif|webp|avif)$/.test(contentType)) throw new Error("이미지 파일이 아닌 응답입니다.");
  if (Number(response.headers.get("content-length") || 0) > 15 * 1024 * 1024) throw new Error("이미지 크기가 허용 범위를 벗어났습니다.");
  const bytes = await response.arrayBuffer();
  if (!bytes.byteLength || bytes.byteLength > 15 * 1024 * 1024) throw new Error("이미지 크기가 허용 범위를 벗어났습니다.");
  const { error } = await storage.upload(path, bytes, { contentType, cacheControl: "31536000", upsert: false });
  if (error && !/already exists|duplicate/i.test(error.message)) throw error;
  return storage.getPublicUrl(path).data.publicUrl;
}

export async function persistPostImages(contentHtml: string, firstImage: string | null) {
  const $ = load(contentHtml, null, false);
  const sources = new Set<string>();
  $("img[src]").each((_, image) => { const src = $(image).attr("src"); if (src && isNaverImage(src)) sources.add(src); });
  if (firstImage && isNaverImage(firstImage)) sources.add(firstImage);
  const replacements = new Map<string, string>();
  for (const source of sources) replacements.set(source, await storeNaverImage(source));
  $("img[src]").each((_, image) => { const src = $(image).attr("src"); if (src && replacements.has(src)) $(image).attr("src", replacements.get(src)); });
  return { contentHtml: $.html(), firstImage: firstImage ? replacements.get(firstImage) || firstImage : null };
}
