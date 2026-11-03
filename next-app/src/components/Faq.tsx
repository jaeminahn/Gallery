"use client";
import { useState } from "react";
const faqs = [
  ["바리솔 조명은 어떤 공간에 적합한가요?", "그림자 없이 부드럽고 균일한 빛이 필요한 병원, 피부과, 은행, 로비, 사무실과 주거 공간에 적합합니다. 공간 형태에 맞춰 크기와 모양을 제작할 수 있어 천장 디자인을 깔끔하게 완성할 수 있습니다."],
  ["울산 외 지역도 출장 시공이 가능한가요?", "네. 울산을 중심으로 부산과 경남 지역을 주로 시공하며, 현장 규모와 일정에 따라 포항을 포함한 타 지역 출장도 상담할 수 있습니다."],
  ["기존 바리솔 조명의 LED만 교체할 수 있나요?", "가능합니다. 밝기가 떨어졌거나 일부가 점등되지 않는 경우 현장 상태를 확인한 뒤 LED 모듈, 컨버터, 배선 문제를 진단하고 필요한 부분만 수리합니다."],
  ["가정용 LED 엣지등도 설치하나요?", "네. 아파트와 주택의 거실등, 방등, 주방등, 욕실등을 슬림 엣지등과 LED 조명으로 교체하며 기존 배선 상태도 함께 확인합니다."],
  ["바리솔과 LED 엣지등은 어떻게 다른가요?", "바리솔은 천장 형태에 맞춰 넓은 면을 고르게 밝히는 조명입니다. LED 엣지등은 거실이나 방의 기존 조명을 슬림한 등기구로 교체할 때 적합합니다. 공간과 천장 상태를 보고 선택합니다."],
  ["해외 수입 식탁등 설치도 가능한가요?", "가능합니다. 해외 수입 펜던트와 직구 조명의 조립, 높이 조절, 천장 보강, 전선 수리와 설치를 상담합니다."],
  ["조명 시공을 문의할 때 무엇을 알려주면 되나요?", "시공 지역, 설치 공간, 교체할 조명과 현재 상태를 알려주세요. 공간과 조명 사진을 문자로 보내주시면 상담과 현장 확인에 도움이 됩니다."],
];
export function Faq() {
  const [open, setOpen] = useState<number | null>(null);
  return <>{faqs.map(([question, answer], index) => <div className={`faq-item ${open === index ? "is-open" : ""}`} key={question}>
    <button type="button" aria-expanded={open === index} onClick={() => setOpen(open === index ? null : index)}>{question}</button>
    <div className="faq-answer" style={{ maxHeight: open === index ? 300 : 0 }}><p>{answer}</p></div>
  </div>)}</>;
}
export { faqs };
