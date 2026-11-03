export type Post = {
  id: string; log_no: string; slug: string; title: string; content_html: string;
  excerpt: string; first_image: string | null; source_url: string;
  status: "draft" | "publish" | "pending" | "private"; published_at: string; created_at: string; updated_at: string;
};
export type RemotePost = { log_no: string; title: string; date: string; thumbnail: string };
export type QueueItem = { id: string; log_no: string; status: "pending" | "processing" | "cancelling" | "failed"; attempts: number; error: string | null; started_at: string | null; created_at: string };
