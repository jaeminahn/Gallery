import { animate, inView, stagger, scroll } from "https://cdn.jsdelivr.net/npm/motion@12.23.24/+esm";

const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const header = document.querySelector("#site-header");
const updateHeader = () => header?.classList.toggle("is-scrolled", window.scrollY > 24);
updateHeader();
window.addEventListener("scroll", updateHeader, { passive: true });

if (!reduceMotion) {
  animate(".hero [data-hero], .hero h1", { opacity: [0, 1], y: [30, 0] }, { duration: .9, delay: stagger(.1), ease: [.22, 1, .36, 1] });
  animate(".hero-media img", { scale: [1.06, 1] }, { duration: 2, ease: [.22, 1, .36, 1] });

  scroll(animate(".hero-media img", { y: ["0%", "10%"] }), { target: document.querySelector(".hero"), offset: ["start start", "end start"] });

  inView("[data-reveal]", (element) => {
    animate(element, { opacity: [0, 1], y: [26, 0] }, { duration: .8, ease: [.22, 1, .36, 1] });
  }, { margin: "0px 0px -10% 0px" });

  inView("[data-stagger]", (element) => {
    animate(Array.from(element.children), { opacity: [0, 1], y: [22, 0] }, { duration: .7, delay: stagger(.09), ease: [.22, 1, .36, 1] });
  }, { margin: "0px 0px -8% 0px" });
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
