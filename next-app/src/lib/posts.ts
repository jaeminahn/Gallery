import { serviceDb } from "./supabase";
import type { Post } from "./types";
export async function getPublishedPosts(page = 1, limit = 9): Promise<{ posts: Post[]; count: number }> {
  if (!process.env.NEXT_PUBLIC_SUPABASE_URL || !process.env.SUPABASE_SECRET_KEY) return { posts: [], count: 0 };
  const from = Math.max(0, page - 1) * limit;
  const { data, count, error } = await serviceDb().from("posts").select("*", { count: "exact" }).eq("status", "publish").order("published_at", { ascending: false }).order("id", { ascending: false }).range(from, from + limit - 1);
  if (error) throw error;
  return { posts: data as Post[], count: count ?? 0 };
}
export async function getPublishedPost(slug: string): Promise<Post | null> {
  if (!process.env.NEXT_PUBLIC_SUPABASE_URL || !process.env.SUPABASE_SECRET_KEY) return null;
  const { data, error } = await serviceDb().from("posts").select("*").eq("slug", slug).eq("status", "publish").maybeSingle();
  if (error) throw error;
  return data as Post | null;
}

export async function recordPostView(postId: string): Promise<void> {
  if (!process.env.NEXT_PUBLIC_SUPABASE_URL || !process.env.SUPABASE_SECRET_KEY) return;
  try {
    const db = serviceDb();
    const today = new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Seoul" });
    const { data: existing } = await db.from("post_view_stats").select("views").eq("post_id", postId).eq("view_date", today).maybeSingle();
    if (existing) await db.from("post_view_stats").update({ views: (existing as { views: number }).views + 1 }).eq("post_id", postId).eq("view_date", today);
    else await db.from("post_view_stats").insert({ post_id: postId, view_date: today, views: 1 });
  } catch { /* 통계 저장 실패가 페이지를 막지 않도록 무시 */ }
}

export async function getAllPublishedPosts(): Promise<Pick<Post, "slug" | "updated_at" | "published_at">[]> {
  if (!process.env.NEXT_PUBLIC_SUPABASE_URL || !process.env.SUPABASE_SECRET_KEY) return [];
  const { data, error } = await serviceDb().from("posts").select("slug,updated_at,published_at").eq("status", "publish").order("published_at", { ascending: false });
  if (error) throw error;
  return data as Pick<Post, "slug" | "updated_at" | "published_at">[];
}
