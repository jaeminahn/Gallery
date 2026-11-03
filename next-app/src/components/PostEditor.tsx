"use client";
import Link from "next/link";
import { useMemo, useRef, useState } from "react";
import type { Post } from "@/lib/types";
import { htmlToMarkdown, markdownToHtml } from "@/lib/markdown";
import { AdminShell } from "./AdminShell";

const toolbar: { label: string; before: string; after: string; placeholder: string }[] = [
  { label: "제목", before: "## ", after: "", placeholder: "제목" },
  { label: "굵게", before: "**", after: "**", placeholder: "굵은 텍스트" },
  { label: "링크", before: "[", after: "](https://)", placeholder: "링크 텍스트" },
  { label: "이미지", before: "![", after: "](이미지 URL)", placeholder: "대체 텍스트" },
  { label: "리스트", before: "\n- ", after: "", placeholder: "항목" },
  { label: "인용", before: "\n> ", after: "", placeholder: "인용문" },
];

export function PostEditor({ post }: { post: Post }) {
  const [title, setTitle] = useState(post.title);
  const [markdown, setMarkdown] = useState(() => htmlToMarkdown(post.content_html));
  const [excerpt, setExcerpt] = useState(post.excerpt);
  const [status, setStatus] = useState(post.status);
  const [savedStatus, setSavedStatus] = useState(post.status);
  const [message, setMessage] = useState("");
  const [view, setView] = useState<"edit" | "preview">("edit");
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const preview = useMemo(() => markdownToHtml(markdown), [markdown]);

  const wrapSelection = (item: (typeof toolbar)[number]) => {
    const textarea = textareaRef.current;
    if (!textarea) return;
    const start = textarea.selectionStart, end = textarea.selectionEnd;
    const selected = markdown.slice(start, end) || item.placeholder;
    const next = markdown.slice(0, start) + item.before + selected + item.after + markdown.slice(end);
    setMarkdown(next);
    requestAnimationFrame(() => {
      textarea.focus();
      const cursor = start + item.before.length + selected.length + item.after.length;
      textarea.setSelectionRange(cursor, cursor);
    });
  };

  return <AdminShell active={post.status === "publish" ? "posts" : "drafts"}>
    <div className="admin-editor-wrap">
    <div className="admin-editor-top"><Link href={post.status === "publish" ? "/admin/posts/" : "/admin/drafts/"}>← 목록으로</Link>{savedStatus === "publish" && <Link href={`/blog/${post.slug}/`} target="_blank">공개 글 보기 ↗</Link>}</div>
    <form className="admin-editor" onSubmit={async e => {
      e.preventDefault();
      const res = await fetch("/api/admin/post-save", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ id: post.id, title, content_html: markdownToHtml(markdown), excerpt, status }) });
      if (res.ok) { setSavedStatus(status); setMessage(status === "publish" ? "저장하고 공개했습니다." : "저장했습니다. 방문자에게는 보이지 않습니다."); }
      else setMessage((await res.json()).error || "저장에 실패했습니다.");
    }}>
      <h1>게시글 편집</h1>
      <label>제목<input value={title} onChange={e => setTitle(e.target.value)} required /></label>
      <label>요약<textarea value={excerpt} onChange={e => setExcerpt(e.target.value)} style={{ minHeight: 90 }} /></label>
      <div className="md-editor">
        <div className="md-editor-bar">
          <div className="md-toolbar" role="toolbar" aria-label="서식">{toolbar.map(item => <button key={item.label} type="button" className="button" onClick={() => wrapSelection(item)}>{item.label}</button>)}</div>
          <div className="md-view-tabs" role="tablist">{(["edit", "preview"] as const).map(v => <button key={v} type="button" role="tab" aria-selected={view === v} className={view === v ? "current" : ""} onClick={() => setView(v)}>{v === "edit" ? "편집" : "미리보기"}</button>)}</div>
        </div>
        {view === "edit"
          ? <textarea ref={textareaRef} className="md-source" value={markdown} onChange={e => setMarkdown(e.target.value)} aria-label="본문 마크다운" />
          : <div className="md-preview article-body" dangerouslySetInnerHTML={{ __html: preview }} />}
      </div>
      <label>게시 상태<select value={status} onChange={e => setStatus(e.target.value as Post["status"])}><option value="draft">임시글</option><option value="publish">공개</option><option value="pending">승인 대기</option><option value="private">비공개</option></select></label>
      <div className="admin-editor-actions"><span role="status">{message}</span><button className="button button-primary" type="submit">저장</button></div>
    </form>
    </div>
  </AdminShell>;
}
