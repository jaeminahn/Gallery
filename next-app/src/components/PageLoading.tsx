export function PageLoading({ label = "페이지를 불러오는 중입니다" }: { label?: string }) {
  return <main className="page-loading" role="status" aria-label={label}>
    <span className="page-loading-spinner" aria-hidden="true"/>
  </main>;
}
