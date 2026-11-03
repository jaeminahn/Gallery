"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
export function LoginForm() {
  const [username,setUsername]=useState(""),[password,setPassword]=useState(""),[error,setError]=useState(""); const router=useRouter();
  return <form onSubmit={async event=>{event.preventDefault();setError("");const res=await fetch("/api/admin/login",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({username,password})});if(!res.ok){setError((await res.json()).error||"로그인 실패");return;}router.replace("/admin/");router.refresh();}}>
    <h1>갤러리조명</h1><label>아이디<input type="text" value={username} onChange={e=>setUsername(e.target.value)} required autoComplete="username"/></label>
    <label>비밀번호<input type="password" value={password} onChange={e=>setPassword(e.target.value)} required autoComplete="current-password"/></label>
    {error&&<p role="alert">{error}</p>}<button className="button button-primary" type="submit">로그인</button>
  </form>;
}
