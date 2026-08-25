/* Уведомления. Контейнер — popover="manual": так тосты живут в верхнем слое и
   видны поверх открытого <dialog>. Когда открывается новая модалка, контейнер
   перепоказывается, чтобы снова оказаться сверху (см. modal.js). */

const ICON = { ok: "i-check-circle", error: "i-x-circle", warn: "i-alert-triangle", info: "i-info" };
const MAX = 4;

function container() {
  let box = document.querySelector("[data-toasts]");
  if (!box) {
    box = document.createElement("div");
    box.className = "toasts";
    box.setAttribute("data-toasts", "");
    box.setAttribute("popover", "manual");
    document.body.appendChild(box);
  }
  box.setAttribute("aria-live", "polite");
  return box;
}

function raise(box) {
  if (!box.showPopover) return;
  try {
    if (box.matches(":popover-open")) box.hidePopover();
    box.showPopover();
  } catch (e) {
    /* не popover-контекст — просто останется fixed */
  }
}

/** Поднять контейнер поверх только что открытой модалки. */
export function bringToFront() {
  const box = document.querySelector("[data-toasts]");
  if (box && box.childElementCount > 0) raise(box);
}

/**
 * show("Готово", { type: "ok", detail, action: { label, onClick }, timeout })
 * message и detail — разметка: динамические куски экранируйте через e().
 */
export function show(message, opts = {}) {
  const box = container();
  const type = opts.type || "info";

  const toast = document.createElement("div");
  toast.className = "toast toast--" + type;
  toast.setAttribute("role", type === "error" ? "alert" : "status");
  toast.innerHTML = `
    <svg class="icon"><use href="#${opts.icon || ICON[type]}"/></svg>
    <div class="toast__body">
      <div>${message}</div>
      ${opts.detail ? `<div class="toast__detail">${opts.detail}</div>` : ""}
    </div>
    ${opts.action ? `<button type="button" class="toast__action">${opts.action.label}</button>` : ""}
    <button type="button" class="toast__close" aria-label="Закрыть"><svg class="icon icon--sm"><use href="#i-x"/></svg></button>`;

  const remove = () => {
    if (!toast.isConnected) return;
    toast.classList.add("is-leaving");
    const done = () => {
      toast.remove();
      if (box.childElementCount === 0 && box.hidePopover && box.matches(":popover-open")) box.hidePopover();
    };
    toast.addEventListener("animationend", done, { once: true });
    setTimeout(done, 400); // reduced-motion: анимации нет, событие не придёт
  };

  const timer = setTimeout(remove, opts.timeout || (opts.action ? 7000 : 4200));
  toast.querySelector(".toast__close").addEventListener("click", () => { clearTimeout(timer); remove(); });

  if (opts.action) {
    toast.querySelector(".toast__action").addEventListener("click", () => {
      clearTimeout(timer);
      remove();
      opts.action.onClick();
    });
  }

  while (box.childElementCount >= MAX) box.firstElementChild.remove();
  box.appendChild(toast);
  raise(box);
  return toast;
}
