/* Боковое меню.

   Шире lg (1024px) оно стоит в сетке страницы и ничем не управляется.
   Уже — выезжает поверх содержимого: затемнение, блокировка прокрутки,
   `inert` на остальной странице, Escape и возврат фокуса. При смене ширины
   окна состояние сбрасывается — иначе затемнение оставалось висеть. */

import * as popover from "./popover.js";

const DESKTOP = "(min-width: 1024px)";

export function init() {
  const sidebar = document.querySelector("[data-sidebar]");
  const burger = document.querySelector("[data-sidebar-toggle]");
  const overlay = document.querySelector("[data-sidebar-overlay]");
  const main = document.querySelector("[data-main]");
  if (!sidebar || !burger) return;

  const mq = window.matchMedia(DESKTOP);
  let opened = false;

  const setInert = (on) => {
    if (main) main.inert = on;
  };

  const open = () => {
    if (opened || mq.matches) return;
    opened = true;
    sidebar.dataset.open = "";
    if (overlay) overlay.hidden = false;
    document.documentElement.dataset.scrollLock = "";
    setInert(true);
    burger.setAttribute("aria-expanded", "true");
    const first = sidebar.querySelector("a, button");
    first?.focus({ preventScroll: true });
  };

  const close = ({ refocus = true } = {}) => {
    if (!opened) return;
    opened = false;
    popover.closeAll();
    delete sidebar.dataset.open;
    if (overlay) overlay.hidden = true;
    delete document.documentElement.dataset.scrollLock;
    setInert(false);
    burger.setAttribute("aria-expanded", "false");
    if (refocus) burger.focus({ preventScroll: true });
  };

  burger.setAttribute("aria-controls", sidebar.id || "sidebar");
  burger.setAttribute("aria-expanded", "false");
  burger.addEventListener("click", () => (opened ? close() : open()));
  overlay?.addEventListener("click", () => close());
  sidebar.querySelector("[data-sidebar-close]")?.addEventListener("click", () => close());

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && opened && !popover.hasOpen()) close();
  });

  // Стали шире — меню в сетке, затемнение и inert снимаем.
  mq.addEventListener("change", (e) => {
    if (e.matches) close({ refocus: false });
  });

  // Переход по ссылке: страница перезагрузится, но чтобы не мигать — закрываем.
  sidebar.addEventListener("click", (e) => {
    if (e.target.closest("a[href]") && opened) close({ refocus: false });
  });
}
