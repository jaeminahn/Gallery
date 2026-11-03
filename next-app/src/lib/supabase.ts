import { createClient } from "@supabase/supabase-js";
import { createServerClient } from "@supabase/ssr";
import { cookies } from "next/headers";

function config() {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  const publishableKey = process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY;
  if (!url || !publishableKey) throw new Error("Supabase 환경 변수가 설정되지 않았습니다.");
  return { url, publishableKey };
}
export function serviceDb() {
  const { url } = config();
  const secret = process.env.SUPABASE_SECRET_KEY;
  if (!secret) throw new Error("SUPABASE_SECRET_KEY가 설정되지 않았습니다.");
  return createClient(url, secret, { auth: { persistSession: false, autoRefreshToken: false } });
}
export async function sessionDb() {
  const { url, publishableKey } = config();
  const cookieStore = await cookies();
  return createServerClient(url, publishableKey, {
    cookies: {
      getAll() { return cookieStore.getAll(); },
      setAll(values) { try { values.forEach(({ name, value, options }) => cookieStore.set(name, value, options)); } catch { /* Server Components cannot set cookies. */ } },
    },
  });
}
export async function requireAdmin() {
  const db = await sessionDb();
  const { data: { user }, error } = await db.auth.getUser();
  if (error || !user) throw new Error("UNAUTHORIZED");
  const { data: admin, error: adminError } = await serviceDb().from("admin_users").select("user_id").eq("user_id", user.id).maybeSingle();
  if (adminError || !admin) throw new Error("FORBIDDEN");
  return user;
}
