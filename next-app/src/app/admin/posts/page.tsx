import { redirect } from "next/navigation";
import { requireAdmin } from "@/lib/supabase";
import { PostManager } from "@/components/PostManager";
export const dynamic = "force-dynamic";
export const metadata = { title: "공개 게시글 — 갤러리조명 관리자" };
export default async function AdminPostsPage() {
  try { await requireAdmin(); } catch { redirect("/admin/login/"); }
  return <PostManager filter="publish" />;
}
