import Link from "next/link";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { SiteShell } from "@/components/SiteShell";
import { ShareButton } from "@/components/ShareButton";
import { getPublishedPost, recordPostView } from "@/lib/posts";
import { serviceDb } from "@/lib/supabase";
import { siteUrl } from "@/lib/site";
import type { Post } from "@/lib/types";
export const dynamic = "force-dynamic";

function stripHtml(html: string) { return html.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim(); }

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const post = await getPublishedPost((await params).slug);
  if (!post) return {};
  const url = `${siteUrl()}/blog/${post.slug}/`;
  const description = post.excerpt || stripHtml(post.content_html).slice(0, 155);
  return {
    title: post.title,
    description,
    alternates: { canonical: url },
    openGraph: { type: "article", url, title: post.title, description, images: post.first_image ? [post.first_image] : [`${siteUrl()}/assets/images/barrisol-hero.png`], publishedTime: post.published_at, modifiedTime: post.updated_at },
    twitter: { card: "summary_large_image", title: post.title, description, images: post.first_image ? [post.first_image] : [`${siteUrl()}/assets/images/barrisol-hero.png`] },
  };
}

export default async function PostPage({ params }: { params: Promise<{ slug: string }> }) {
  const post = await getPublishedPost((await params).slug);
  if (!post) notFound();
  void recordPostView(post.id);
  const { data } = await serviceDb().from("posts").select("id,slug,title,published_at").eq("status", "publish").order("published_at", { ascending: true });
  const all = (data || []) as Pick<Post, "id" | "slug" | "title" | "published_at">[];
  const index = all.findIndex(item => item.id === post.id), previous = all[index - 1], next = all[index + 1];
  const url = `${siteUrl()}/blog/${post.slug}/`;
  const description = post.excerpt || stripHtml(post.content_html).slice(0, 155);
  const jsonLd = {
    "@context": "https://schema.org", "@type": "Article",
    headline: post.title, description, image: post.first_image ? [post.first_image] : [`${siteUrl()}/assets/images/barrisol-hero.png`],
    datePublished: post.published_at, dateModified: post.updated_at,
    mainEntityOfPage: url, inLanguage: "ko-KR",
    author: { "@type": "Organization", name: "갤러리조명", url: siteUrl() },
    publisher: { "@type": "Organization", name: "갤러리조명", url: siteUrl() },
  };
  return <SiteShell><main className="single-post"><article className="article"><div className="wrap">
    <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }}/>
    <nav className="article-nav" aria-label="글 탐색"><Link className="btn-link" href="/blog/"><span aria-hidden="true">&larr;</span> 시공 기록 목록</Link><span className="article-nav-label">갤러리조명 시공 기록</span></nav>
    <header className="article-header"><div className="article-meta">{new Date(post.published_at).toLocaleDateString("ko-KR", { timeZone: "Asia/Seoul" })}</div><h1>{post.title}</h1></header>
    <div className="article-body" dangerouslySetInnerHTML={{ __html: post.content_html }}/>
    <footer className="article-footer"><div className="article-share"><span>이 글이 도움이 되었다면<br/>주변에 알려주세요.</span><ShareButton title={post.title}/></div>
      <nav className="article-pager" aria-label="이전/다음 글"><div className="article-pager-item"><span className="article-pager-label">이전 글</span>{previous ? <Link href={`/blog/${previous.slug}/`}>{previous.title}</Link> : <span className="article-pager-empty">가장 오래된 글입니다</span>}</div><div className="article-pager-item article-pager-next"><span className="article-pager-label">다음 글</span>{next ? <Link href={`/blog/${next.slug}/`}>{next.title}</Link> : <span className="article-pager-empty">가장 최근 글입니다</span>}</div></nav>
      <div className="article-cta"><h2>비슷한 고민이 있으신가요?</h2><p>사진과 지역을 문자로 보내주시면<br/>빠르게 상담해 드립니다.</p><a className="btn btn-light" href="tel:01045888709">010-4588-8709 전화하기</a></div>
    </footer>
  </div></article></main></SiteShell>;
}
