import { redirect } from "next/navigation";
import { requireAdmin } from "@/lib/supabase";
import { LoginForm } from "@/components/LoginForm";
export default async function LoginPage() {
  const authenticated = await requireAdmin().then(() => true).catch(() => false);
  if (authenticated) redirect("/admin/");
  return <main className="admin-login"><LoginForm/></main>;
}
