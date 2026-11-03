"use client";
import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { AdminShell, type AdminSection } from "./AdminShell";

type Stored = { id: string; log_no: string; slug: string; title: string; status: string; published_at: string };
const PAGE_SIZE = 20;
const statusLabel: Record<string, string> = { draft: "임시글", publish: "공개", pending: "승인 대기", private: "비공개" };

async function api(action: string, body: unknown = {}) {
  const res = await fetch(`/api/admin/${action}`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || "요청에 실패했습니다.");
  return data;
}

export function PostManager({ filter }: { filter: "publish" | "draft" }) {
  const [all, setAll] = useState<Stored[] | null>(null);
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const isLive = filter === "publish";

  const load = useCallback(async () => {
    const res = await fetch("/api/admin/snapshot", { cache: "no-store" });
    if (!res.ok) throw new Error((await res.json()).error || "목록을 읽지 못했습니다.");
    const data = await res.json();
    setAll(data.posts);
  }, []);
  useEffect(() => { void Promise.resolve().then(load).catch(e => setError(e instanceof Error ? e.message : String(e))); }, [load]);

  const items = useMemo(() => (all || []).filter(p => isLive ? p.status === "publish" : p.status !== "publish"), [all, isLive]);
  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return q ? items.filter(p => p.title.toLowerCase().includes(q) || p.log_no.includes(q)) : items;
  }, [items, query]);
  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const current = Math.min(page, pages);
  const visible = filtered.slice((current - 1) * PAGE_SIZE, current * PAGE_SIZE);

  const changeStatus = async (post: Stored, status: string, success: string) => {
    if (!confirm(status === "publish" ? `"${post.title}"을(를) 공개할까요? 방문자에게 바로 보입니다.` : `"${post.title}"을(를) 임시글로 내릴까요? 방문자에게 보이지 않습니다.`)) return;
    try { setError(""); await api("post-status", { id: post.id, status }); setMessage(success); await load(); }
    catch (e) { setError(e instanceof Error ? e.message : String(e)); }
  };

  return <AdminShell active={isLive ? "posts" : "drafts" satisfies AdminSection}>
    <div className="gallery-sync-admin">
      <div className="gallery-sync-notices">{message && <div className="gallery-sync-alert gallery-sync-alert-success" role="status"><p>{message}</p></div>}{error && <div className="gallery-sync-alert gallery-sync-alert-error" role="alert"><p>{error}</p></div>}</div>
      <div className="gallery-sync-hero"><div><p className="gallery-sync-kicker">{isLive ? "Published" : "Drafts"}</p><h1>{isLive ? "공개 게시글" : "임시글"}</h1><p>{isLive ? "방문자에게 보이는 글 목록입니다. 내용을 수정하거나 임시글로 내릴 수 있습니다." : "아직 방문자에게 보이지 않는 글입니다. 확인 후 공개로 변경하세요."}</p></div><Link className="button" href="/admin/">← 동기화 화면</Link></div>
      <div className="gallery-post-toolbar">
        <input type="search" className="gallery-post-search" placeholder="제목 또는 원문 번호로 검색" value={query} onChange={e => { setQuery(e.target.value); setPage(1); }} aria-label="글 검색" />
        <span className="gallery-post-count">총 {filtered.length}개{query && ` (전체 ${items.length}개 중)`}</span>
      </div>
      {visible.length ? <div className="gallery-sync-section"><div className="gallery-sync-list">{visible.map(p => <article className="gallery-sync-row gallery-post-row" key={p.id}>
        <span className={`gallery-sync-status gallery-sync-status-${p.status === "publish" ? "done" : "pending"}`}>{statusLabel[p.status] || p.status}</span>
        <div className="gallery-sync-info"><h3>{p.title}</h3><div className="gallery-sync-meta"><span>{new Date(p.published_at).toLocaleDateString("ko-KR", { timeZone: "Asia/Seoul" })}</span><span>원문 번호 {p.log_no}</span></div></div>
        <div className="gallery-sync-actions"><Link href={`/admin/posts/${p.id}/`}>편집</Link>
          {p.status === "publish" ? <><button className="button" onClick={() => changeStatus(p, "draft", "임시글로 변경했습니다.")}>임시글로 변경</button><Link href={`/blog/${p.slug}/`} target="_blank">보기</Link></> : <button className="button button-primary" onClick={() => changeStatus(p, "publish", "공개했습니다. 사이트에 바로 표시됩니다.")}>공개로 변경</button>}
        </div>
      </article>)}</div></div>
        : <div className="gallery-empty">{all === null ? "목록을 불러오는 중입니다." : query ? "검색 결과가 없습니다." : isLive ? "공개 중인 글이 없습니다. 임시글에서 공개로 변경해 보세요." : "임시글이 없습니다. 동기화하면 임시글로 저장됩니다."}</div>}
      {pages > 1 && <nav className="gallery-sync-pagination" aria-label="목록 페이지">{Array.from({ length: pages }, (_, i) => <button type="button" key={i} className={current === i + 1 ? "current" : ""} onClick={() => setPage(i + 1)}>{i + 1}</button>)}</nav>}
    </div>
  </AdminShell>;
}
