import { en } from "./en.js";
import { ru } from "./ru.js";

export const LANGS = ["en", "ru"];

const DICTS = { en, ru };
const FALLBACK = "en";
const STORE = "lang";

let current = restore();
let rules = null;

function restore() {
  try {
    const saved = localStorage.getItem(STORE);
    if (saved && LANGS.includes(saved)) return saved;
  } catch (err) {
    return FALLBACK;
  }
  return FALLBACK;
}

function interpolate(value, vars) {
  return value.replace(/\{(\w+)\}/g, (whole, name) => {
    if (!(name in vars)) return whole;
    const slot = vars[name];
    return typeof slot === "string" && slot.startsWith("@") ? t(slot.slice(1)) : String(slot);
  });
}

export const lang = () => current;

export function t(key, vars) {
  const value = DICTS[current][key] ?? DICTS[FALLBACK][key] ?? key;
  return vars ? interpolate(value, vars) : value;
}

export function plural(key, count, vars) {
  const forms = DICTS[current][key] ?? DICTS[FALLBACK][key];
  if (!forms || typeof forms !== "object") return t(key, vars);
  rules ||= new Intl.PluralRules(current);
  const form = forms[rules.select(count)] ?? forms.other ?? forms.many ?? forms.one;
  return interpolate(form, { n: num(count), ...vars });
}

export function num(value) {
  return Number(value).toLocaleString(locale());
}

export function bytes(value) {
  const units = t("unit.bytes").split(",");
  let size = Number(value);
  let i = 0;
  while (size >= 1024 && i < units.length - 1) {
    size /= 1024;
    i++;
  }
  const shown = i === 0 ? String(Math.trunc(size)) : size.toFixed(1);
  return shown + " " + units[i];
}

const STEPS = [
  [31536000, "y"],
  [2592000, "mo"],
  [86400, "d"],
  [3600, "h"],
  [60, "min"],
];

export function ago(value) {
  const diff = Math.floor((Date.now() - Date.parse(value)) / 1000);
  if (diff < 60) return t("time.now");
  for (const [size, unit] of STEPS) {
    if (diff >= size) return t("time.ago", { value: Math.floor(diff / size) + " " + t("unit." + unit) });
  }
  return t("time.now");
}

export function left(value) {
  if (!value) return t("time.forever");
  const diff = Math.floor((Date.parse(value) - Date.now()) / 1000);
  if (diff <= 0) return t("time.expired");
  for (const [size, unit] of STEPS.slice(2)) {
    if (diff >= size) return t("time.left", { value: Math.floor(diff / size) + " " + t("unit." + unit) });
  }
  return t("time.soon");
}

export function month(value) {
  const date = new Date(String(value) + "-01T00:00:00");
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString(locale(), { month: "long" });
}

const FORMATTERS = { bytes, ago, left, num, month };

function vars(el) {
  if (!el.dataset.i18nVars) return null;
  try {
    return JSON.parse(el.dataset.i18nVars);
  } catch (err) {
    return null;
  }
}

export function apply(root = document) {
  root.querySelectorAll("[data-i18n]").forEach((el) => {
    el.textContent = t(el.dataset.i18n, vars(el));
  });

  root.querySelectorAll("[data-i18n-attr]").forEach((el) => {
    el.dataset.i18nAttr.split(";").forEach((pair) => {
      const [attr, key] = pair.split(":");
      if (attr && key) el.setAttribute(attr.trim(), t(key.trim(), vars(el)));
    });
  });

  root.querySelectorAll("[data-i18n-plural]").forEach((el) => {
    el.textContent = plural(el.dataset.i18nPlural, Number(el.dataset.count || 0), vars(el));
  });

  root.querySelectorAll("[data-fmt]").forEach((el) => {
    const format = FORMATTERS[el.dataset.fmt];
    if (format) el.textContent = format(el.dataset.value);
  });

  relabelDays(root);
  retitleFolders(root);

  if (root === document) retitle();
}

function retitleFolders(root) {
  root.querySelectorAll(".fm__folder-wrap").forEach((wrap) => {
    const link = wrap.querySelector(".fm__folder");
    const name = wrap.dataset.name;
    if (!link || name === undefined) return;
    const marks = Array.from(wrap.querySelectorAll(".fm__badges [aria-label]"))
      .map((mark) => mark.getAttribute("aria-label"));
    link.title = marks.length === 0 ? name : name + " — " + marks.join(", ");
  });
}

function locale() {
  return current === "ru" ? "ru-RU" : "en-US";
}

function relabelDays(root) {
  root.querySelectorAll(".tchart__col[data-day]").forEach((col) => {
    const date = new Date(col.dataset.day + "T00:00:00");
    if (Number.isNaN(date.getTime())) return;
    col.dataset.title = date.toLocaleDateString(locale(), { day: "numeric", month: "long", year: "numeric" });
    const label = col.querySelector(".tchart__label");
    if (!label || label.textContent.trim() === "") return;
    label.textContent = date.getDate() === 1
      ? date.toLocaleDateString(locale(), { day: "numeric", month: "short" })
      : String(date.getDate());
  });
}

function retitle() {
  const key = document.body.dataset.titleKey;
  const name = document.body.dataset.titleName;
  document.title = (key ? t(key) : name || "") + " — s5w";
}

export function setLang(next) {
  if (!LANGS.includes(next) || next === current) return;
  current = next;
  rules = null;
  try {
    localStorage.setItem(STORE, next);
  } catch (err) {}
  document.documentElement.lang = next;
  apply(document);
  document.dispatchEvent(new CustomEvent("lang:change", { detail: { lang: next } }));
}

export function init() {
  document.documentElement.lang = current;
  apply(document);
  document.querySelectorAll("[data-lang-toggle]").forEach(sync);

  document.addEventListener("click", (event) => {
    const btn = event.target.closest("[data-lang-toggle]");
    if (!btn) return;
    setLang(LANGS[(LANGS.indexOf(current) + 1) % LANGS.length]);
    document.querySelectorAll("[data-lang-toggle]").forEach(sync);
  });
}

function sync(btn) {
  const code = btn.querySelector("[data-lang-code]");
  if (code) code.textContent = current.toUpperCase();
  btn.setAttribute("aria-label", t("lang.switch"));
  btn.setAttribute("title", t("lang.switch"));
}
