import { redirect, notFound } from "next/navigation";
import { requireAdmin, serviceDb } from "@/lib/supabase";
import { PostEditor } from "@/components/PostEditor";
import type { Post } from "@/lib/types";
export const dynamic="force-dynamic";
export default async function EditPost({params}:{params:Promise<{id:string}>}) {
  try { await requireAdmin(); } catch { redirect("/admin/login/"); }
  const {data}=await serviceDb().from("posts").select("*").eq("id",(await params).id).maybeSingle();
  if(!data) notFound();
  return <PostEditor post={data as Post}/>;
}
