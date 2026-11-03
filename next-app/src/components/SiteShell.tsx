import Link from "next/link";
import { Effects } from "./Effects";

export function SiteShell({ children }: { children: React.ReactNode }) {
  return <>
    <header className="site-header" id="site-header"><div className="wrap nav">
      <Link className="brand" href="/" aria-label="갤러리조명 홈"><span>갤러리조명</span></Link>
      <nav className="nav-links" aria-label="주 메뉴">
        <Link href="/#services">전문 시공</Link><Link href="/#barrisol">바리솔</Link>
        <Link href="/#area">출장 지역</Link><Link href="/blog/">시공 기록</Link>
        <a className="nav-call" href="tel:01045888709">전화 상담</a>
      </nav>
    </div></header>
    {children}
    <footer className="site-footer"><div className="wrap">
      <div className="footer-grid">
        <div><Link className="brand" href="/"><span>갤러리조명</span></Link><p>빛으로 공간의 인상을 완성합니다.<br/>바리솔과 LED 조명의 경험을 현장에 맞게 제안합니다.</p></div>
        <div><p className="footer-title">상담</p><p><a href="tel:01045888709">010-4588-8709</a><br/><a href="tel:0522570644">052-257-0644</a><br/>luxurylusso@naver.com</p></div>
        <div><p className="footer-title">사업자 정보</p><p>태성이엔티, 대표 안성환<br/>울산 남구 갈밭로10번길 9<br/>사업자번호 610-08-87756</p></div>
      </div>
      <div className="footer-bottom">© {new Date().getFullYear()} 갤러리조명. 현장과 공간에 맞는 정직한 시공을 약속드립니다.</div>
    </div></footer>
    <a className="mobile-call" href="tel:01045888709">지금 전화 상담 010-4588-8709</a>
    <Effects />
  </>;
}
