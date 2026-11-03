import type { MetadataRoute } from "next";
import { getAllPublishedPosts } from "@/lib/posts";
import { siteUrl } from "@/lib/site";

export const dynamic = "force-dynamic";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const base = siteUrl();
  const posts = await getAllPublishedPosts().catch(() => []);
  return [
    { url: `${base}/`, changeFrequency: "weekly", priority: 1 },
    { url: `${base}/blog/`, changeFrequency: "daily", priority: 0.8 },
    ...posts.map(post => ({
      url: `${base}/blog/${post.slug}/`,
      lastModified: post.updated_at || post.published_at,
      changeFrequency: "weekly" as const,
      priority: 0.7,
    })),
  ];
}
