"use client";
import Link from "next/link";
import { useState } from "react";

export type AdminSection = "sync" | "posts" | "drafts";

const nav: { key: AdminSection; label: string; href: string; hint: string }[] = [
  { key: "sync", label: "글 동기화", href: "/admin/", hint: "네이버에서 글 가져오기" },
  { key: "posts", label: "공개 게시글", href: "/admin/posts/", hint: "방문자에게 보이는 글" },
  { key: "drafts", label: "임시글", href: "/admin/drafts/", hint: "공개 전 보관되는 글" },
];

export function AdminShell({ active, children }: { active: AdminSection; children: React.ReactNode }) {
  const [loggingOut,setLoggingOut]=useState(false);
  return <div className="admin-shell">
    <aside className="admin-sidebar">
      <strong>갤러리조명</strong>
      <nav className="admin-nav" aria-label="관리자 메뉴">
        {nav.map(item => <Link key={item.key} href={item.href} className={active === item.key ? "active" : ""} aria-current={active === item.key ? "page" : undefined}>
          <span>{item.label}</span><small>{item.hint}</small>
        </Link>)}
        <Link href="/" className="admin-nav-site"><span>사이트 보기</span><small>공개 화면으로 이동</small></Link>
      </nav>
    </aside>
    <main className="admin-main">
      <div className="admin-top"><span>갤러리조명 관리자</span><button className="admin-logout" disabled={loggingOut} data-pending={loggingOut} onClick={async () => { setLoggingOut(true);try{const res=await fetch("/api/admin/logout", { method: "POST", headers: { "Content-Type": "application/json" }, body: "{}" });if(!res.ok)throw new Error("로그아웃에 실패했습니다.");location.href = "/admin/login/";}catch(e){window.alert(e instanceof Error?e.message:"로그아웃에 실패했습니다.");setLoggingOut(false);}}}><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M15 8l4 4-4 4M19 12H9"/></svg><span>{loggingOut?"로그아웃 중...":"로그아웃"}</span></button></div>
      {children}
    </main>
  </div>;
}
