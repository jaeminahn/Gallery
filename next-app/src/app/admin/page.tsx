import { redirect } from "next/navigation";
import { requireAdmin } from "@/lib/supabase";
import { AdminDashboard } from "@/components/AdminDashboard";
export const dynamic="force-dynamic";
export default async function AdminPage() {
  try { await requireAdmin(); } catch { redirect("/admin/login/"); }
  return <AdminDashboard/>;
}
