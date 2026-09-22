import { animate, inView, stagger, scroll } from "https://cdn.jsdelivr.net/npm/motion@12.23.24/+esm";

const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const header = document.querySelector("#site-header");
const updateHeader = () => header?.classList.toggle("is-scrolled", window.scrollY > 24);
updateHeader();
window.addEventListener("scroll", updateHeader, { passive: true });

if (!reduceMotion) {
  animate(".hero .eyebrow, .hero h1, .hero-lead, .hero-actions", { opacity: [0, 1], y: [34, 0] }, { duration: .85, delay: stagger(.09), ease: [.22, 1, .36, 1] });
  animate(".hero-media img", { scale: [1.09, 1.03] }, { duration: 1.8, ease: [.22, 1, .36, 1] });

  inView("[data-reveal]", (element) => {
    animate(element, { opacity: [0, 1], y: [28, 0] }, { duration: .75, ease: [.22, 1, .36, 1] });
  }, { margin: "0px 0px -10% 0px" });

  inView("[data-stagger]", (element) => {
    animate(Array.from(element.children), { opacity: [0, 1], y: [24, 0] }, { duration: .65, delay: stagger(.08), ease: [.22, 1, .36, 1] });
  }, { margin: "0px 0px -8% 0px" });

  const glow = document.querySelector(".service-glow");
  if (glow) scroll(animate(glow, { y: [-40, 120], scale: [.9, 1.2] }), { target: glow.closest(".service-card"), offset: ["start end", "end start"] });
}

document.querySelectorAll(".faq-item button").forEach((button) => {
  button.addEventListener("click", () => {
    const item = button.closest(".faq-item");
    const answer = item.querySelector(".faq-answer");
    const open = item.classList.toggle("is-open");
    button.setAttribute("aria-expanded", String(open));
    answer.style.maxHeight = open ? `${answer.scrollHeight}px` : "0px";
  });
});
