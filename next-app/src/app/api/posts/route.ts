import { getPublishedPosts } from "@/lib/posts";

export const dynamic = "force-dynamic";

export async function GET(request: Request) {
  const raw = Number(new URL(request.url).searchParams.get("page"));
  const page = Number.isSafeInteger(raw) && raw > 0 ? Math.min(raw, 10000) : 1;
  try {
    const result = await getPublishedPosts(page, 9);
    return Response.json(result, { headers: { "Cache-Control": "no-store" } });
  } catch {
    return Response.json({ error: "게시글을 불러오지 못했습니다." }, { status: 500 });
  }
}
