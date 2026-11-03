"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
export function LoginForm() {
  const [username,setUsername]=useState(""),[password,setPassword]=useState(""),[error,setError]=useState(""),[busy,setBusy]=useState(false); const router=useRouter();
  return <form aria-busy={busy} onSubmit={async event=>{event.preventDefault();if(busy)return;setBusy(true);setError("");try{const res=await fetch("/api/admin/login",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({username,password})});if(!res.ok)throw new Error((await res.json()).error||"로그인 실패");router.replace("/admin/");router.refresh();}catch(e){setError(e instanceof Error?e.message:"로그인에 실패했습니다.");setBusy(false);}}}>
    <h1>갤러리조명</h1><label>아이디<input type="text" value={username} onChange={e=>setUsername(e.target.value)} required autoComplete="username"/></label>
    <label>비밀번호<input type="password" value={password} onChange={e=>setPassword(e.target.value)} required autoComplete="current-password"/></label>
    {error&&<p role="alert">{error}</p>}<button className="button button-primary" type="submit" disabled={busy} data-pending={busy}>{busy?"로그인 중...":"로그인"}</button>
  </form>;
}
