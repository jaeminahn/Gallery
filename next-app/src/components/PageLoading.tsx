export function PageLoading({ label = "페이지를 불러오는 중입니다" }: { label?: string }) {
  return <main className="page-loading" role="status" aria-label={label}>
    <div className="page-loading-inner">
      <span className="page-loading-brand">갤러리조명</span>
      <span className="page-loading-bar" aria-hidden="true"><span/></span>
      <span className="page-loading-text">{label}</span>
    </div>
  </main>;
}
