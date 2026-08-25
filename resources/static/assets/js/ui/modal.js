/* Модальные окна на нативном <dialog>.

   Верхний слой браузера решает то, с чем раньше боролись вручную: подложка
   не обрезается контейнерами, фокус заперт внутри, Escape закрывает верхнее.
   Здесь остаётся своё: стек, блокировка прокрутки страницы, возврат фокуса,
   подтверждения и хуки на открытие. */

import { node, isCoarse } from "./dom.js";
import * as popover from "./popover.js";
import * as toast from "./toast.js";

const stack = []; // { dialog, trigger }
const onOpenHooks = new Map(); // id -> fn(dialog, context)

const resolve = (target) =>
  typeof target === "string" ? document.getElementById(target) : target?.closest?.("dialog") ?? null;

function lock() {
  document.documentElement.dataset.scrollLock = "";
}

function unlock() {
  if (stack.length === 0) delete document.documentElement.dataset.scrollLock;
}

function focusInside(dialog) {
  // На телефоне не тянем клавиатуру сразу: она закрыла бы половину шторки.
  if (isCoarse()) return;
  const el =
    dialog.querySelector("[autofocus]") ||
    dialog.querySelector("input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])");
  if (el) setTimeout(() => el.focus({ preventScroll: true }), 60);
}

function bind(dialog) {
  if (dialog.dataset.modalBound) return;
  dialog.dataset.modalBound = "1";

  // Клик по подложке: цель — сам <dialog>, содержимое закрывает его целиком.
  dialog.addEventListener("click", (e) => {
    if (e.target === dialog) close(dialog);
  });

  // Escape: если только что закрылось меню — модалку не трогаем.
  dialog.addEventListener("cancel", (e) => {
    if (popover.hasOpen() || popover.justClosed()) e.preventDefault();
  });

  // Единая уборка: и для close(), и для нативного закрытия по Escape.
  dialog.addEventListener("close", () => {
    const index = stack.findIndex((entry) => entry.dialog === dialog);
    const entry = index === -1 ? null : stack.splice(index, 1)[0];
    popover.closeAll();
    unlock();
    dialog.dispatchEvent(new CustomEvent("modal:close", { bubbles: true }));
    if (entry?.trigger?.isConnected) entry.trigger.focus({ preventScroll: true });
  });
}

/**
 * Открыть модалку по id или элементу. Возвращает <dialog> или null.
 * context уходит в хук onOpen — так одна форма открывается в разных режимах.
 */
export function open(target, { trigger = null, context = null } = {}) {
  const dialog = resolve(target);
  if (!dialog) return null;
  if (dialog.open) return dialog;

  bind(dialog);
  stack.push({ dialog, trigger: trigger || document.activeElement });
  lock();
  dialog.showModal();
  toast.bringToFront();

  const hook = onOpenHooks.get(dialog.id);
  if (hook) hook(dialog, context);
  dialog.dispatchEvent(new CustomEvent("modal:open", { bubbles: true, detail: { context } }));
  focusInside(dialog);
  return dialog;
}

export function close(target) {
  const dialog = resolve(target);
  if (dialog?.open) dialog.close();
}

export function closeTop() {
  const top = stack.at(-1);
  if (top) close(top.dialog);
}

export const current = () => stack.at(-1)?.dialog ?? null;
export const isOpen = (target) => !!resolve(target)?.open;

/** Что сделать при открытии модалки с таким id (сброс формы, режим). */
export function onOpen(id, fn) {
  onOpenHooks.set(id, fn);
}

/** Подтверждение. Строится на лету — разметку держать негде. */
export function confirm({ title, text, confirmLabel = "Удалить", tone = "danger" }) {
  return new Promise((resolvePromise) => {
    const dialog = node(`
      <dialog class="modal modal--sm" aria-labelledby="confirm-title">
        <div class="modal__inner">
          <header class="modal__header">
            <span class="modal__icon tone tone--${tone}"><svg class="icon"><use href="#i-alert-triangle"/></svg></span>
            <h2 class="modal__title" id="confirm-title">${title}</h2>
          </header>
          <div class="modal__body"><p class="modal__text">${text}</p></div>
          <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-no>Отмена</button>
            <button type="button" class="btn btn--${tone === "danger" ? "danger" : "primary"}" data-yes autofocus>${confirmLabel}</button>
          </footer>
        </div>
      </dialog>`);

    let answer = false;
    dialog.querySelector("[data-yes]").addEventListener("click", () => { answer = true; close(dialog); });
    dialog.querySelector("[data-no]").addEventListener("click", () => close(dialog));
    dialog.addEventListener("close", () => {
      resolvePromise(answer);
      setTimeout(() => dialog.remove(), 300);
    });

    document.body.appendChild(dialog);
    open(dialog);
  });
}

export function init() {
  document.addEventListener("click", (e) => {
    const opener = e.target.closest("[data-modal-open]");
    if (opener) {
      e.preventDefault();
      open(opener.dataset.modalOpen, { trigger: opener });
      return;
    }

    const closer = e.target.closest("[data-modal-close]");
    if (closer) {
      e.preventDefault();
      close(closer);
    }
  });
}

export const Modal = { open, close, closeTop, current, isOpen, onOpen, confirm };
