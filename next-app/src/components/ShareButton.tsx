"use client";
import { useState } from "react";
export function ShareButton({ title }: { title: string }) {
  const [copied, setCopied] = useState(false);
  return <button type="button" className="btn btn-share" onClick={async () => {
    try { const payload = { title, url: window.location.href }; if (navigator.share) await navigator.share(payload); else { await navigator.clipboard.writeText(payload.url); setCopied(true); setTimeout(() => setCopied(false), 1600); } } catch { /* cancelled */ }
  }}>{copied ? "복사됨" : "링크 복사"}</button>;
}
