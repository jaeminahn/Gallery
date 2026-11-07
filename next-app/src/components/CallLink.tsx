"use client";

import Image from "next/image";
import { useId, useRef } from "react";

const PHONE = "010-4588-8709";

export function CallLink({ className, children }: { className?: string; children: React.ReactNode }) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const id = useId();

  return <>
    <a className={className} href="tel:01045888709" onClick={event => {
      if (window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
        event.preventDefault();
        dialogRef.current?.showModal();
      }
    }}>{children}</a>
    <dialog ref={dialogRef} className="call-dialog" aria-labelledby={`${id}-title`} aria-describedby={`${id}-description`} onClick={event => {
      if (event.target !== event.currentTarget) return;
      const bounds = event.currentTarget.getBoundingClientRect();
      if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
        event.currentTarget.close();
      }
    }}>
      <button type="button" className="call-close" aria-label="닫기" onClick={() => dialogRef.current?.close()}>×</button>
      <h2 className="call-title" id={`${id}-title`}>전화 상담</h2>
      <p className="call-description" id={`${id}-description`}>휴대폰 카메라로 QR 코드를 스캔하면 전화번호가 열립니다.</p>
      <Image className="call-qr" src="/assets/images/phone-qr.svg" alt={`${PHONE} 전화 연결 QR 코드`} width={280} height={280} />
      <a className="call-number" href="tel:01045888709">{PHONE}</a>
    </dialog>
  </>;
}
