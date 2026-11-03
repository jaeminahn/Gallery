import { SiteShell } from "@/components/SiteShell";
import { InfinitePostGrid } from "@/components/InfinitePostGrid";
import { getPublishedPosts } from "@/lib/posts";
export const dynamic = "force-dynamic";
export const metadata = {
  title: "시공 기록",
  description: "울산 갤러리조명의 현장 시공 기록입니다. 지역과 공간, 작업 내용을 구체적으로 담아 비슷한 현장의 조명 선택에 도움을 드립니다.",
  alternates: { canonical: "/blog/" },
};
export default async function BlogPage({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  const page = Math.max(1, Number((await searchParams).page) || 1);
  const { posts, count } = await getPublishedPosts(page);
  return <SiteShell><main className="blog-main">
    <section className="blog-hero"><canvas className="blog-hero-dots dots-canvas" aria-hidden="true"/><div className="wrap"><h1>현장에서 기록한<br/>조명 이야기</h1><p className="section-copy">지역과 공간, 작업 내용을 구체적으로 담습니다.<br/>비슷한 현장의 조명 선택에 도움이 되길 바랍니다.</p></div></section>
    <section className="section"><div className="wrap"><InfinitePostGrid initialPosts={posts} initialCount={count} initialPage={page}/></div></section>
  </main></SiteShell>;
}
