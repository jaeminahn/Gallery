"use client";
import { usePathname } from "next/navigation";
import { useEffect } from "react";
import { animate, inView, stagger, scroll } from "motion";

export function Effects() {
  const pathname = usePathname();
  useEffect(() => {
    const header = document.querySelector("#site-header");
    const updateHeader = () => header?.classList.toggle("is-scrolled", window.scrollY > 24);
    updateHeader();
    window.addEventListener("scroll", updateHeader, { passive: true });
    const cleanups: Array<() => void> = [() => window.removeEventListener("scroll", updateHeader)];
    if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      if (document.querySelector(".hero")) {
        const a = animate(".hero [data-hero], .hero h1", { opacity: [0, 1], y: [30, 0] }, { duration: .9, delay: stagger(.1), ease: [.22, 1, .36, 1] });
        const b = animate(".hero-media img", { scale: [1.06, 1] }, { duration: 2, ease: [.22, 1, .36, 1] });
        const c = scroll(animate(".hero-media img", { y: ["0%", "10%"] }), { target: document.querySelector(".hero")!, offset: ["start start", "end start"] });
        cleanups.push(() => { a.stop(); b.stop(); c(); });
      }
      cleanups.push(inView("[data-reveal]", element => { animate(element, { opacity: [0, 1], y: [26, 0] }, { duration: .8, ease: [.22, 1, .36, 1] }); }, { margin: "0px 0px -10% 0px" }));
      cleanups.push(inView("[data-stagger]", element => { animate(Array.from(element.children), { opacity: [0, 1], y: [22, 0] }, { duration: .7, delay: stagger(.09), ease: [.22, 1, .36, 1] }); }, { margin: "0px 0px -8% 0px" }));
    }
    document.querySelectorAll<HTMLCanvasElement>("canvas.dots-canvas").forEach(canvas => {
      const context = canvas.getContext("2d");
      const section = canvas.closest("section") as HTMLElement | null;
      if (context && section) {
        let dots: Array<{x:number;y:number}> = [], width = 0, height = 0, visible = false, frame = 0;
        const mouse = { x: -9999, y: -9999 };
        const resize = () => { const rect = section.getBoundingClientRect(), dpr = Math.min(window.devicePixelRatio || 1, 2); width = rect.width; height = rect.height; canvas.width = width * dpr; canvas.height = height * dpr; context.setTransform(dpr, 0, 0, dpr, 0, 0); dots = []; for(let y=21;y<height;y+=42) for(let x=21;x<width;x+=42) dots.push({x,y}); };
        const move = (event: PointerEvent) => { const rect = canvas.getBoundingClientRect(); mouse.x = event.clientX - rect.left; mouse.y = event.clientY - rect.top; };
        const leave = () => { mouse.x = -9999; mouse.y = -9999; };
        const observer = new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; }, { rootMargin: "80px" });
        observer.observe(section); section.addEventListener("pointermove", move); section.addEventListener("pointerleave", leave); window.addEventListener("resize", resize); resize();
        const loop = () => { if (visible) { context.clearRect(0,0,width,height); for(const dot of dots) { const strength = Math.max(0,1-Math.hypot(dot.x-mouse.x,dot.y-mouse.y)/190); context.beginPath(); context.arc(dot.x,dot.y,1+strength*2.4,0,Math.PI*2); context.fillStyle=`rgba(255, 255, 255, ${(0.16+strength*0.84).toFixed(3)})`; context.fill(); } } frame=requestAnimationFrame(loop); }; loop();
        cleanups.push(() => { cancelAnimationFrame(frame); observer.disconnect(); section.removeEventListener("pointermove",move); section.removeEventListener("pointerleave",leave); window.removeEventListener("resize",resize); });
      }
    });
    return () => cleanups.forEach(fn => fn());
  }, [pathname]);
  return null;
}
