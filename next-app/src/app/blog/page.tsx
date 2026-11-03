import Link from "next/link";
import { SiteShell } from "@/components/SiteShell";
import { PostCard } from "@/components/PostCard";
import { getPublishedPosts } from "@/lib/posts";
export const dynamic = "force-dynamic";
export const metadata = { title: "시공 기록" };
export default async function BlogPage({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  const page = Math.max(1, Number((await searchParams).page) || 1);
  const { posts, count } = await getPublishedPosts(page);
  const pages = Math.ceil(count / 9);
  return <SiteShell><main className="blog-main">
    <section className="blog-hero"><div className="wrap"><h1>현장에서 기록한<br/>조명 이야기</h1><p className="section-copy">지역과 공간, 작업 내용을 구체적으로 담습니다. 비슷한 현장의 조명 선택에 도움이 되길 바랍니다.</p></div></section>
    <section className="section"><div className="wrap"><div className="post-grid">
      {posts.length ? posts.map(post => <PostCard post={post} key={post.id}/>) : <p>새로운 시공 기록을 준비하고 있습니다.</p>}
    </div>{pages > 1 && <nav className="gallery-sync-pagination" aria-label="글 목록 페이지">
      {Array.from({ length: pages }, (_, i) => <Link key={i} className={`page-numbers ${page === i + 1 ? "current" : ""}`} href={`/blog/?page=${i+1}`}>{i+1}</Link>)}
    </nav>}</div></section>
  </main></SiteShell>;
}
