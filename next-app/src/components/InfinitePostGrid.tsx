"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { PostCard } from "@/components/PostCard";
import type { Post } from "@/lib/types";

const PAGE_SIZE = 9;

export function InfinitePostGrid({ initialPosts, initialCount, initialPage }: { initialPosts: Post[]; initialCount: number; initialPage: number }) {
  const [posts, setPosts] = useState(initialPosts);
  const [total, setTotal] = useState(initialCount);
  const [nextPage, setNextPage] = useState(initialPage + 1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const sentinel = useRef<HTMLDivElement>(null);
  const loadingRef = useRef(false);
  const hasMore = nextPage <= Math.ceil(total / PAGE_SIZE);

  const loadMore = useCallback(async () => {
    if (loadingRef.current || nextPage > Math.ceil(total / PAGE_SIZE)) return;
    loadingRef.current = true;
    setLoading(true);
    setError("");
    try {
      const res = await fetch(`/api/posts?page=${nextPage}`, { cache: "no-store" });
      if (!res.ok) throw new Error("다음 글을 불러오지 못했습니다.");
      const data = await res.json() as { posts: Post[]; count: number };
      setPosts(current => {
        const known = new Set(current.map(post => post.id));
        return [...current, ...data.posts.filter(post => !known.has(post.id))];
      });
      setTotal(data.count);
      setNextPage(nextPage + 1);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "다음 글을 불러오지 못했습니다.");
    } finally {
      loadingRef.current = false;
      setLoading(false);
    }
  }, [nextPage, total]);

  useEffect(() => {
    if (!hasMore || error || !sentinel.current || typeof IntersectionObserver === "undefined") return;
    const observer = new IntersectionObserver(entries => {
      if (entries[0]?.isIntersecting) void loadMore();
    }, { rootMargin: "500px 0px" });
    observer.observe(sentinel.current);
    return () => observer.disconnect();
  }, [hasMore, error, loadMore]);

  return <>
    <div className="post-grid">{posts.length ? posts.map(post => <PostCard post={post} key={post.id}/>) : <p>새로운 시공 기록을 준비하고 있습니다.</p>}</div>
    {hasMore && <div className="post-load-more" ref={sentinel}>
      {loading ? <p role="status">다음 시공 기록을 불러오는 중...</p> : error ? <><p role="alert">{error}</p><button type="button" className="btn" onClick={() => void loadMore()}>다시 시도</button></> : <button type="button" className="btn post-more-button" onClick={() => void loadMore()}>더 보기</button>}
    </div>}
  </>;
}
