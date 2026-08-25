/* Всплывающие слои: меню действий и списки селектов.

   Меню лежат внутри прокручиваемых контейнеров, и `position: absolute` там
   обрезается. Открытое меню переводим в `position: fixed` и ставим по кнопке —
   оно больше никому не принадлежит и ничем не режется. Меню НЕ переносится в
   другое место DOM: обработчики действий ищут строку через `closest("[data-row]")`.

   Ни у одного предка меню не должно быть transform / filter / backdrop-filter —
   иначе fixed-слой считается не от окна (см. комментарий в base.css). Это
   правило каркаса, поэтому здесь один замер вместо трёх.

   На телефоне меню становится нижней шторкой (.is-sheet): крупные пункты,
   затемнение через ::before самого меню — так шторка работает и внутри <dialog>. */

import { isMobile } from "./dom.js";

const open = new Map(); // menu -> { anchor, options, owner }
let closedAt = 0;

const MARGIN = 8;

function place(menu, anchor, { align, gap, matchWidth }) {
  if (menu.classList.contains("is-sheet")) {
    menu.style.left = menu.style.top = menu.style.width = "";
    return;
  }

  const a = anchor.getBoundingClientRect();
  if (matchWidth) menu.style.width = `${Math.max(a.width, 160)}px`;

  // Сначала ставим в угол, чтобы размер меню померить в фиксированном слое:
  // в потоке ячейки ширина другая, а с ней и высота из-за переносов.
  menu.style.left = "0px";
  menu.style.top = "0px";
  const size = menu.getBoundingClientRect();

  const vw = document.documentElement.clientWidth;
  const vh = window.innerHeight;

  let top = a.bottom + gap;
  if (top + size.height > vh - MARGIN) top = a.top - gap - size.height; // вниз не влезло — вверх
  top = Math.min(Math.max(MARGIN, top), Math.max(MARGIN, vh - size.height - MARGIN));

  let left = align === "right" ? a.right - size.width : a.left;
  left = Math.min(Math.max(MARGIN, left), Math.max(MARGIN, vw - size.width - MARGIN));

  menu.style.left = `${left}px`;
  menu.style.top = `${top}px`;
}

/**
 * @param {HTMLElement} menu    слой, который показываем
 * @param {HTMLElement} anchor  кнопка, от которой считать координаты
 * @param {object} opts         { align: "right"|"left", gap, matchWidth, owner }
 *   owner — элемент, который считается «своим» для клика мимо (обычно .dropdown/.cselect)
 */
export function openMenu(menu, anchor, opts = {}) {
  const options = { align: "right", gap: 8, matchWidth: false, owner: anchor, ...opts };
  closeAll(menu);

  menu.classList.add("is-floating");
  menu.classList.toggle("is-sheet", isMobile());
  open.set(menu, { anchor, options });
  anchor.setAttribute("aria-expanded", "true");
  place(menu, anchor, options);

  // Клавиатура: стрелки ходят по пунктам.
  const first = menu.querySelector("[role=menuitem], .dropdown__item, .cselect__option.is-selected, .cselect__option");
  if (first && document.activeElement === anchor && !isMobile()) first.focus({ preventScroll: true });
}

export function closeMenu(menu) {
  const entry = open.get(menu);
  if (!entry) return;

  menu.classList.remove("is-floating", "is-sheet");
  menu.style.top = menu.style.left = menu.style.width = "";
  entry.anchor.setAttribute("aria-expanded", "false");
  entry.options.owner?.classList.remove("is-open");
  open.delete(menu);
  closedAt = performance.now();
  if (entry.options.onClose) entry.options.onClose();
}

export function closeAll(except) {
  open.forEach((entry, menu) => {
    if (menu !== except) closeMenu(menu);
  });
}

export const isOpen = (menu) => open.has(menu);
export const hasOpen = () => open.size > 0;

/** Меню только что закрылось по Escape — модалке закрываться не надо. */
export const justClosed = () => performance.now() - closedAt < 120;

function reflow() {
  if (open.size === 0) return;
  let hidden = false;
  open.forEach(({ anchor }) => {
    const a = anchor.getBoundingClientRect();
    if (a.bottom < 0 || a.top > window.innerHeight) hidden = true;
  });
  if (hidden) { closeAll(); return; }
  open.forEach(({ anchor, options }, menu) => place(menu, anchor, options));
}

export function init() {
  // capture — иначе прокрутка внутренних контейнеров сюда не всплывёт.
  // Без requestAnimationFrame: меню одно, а кадр задержки заметен.
  window.addEventListener("scroll", reflow, true);
  window.addEventListener("resize", reflow);

  // Клик мимо: закрыть всё, кроме меню, чей владелец содержит цель клика.
  // Клик по самой шторке (её затемнению) тоже считается «мимо».
  document.addEventListener("click", (e) => {
    open.forEach((entry, menu) => {
      if (e.target === menu) { closeMenu(menu); return; }
      if (entry.options.owner?.contains(e.target)) return;
      closeMenu(menu);
    });
  });

  document.addEventListener("keydown", (e) => {
    if (open.size === 0) return;

    if (e.key === "Escape") {
      e.stopPropagation();
      closeAll();
      return;
    }

    if (e.key !== "ArrowDown" && e.key !== "ArrowUp") return;
    const menu = Array.from(open.keys()).at(-1);
    const items = Array.from(menu.querySelectorAll("button, a")).filter((el) => !el.disabled);
    if (items.length === 0) return;

    e.preventDefault();
    const index = items.indexOf(document.activeElement);
    const next = e.key === "ArrowDown" ? (index + 1) % items.length : (index - 1 + items.length) % items.length;
    items[next].focus();
  });

  // Дропдауны — делегированно: строки появляются на лету.
  document.addEventListener("click", (e) => {
    const toggle = e.target.closest("[data-dropdown-toggle]");
    if (!toggle) return;

    const dropdown = toggle.closest(".dropdown");
    const menu = dropdown?.querySelector(".dropdown__menu");
    if (!menu) return;

    if (open.has(menu)) {
      closeMenu(menu);
    } else {
      dropdown.classList.add("is-open");
      openMenu(menu, toggle, { align: "right", owner: dropdown });
    }
  });

  // Пункт меню выбран — меню закрываем (ссылки уходят на новую страницу сами).
  document.addEventListener("click", (e) => {
    const item = e.target.closest(".dropdown__item");
    if (!item) return;
    const menu = item.closest(".dropdown__menu");
    if (menu) closeMenu(menu);
  });

  // Смена ширины окна между телефоном и столом — меню закрываем: режим другой.
  window.matchMedia("(max-width: 767.98px)").addEventListener("change", () => closeAll());
}
