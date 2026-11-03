import Link from "next/link";
import type { Post } from "@/lib/types";
export function PostCard({ post }: { post: Post }) {
  return <article className="post-card" data-reveal><Link href={`/blog/${post.slug}/`}>
    <div className="post-thumb"><img src={post.first_image || "/assets/images/barrisol-hero.png"} alt={post.title} loading="lazy" referrerPolicy="no-referrer"/></div>
    <div className="post-meta">{new Date(post.published_at).toLocaleDateString("ko-KR", { timeZone: "Asia/Seoul", year: "numeric", month: "2-digit", day: "2-digit" }).replace(/\. /g, ".").replace(/\.$/, "")}</div>
    <h3>{post.title}</h3><p>{post.excerpt}</p>
  </Link></article>;
}
