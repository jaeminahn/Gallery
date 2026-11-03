import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = {
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000"),
  title: { default: "울산 갤러리조명 | 바리솔 · LED 조명 전문 시공", template: "%s | 갤러리조명" },
  description: "울산 갤러리조명은 바리솔 조명, LED 엣지등, 해외 수입 식탁등, 병원과 은행 및 기관 조명을 전문 시공합니다. 부산 울산 경남 출장 상담 010-4588-8709",
  openGraph: { locale: "ko_KR", type: "website", siteName: "갤러리조명", images: ["/assets/images/barrisol-hero.png"] },
};
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="ko" data-scroll-behavior="smooth"><body>{children}</body></html>;
}
