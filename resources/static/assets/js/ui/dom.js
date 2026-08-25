/* Мелкие помощники для работы с DOM. */

export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

/** Экранирование для вставки в разметку. */
export const e = (value) =>
  String(value == null ? "" : value).replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  })[c]);

/** Первый элемент из строки разметки. */
export function node(html) {
  const tpl = document.createElement("template");
  tpl.innerHTML = html.trim();
  return tpl.content.firstElementChild;
}

/** Строка таблицы: <tr> нельзя создать через template без <tbody>. */
export function rowNode(html) {
  const tbody = document.createElement("tbody");
  tbody.innerHTML = html.trim();
  return tbody.firstElementChild;
}

/* Те же пороги, что в base.css: одна точка правды для отзывчивости. */
export const isMobile = () => window.matchMedia("(max-width: 767.98px)").matches;
export const isCoarse = () => window.matchMedia("(pointer: coarse)").matches;
export const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export const bucketId = () => document.body.dataset.bucketId || "";

/** Русское склонение: plural(3, "файл", "файла", "файлов"). */
export function plural(count, one, few, many) {
  const mod100 = count % 100;
  if (mod100 >= 11 && mod100 <= 14) return many;
  return [many, one, few, few, few, many, many, many, many, many][count % 10];
}
