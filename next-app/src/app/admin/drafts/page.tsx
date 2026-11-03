import { redirect } from "next/navigation";
import { requireAdmin } from "@/lib/supabase";
import { PostManager } from "@/components/PostManager";
export const dynamic = "force-dynamic";
export const metadata = { title: "임시글 — 갤러리조명 관리자" };
export default async function AdminDraftsPage() {
  try { await requireAdmin(); } catch { redirect("/admin/login/"); }
  return <PostManager filter="draft" />;
}
