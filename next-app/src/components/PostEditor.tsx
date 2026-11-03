"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
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
  const router = useRouter();
  const [title, setTitle] = useState(post.title);
  const [markdown, setMarkdown] = useState(() => htmlToMarkdown(post.content_html));
  const [excerpt, setExcerpt] = useState(post.excerpt);
  const [status, setStatus] = useState(post.status);
  const [savedStatus, setSavedStatus] = useState(post.status);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [view, setView] = useState<"edit" | "preview">("edit");
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const savingRef = useRef(false);
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

  return <AdminShell active={savedStatus === "publish" ? "posts" : "drafts"}>
    <div className="admin-editor-wrap">
    <div className="admin-editor-top"><Link href={savedStatus === "publish" ? "/admin/posts/" : "/admin/drafts/"}>← 목록으로</Link>{savedStatus === "publish" && <Link href={`/blog/${post.slug}/`} target="_blank">공개 글 보기 ↗</Link>}</div>
    <form className="admin-editor" aria-busy={saving||deleting} onSubmit={async e => {
      e.preventDefault();
      if (savingRef.current||deleting) return;
      savingRef.current = true;
      setSaving(true);
      try {
        const res = await fetch("/api/admin/post-save", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ id: post.id, title, content_html: markdownToHtml(markdown), excerpt, status }) });
        if (!res.ok) throw new Error((await res.json()).error || "저장에 실패했습니다.");
        setSavedStatus(status);
        window.alert(status === "publish" ? "저장하고 공개했습니다." : "저장했습니다. 방문자에게는 보이지 않습니다.");
      } catch (error) { window.alert(error instanceof Error ? error.message : "저장에 실패했습니다."); }
      finally { savingRef.current = false; setSaving(false); }
    }}>
      <header className="admin-editor-heading"><div><p className="admin-editor-eyebrow">CONTENT EDITOR</p><h1>게시글 편집</h1><p>내용을 다듬은 뒤 저장하세요. 공개 상태는 아래에서 변경할 수 있습니다.</p></div><span className="admin-editor-status">{savedStatus==="publish"?"공개 중":"비공개 상태"}</span></header>
      <label className="admin-editor-field">제목<input value={title} onChange={e => setTitle(e.target.value)} required placeholder="게시글 제목" /></label>
      <label className="admin-editor-field">요약<textarea value={excerpt} onChange={e => setExcerpt(e.target.value)} placeholder="목록과 검색 결과에 보일 간단한 소개" /></label>
      <div className="md-editor">
        <div className="md-editor-heading"><strong>본문</strong><span>마크다운으로 작성하고 미리보기로 확인하세요.</span></div>
        <div className="md-editor-bar">
          <div className="md-toolbar" role="toolbar" aria-label="서식">{toolbar.map(item => <button key={item.label} type="button" className="button" disabled={view==="preview"} onClick={() => wrapSelection(item)}>{item.label}</button>)}</div>
          <div className="md-view-tabs" role="tablist">{(["edit", "preview"] as const).map(v => <button key={v} type="button" role="tab" aria-selected={view === v} className={view === v ? "current" : ""} onClick={() => setView(v)}>{v === "edit" ? "편집" : "미리보기"}</button>)}</div>
        </div>
        {view === "edit"
          ? <textarea ref={textareaRef} className="md-source" value={markdown} onChange={e => setMarkdown(e.target.value)} aria-label="본문 마크다운" />
          : <div className="md-preview article-body" dangerouslySetInnerHTML={{ __html: preview }} />}
      </div>
      <label className="admin-editor-field admin-editor-status-field">게시 상태<select value={status} onChange={e => setStatus(e.target.value as Post["status"])}><option value="draft">임시글</option><option value="publish">공개</option><option value="pending">승인 대기</option><option value="private">비공개</option></select></label>
      <div className="admin-editor-actions"><button className="button gallery-delete-button" type="button" disabled={saving||deleting} data-pending={deleting} onClick={async()=>{if(!window.confirm(`"${title}" 글을 삭제할까요? 삭제한 글은 복구할 수 없습니다.`))return;setDeleting(true);try{const res=await fetch("/api/admin/post-delete",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:post.id})});if(!res.ok)throw new Error((await res.json()).error||"삭제에 실패했습니다.");window.alert("게시글을 삭제했습니다.");router.push(savedStatus==="publish"?"/admin/posts/":"/admin/drafts/");}catch(error){window.alert(error instanceof Error?error.message:"삭제에 실패했습니다.");setDeleting(false);}}}>{deleting?"삭제 중...":"게시글 삭제"}</button><button className="button button-primary" type="submit" disabled={saving||deleting} data-pending={saving}>{saving?"저장 중...":"변경사항 저장"}</button></div>
    </form>
    </div>
  </AdminShell>;
}
