import { storeNaverImage } from "@/lib/post-images";

export const runtime = "nodejs";

export async function GET(request: Request) {
  const url = new URL(request.url).searchParams.get("url") || "";
  try {
    const stored = await storeNaverImage(url);
    return Response.redirect(stored, 307);
  } catch {
    return new Response("이미지를 불러오지 못했습니다.", { status: 404, headers: { "Cache-Control": "no-store" } });
  }
}
