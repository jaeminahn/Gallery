import Link from "next/link";
import { SiteShell } from "@/components/SiteShell";
import { PostCard } from "@/components/PostCard";
import { Faq } from "@/components/Faq";
import { getPublishedPosts } from "@/lib/posts";
export const dynamic = "force-dynamic";

export default async function HomePage() {
  const { posts } = await getPublishedPosts(1, 3);
  return <SiteShell>

<main>
  <section className="hero" aria-labelledby="hero-title">
    <div className="hero-media"><img src="/assets/images/barrisol-hero.png" alt="바리솔 조명이 설치된 고급 상업 공간"/></div>
    <div className="wrap hero-content">
      <h1 id="hero-title">빛이 머무는<br/>공간을 짓습니다</h1>
      <p className="hero-lead" data-hero>울산 갤러리조명은 바리솔 제작과 설치, 가정용 LED 엣지등, 해외 수입 식탁등을 전문으로 시공합니다.</p>
      <div className="hero-actions" data-hero>
        <a className="btn btn-light" href="tel:01045888709">010-4588-8709 상담하기</a>
        <a className="btn-link" href="#services">시공 분야 보기 <span aria-hidden="true">&rarr;</span></a>
      </div>
    </div>
  </section>

  <section className="section" id="services">
    <div className="wrap">
      <div className="section-head" data-reveal>
        <h2 className="section-title">좋은 조명은 밝기보다<br/>공간을 먼저 봅니다</h2>
        <p className="section-copy">같은 평수라도 천장 높이와 마감, 생활 방식에 따라 필요한 빛은 다릅니다. 현장을 살피고 오래 편안한 조명을 제안합니다.</p>
      </div>
      <div className="service-grid" data-stagger>
        <article className="service-card">
          <span className="service-no">01</span>
          <div className="service-image"><img src="/assets/images/service-barrisol.png" alt="상업 공간에 시공된 맞춤형 바리솔 천장 조명" loading="lazy"/></div>
          <h3>바리솔 제작과 시공</h3>
          <p>천장 크기와 형태에 맞춘 주문 제작, 균일한 면조명, 디밍과 색온도 조절, 기존 바리솔 LED 수리까지 진행합니다.</p>
        </article>
        <article className="service-card">
          <span className="service-no">02</span>
          <div className="service-image"><img src="/assets/images/service-edge-light.png" alt="아파트 거실 천장에 설치된 슬림 LED 엣지등" loading="lazy"/></div>
          <h3>슬림 LED 엣지등</h3>
          <p>거실, 방, 주방, 욕실의 기존 조명을 얇고 환한 엣지등으로 교체합니다. 배선과 컨버터 상태도 함께 확인합니다.</p>
        </article>
        <article className="service-card">
          <span className="service-no">03</span>
          <div className="service-image"><img src="/assets/images/service-louis-poulsen.png" alt="식탁 위에 설치된 루이스폴센 PH 5 펜던트 조명 예시" loading="lazy"/></div>
          <h3>해외 수입 식탁등</h3>
          <p>루이스폴센을 비롯한 수입 펜던트와 직구 조명의 조립, 천장 보강, 높이 조절, 전선 수리와 설치를 섬세하게 마무리합니다.</p>
        </article>
      </div>
    </div>
  </section>

  <section className="section barrisol" id="barrisol">
    <div className="wrap barrisol-layout">
      <div className="barrisol-visual" data-reveal>
        <img src="/assets/images/barrisol-hero.png" alt="곡선형 바리솔 천장 조명 디테일"/>
      </div>
      <div data-reveal>
        <h2 className="section-title">바리솔은<br/>경험이 결과를 바꿉니다</h2>
        <ul className="feature-list">
          <li><span><strong>균일한 발광</strong>LED 배열과 이격 거리를 현장에 맞춰 설계합니다.</span></li>
          <li><span><strong>맞춤 형태</strong>직선과 곡선, 대형 면조명까지 공간에 맞게 제작합니다.</span></li>
          <li><span><strong>수리 가능성</strong>향후 램프와 컨버터를 점검하기 쉬운 구조를 고려합니다.</span></li>
        </ul>
      </div>
    </div>
  </section>

  <section className="section">
    <div className="wrap">
      <div className="section-head" data-reveal>
        <h2 className="section-title">문의부터 점등까지<br/>꼼꼼하게 이어갑니다</h2>
      </div>
      <div className="process-grid" data-stagger>
        <article className="process-card"><span>01</span><h3>전화 상담</h3><p>지역과 공간, 교체할 조명, 불편한 점을 편하게 말씀해 주세요.</p></article>
        <article className="process-card"><span>02</span><h3>현장 확인</h3><p>천장 구조와 배선, 설치 치수, 필요한 밝기를 확인합니다.</p></article>
        <article className="process-card"><span>03</span><h3>제품 제안</h3><p>공간과 예산에 맞는 조명과 시공 방법을 이해하기 쉽게 안내합니다.</p></article>
        <article className="process-card"><span>04</span><h3>책임 시공</h3><p>안전하게 설치하고 점등 상태와 마감까지 확인한 뒤 마무리합니다.</p></article>
      </div>
    </div>
  </section>

  <section className="section area" id="area">
    <div className="wrap">
      <div className="section-head" data-reveal>
        <h2 className="section-title">울산에서 시작해<br/>필요한 현장까지</h2>
        <p className="section-copy">울산 전 지역을 가장 빠르게 방문하며 부산과 경남도 꾸준히 시공합니다. 대형 바리솔과 특수 조명은 타 지역 출장도 협의할 수 있습니다.</p>
      </div>
      <div className="area-list" data-stagger>
        <div className="area-item"><strong>울산</strong><p>남구, 중구, 동구, 북구, 울주군의 아파트, 주택, 병원, 상가와 기관</p></div>
        <div className="area-item"><strong>부산, 경남 등 인접 지역</strong><p>현장 규모와 일정에 맞춰 방문합니다. 인접 지역과 대형 바리솔 프로젝트는 전화로 먼저 문의해 주세요.</p></div>
      </div>
    </div>
  </section>

  {posts.length > 0 && <section className="section" id="journal"><div className="wrap">
    <div className="section-head journal-head" data-reveal><div><h2 className="section-title">현장에서 쌓은<br/>조명 이야기</h2></div><Link className="btn-link" href="/blog/">시공 기록 전체 보기 <span aria-hidden="true">&rarr;</span></Link></div>
    <div className="post-grid" data-stagger>{posts.map(post => <PostCard key={post.id} post={post}/>)}</div>
  </div></section>}

  <section className="section" id="faq">
    <div className="wrap">
      <div className="section-head" data-reveal>
        <h2 className="section-title">자주 묻는 질문에<br/>먼저 답해드립니다</h2>
      </div>
      <div className="faq-list">
        <Faq />
      </div>
    </div>
  </section>

  <section className="section contact" id="contact">
    <canvas className="contact-dots" id="contact-dots" aria-hidden="true"/>
    <div className="wrap contact-layout" data-reveal>
      <div>
        <h2 className="section-title">어떤 공간인지 알려주세요.<br/>빛의 답을 함께 찾겠습니다.</h2>
      <p className="section-copy">사진과 지역, 교체하고 싶은 조명을 문자로 보내주시면 상담이 더 빠릅니다.</p>
      <div className="contact-actions">
        <a className="btn btn-light" href="tel:01045888709">010-4588-8709 전화하기</a>
        <a className="btn-link btn-link-light" href="https://blog.naver.com/luxurylusso" target="_blank" rel="noopener noreferrer">네이버 블로그 <span aria-hidden="true">&rarr;</span></a>
        </div>
      </div>
      <div className="contact-spacer" aria-hidden="true"></div>
    </div>
  </section>
</main>


  </SiteShell>;
}
