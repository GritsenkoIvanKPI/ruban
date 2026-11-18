// One-time / re-runnable generator: extracts the current I18N text object,
// gallery array, and block-image map out of index.html and writes:
//   - content.json               (live editable content, read by the site + admin at runtime)
//   - admin/field-meta.json      (section grouping + friendly labels, used by the admin UI only)
//
// Run with: node scripts/build-content-seed.mjs
// Safe to re-run: it will NOT overwrite content.json if it already exists
// (so it never clobbers live edits made through the admin panel) unless --force is passed.

import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const htmlPath = path.join(root, "index.html");
const html = fs.readFileSync(htmlPath, "utf8");

// --- Extract the I18N object literal and evaluate it in an isolated context ---
const start = html.indexOf("const I18N = {");
const end = html.indexOf("\nconst SUPPORTED_LANGS");
if (start === -1 || end === -1) throw new Error("Could not locate I18N block in index.html");
const i18nSrc = html.slice(start, end).replace("const I18N = ", "var __I18N = ");
const sandbox = {};
vm.createContext(sandbox);
vm.runInContext(i18nSrc, sandbox);
const I18N = sandbox.__I18N;

// --- Extract the gallery data array the same way ---
const gStart = html.indexOf("const galleryImages = [");
const gEnd = html.indexOf("\n  ];", gStart) + "\n  ];".length;
const gSrc = html.slice(gStart, gEnd).replace("const galleryImages = ", "var __GALLERY = ");
vm.runInContext(gSrc, sandbox);
const galleryImages = sandbox.__GALLERY;

// --- Block-level images/videos (key -> current file), matched by their existing
//     unique data-i18n-alt / data-i18n-aria-label attributes ---
const images = {
  footer_logo_alt: "Ruban/web/logo-128.webp",
  hero_video_aria: "Ruban/web/hero-loop-poster.webp",
  about_img1_alt: "Ruban/web/IMG_7743.webp",
  about_img2_alt: "Ruban/web/IMG_7734.webp",
  route_spree_img_alt: "Ruban/web/IMG_7770.webp",
  route_treptower_img_alt: "Ruban/web/IMG_7813.webp",
  route_warschauer_img_alt: "Ruban/web/IMG_7768.webp",
  route_oberbaum_img_alt: "Ruban/web/IMG_6697.webp",
  route_kanaele_img_alt: "Ruban/web/IMG_7599.webp",
  route_mueggelsee_img_alt: "Ruban/web/IMG_7880.webp",
  sicherheit_img_alt: "Ruban/web/IMG_7814.webp",
  preise_img1_alt: "Ruban/web/IMG_7127.webp",
  preise_img2_alt: "Ruban/web/IMG_7885.webp",
  katamaran_img_alt: "Ruban/web/IMG_7942.webp",
  bewertungen_video_aria: "Ruban/web/boat-loop-poster.webp",
  faq_img_alt: "Ruban/web/IMG_7919.webp",
};

const content = {
  text: I18N,
  images,
  gallery: galleryImages,
};

const contentPath = path.join(root, "content.json");
const force = process.argv.includes("--force");
if (fs.existsSync(contentPath) && !force) {
  console.log("content.json already exists — leaving it untouched (pass --force to regenerate).");
} else {
  fs.writeFileSync(contentPath, JSON.stringify(content, null, 2) + "\n");
  console.log("Wrote", contentPath);
}

