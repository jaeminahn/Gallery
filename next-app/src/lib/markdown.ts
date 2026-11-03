import { marked } from "marked";
import TurndownService from "turndown";
import sanitizeHtml from "sanitize-html";

export function htmlToMarkdown(html: string): string {
  const service = new TurndownService({ headingStyle: "atx", codeBlockStyle: "fenced", bulletListMarker: "-", emDelimiter: "*" });
  service.keep(["figure", "figcaption"]);
  service.addRule("image", {
    filter: "img",
    replacement: (_content, node) => {
      const element = node as unknown as { getAttribute(name: string): string | null };
      const src = element.getAttribute("src") || "";
      if (!src) return "";
      const alt = element.getAttribute("alt") || "";
      return `![${alt}](${src})`;
    },
  });
  return service.turndown(html).replace(/\n{3,}/g, "\n\n").trim();
}

export function markdownToHtml(markdown: string): string {
  const raw = marked.parse(markdown, { async: false, breaks: false, gfm: true }) as string;
  return sanitizeHtml(raw, {
    allowedTags: [...sanitizeHtml.defaults.allowedTags, "img", "figure", "figcaption", "div", "span"],
    allowedAttributes: {
      a: ["href", "title", "target", "rel", "class"],
      img: ["src", "alt", "title", "width", "height", "loading", "decoding", "referrerpolicy"],
      div: ["class", "style"], span: ["class", "style"], p: ["class", "style"],
      figure: ["class", "style"], figcaption: ["class"],
    },
    allowedSchemes: ["http", "https", "mailto", "tel"],
  });
}
