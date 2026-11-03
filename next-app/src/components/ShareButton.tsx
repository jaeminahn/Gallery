"use client";
import { useState } from "react";
export function ShareButton({ title }: { title: string }) {
  const [copied, setCopied] = useState(false);
  const [busy, setBusy] = useState(false);
  return <button type="button" className="btn btn-share" disabled={busy} data-pending={busy} onClick={async () => {
    setBusy(true);
    try { const payload = { title, url: window.location.href }; if (navigator.share) await navigator.share(payload); else { await navigator.clipboard.writeText(payload.url); setCopied(true); setTimeout(() => setCopied(false), 1600); } } catch { /* cancelled */ }
    finally { setBusy(false); }
  }}>{busy ? "처리 중..." : copied ? "복사됨" : "링크 복사"}</button>;
}