// --- Section grouping + friendly (German) labels for the admin UI ---
const SECTIONS = [
  { id: "nav", label: "Navigation", match: (k) => /^(skip_link|nav_|lang_aria|btn_jetzt_buchen|menu_open_aria|menu_close_aria)/.test(k) },
  { id: "hero", label: "Startbereich (Hero)", match: (k) => k.startsWith("hero_") },
  { id: "about", label: "Über uns", match: (k) => k.startsWith("about_") },
  { id: "routes", label: "Routen", match: (k) => k.startsWith("routes_") || k.startsWith("route_") },
  { id: "ausstattung", label: "Ausstattung", match: (k) => k.startsWith("ausstattung_") || k.startsWith("a_") },
  { id: "sicherheit", label: "Sicherheit", match: (k) => k.startsWith("sicherheit_") },
  { id: "preise", label: "Preise", match: (k) => k.startsWith("preise_") || k.startsWith("badge_") || k === "unit_per_hour" || k === "btn_whatsapp" },
  { id: "katamaran", label: "Katamaran", match: (k) => k.startsWith("katamaran_") },
  { id: "galerie", label: "Galerie", match: (k) => k.startsWith("galerie_") || k.startsWith("filter_") || k.startsWith("gallery_") || k.startsWith("lightbox_") },
  { id: "buchung", label: "Buchung", match: (k) => k.startsWith("buchung_") || k.startsWith("booking_consent_") },
  { id: "cookies", label: "Cookie-Banner", match: (k) => k.startsWith("cookie_") || k === "footer_cookie_settings" },
  { id: "standort", label: "Standort", match: (k) => k.startsWith("standort_") || k.startsWith("map_") },
  { id: "bewertungen", label: "Bewertungen", match: (k) => k.startsWith("bewertungen_") || k.startsWith("video_") },
  { id: "faq", label: "FAQ", match: (k) => k.startsWith("faq_") },
  { id: "feedback", label: "Kontaktformular", match: (k) => k.startsWith("feedback_") || k.startsWith("form_") },
  { id: "kontakt", label: "Kontaktdaten", match: (k) => k.startsWith("kontakt_") },
  { id: "footer", label: "Footer", match: (k) => k.startsWith("footer_") },
  { id: "legal", label: "Rechtliche Texte", match: (k) => k.startsWith("dlg_") },
  { id: "seo", label: "SEO (Titel & Beschreibung)", match: (k) => k.startsWith("meta_") },
];

const SUFFIX_LABELS = [
  [/_img_alt$/, "Bildbeschreibung (Alt-Text)"],
  [/_aria$/, "Barrierefreiheits-Label"],
  [/_html$/, "Text (mit HTML)"],
  [/_body$/, "Text"],
  [/_title$/, "Titel"],
  [/_subtitle$/, "Untertitel"],
  [/_eyebrow$/, "Kicker"],
  [/_btn$/, "Button-Text"],
  [/_label$/, "Label"],
  [/_days$/, "Tage"],
  [/_value$/, "Wert"],
  [/_author$/, "Autor"],
  [/_status$/, "Statustext"],
  [/_address$/, "Adresse"],
  [/_prefix$/, "Präfix"],
  [/_price$/, "Preis (nur Zahl)"],
];

function humanize(key) {
  let base = key;
  let type = "";
  for (const [re, label] of SUFFIX_LABELS) {
    if (re.test(key)) {
      type = label;
      base = key.replace(re, "");
      break;
    }
  }
  const words = base.replace(/^(nav|route|routes|a|dlg)_/, "").split("_").filter(Boolean);
  const readable = words.map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(" ");
  return type ? `${readable} — ${type}` : readable;
}

const isHtmlField = (k) => k.endsWith("_html");
const isLongField = (k) => k.endsWith("_body") || k.endsWith("_html") || /^faq_a\d/.test(k) || k === "standort_body";

const fields = Object.keys(I18N.de).map((key) => {
  const section = SECTIONS.find((s) => s.match(key))?.id || "misc";
  return {
    key,
    section,
    label: humanize(key),
    multiline: isLongField(key),
    html: isHtmlField(key),
  };
});

const meta = {
  sections: SECTIONS.map(({ id, label }) => ({ id, label })),
  fields,
  images: Object.keys(images).map((key) => ({
    key,
    label: humanize(key.replace(/_(img_alt|aria)$/, "")) || key,
  })),
};

const adminDir = path.join(root, "admin");
fs.mkdirSync(adminDir, { recursive: true });
fs.writeFileSync(path.join(adminDir, "field-meta.json"), JSON.stringify(meta, null, 2) + "\n");
console.log("Wrote", path.join(adminDir, "field-meta.json"));
