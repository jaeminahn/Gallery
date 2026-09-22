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

// Contact section: interactive dot field that glows around the cursor.(function () {
  const canvas = document.getElementById("contact-dots");
  if (!canvas) return;

  const context = canvas.getContext("2d");
  const section = canvas.closest(".contact");
  let dots = [];
  let width = 0;
  let height = 0;
  let visible = false;
  const mouse = { x: -9999, y: -9999 };
  const GLOW_RADIUS = 190;

  function resize() {
    const rect = section.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = rect.width;
    height = rect.height;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    context.setTransform(dpr, 0, 0, dpr, 0, 0);

    dots = [];
    const gap = 42;
    for (let y = gap / 2; y < height; y += gap) {
      for (let x = gap / 2; x < width; x += gap) {
        dots.push({ x, y });
      }
    }
  }

  function draw() {
    context.clearRect(0, 0, width, height);
    for (const dot of dots) {
      const dx = dot.x - mouse.x;
      const dy = dot.y - mouse.y;
      const distance = Math.hypot(dx, dy);
      const strength = Math.max(0, 1 - distance / GLOW_RADIUS);
      const radius = 1 + strength * 2.4;
      const alpha = 0.16 + strength * 0.84;

      context.beginPath();
      context.arc(dot.x, dot.y, radius, 0, Math.PI * 2);
      context.fillStyle = `rgba(255, 255, 255, ${alpha.toFixed(3)})`;
      context.fill();
    }
  }

  function loop() {
    if (visible) draw();
    requestAnimationFrame(loop);
  }

  section.addEventListener("pointermove", (event) => {
    const rect = canvas.getBoundingClientRect();
    mouse.x = event.clientX - rect.left;
    mouse.y = event.clientY - rect.top;
  });
  section.addEventListener("pointerleave", () => {
    mouse.x = -9999;
    mouse.y = -9999;
  });

  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
  }, { rootMargin: "80px" }).observe(section);

  window.addEventListener("resize", resize);
  resize();
  loop();
})();

document.querySelectorAll(".btn-share").forEach((button) => {
  button.addEventListener("click", async () => {
    const payload = { title: button.dataset.shareTitle || document.title, url: button.dataset.shareUrl || location.href };
    try {
      if (navigator.share) {
        await navigator.share(payload);
        return;
      }
      await navigator.clipboard.writeText(payload.url);
      const original = button.textContent;
      button.textContent = "복사됨";
      setTimeout(() => { button.textContent = original; }, 1600);
    } catch (error) {
      /* user cancelled or clipboard unavailable */
    }
  });
});
