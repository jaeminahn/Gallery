import { serviceDb } from "./supabase";
import type { Post } from "./types";
export async function getPublishedPosts(page = 1, limit = 9): Promise<{ posts: Post[]; count: number }> {
  if (!process.env.NEXT_PUBLIC_SUPABASE_URL || !process.env.SUPABASE_SECRET_KEY) return { posts: [], count: 0 };
  const from = Math.max(0, page - 1) * limit;
  const { data, count, error } = await serviceDb().from("posts").select("*", { count: "exact" }).eq("status", "publish").order("published_at", { ascending: false }).range(from, from + limit - 1);
  if (error) throw error;
  return { posts: data as Post[], count: count ?? 0 };
}
export async function getPublishedPost(slug: string): Promise<Post | null> {
  if (!process.env.NEXT_PUBLIC_SUPABASE_URL || !process.env.SUPABASE_SECRET_KEY) return null;
  const { data, error } = await serviceDb().from("posts").select("*").eq("slug", slug).eq("status", "publish").maybeSingle();
  if (error) throw error;
  return data as Post | null;
}
