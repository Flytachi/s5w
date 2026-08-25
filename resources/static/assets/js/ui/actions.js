/* Действия над строками: `data-action="сущность:глагол"`.
   Обработчики регистрируют разделы (features/*), здесь только диспетчер. */

import { e } from "./dom.js";
import * as popover from "./popover.js";
import * as toast from "./toast.js";

const handlers = new Map();

/** register("bucket:delete", async ({ el, row, id, name, dataset }) => {...}) */
export function register(verb, fn) {
  handlers.set(verb, fn);
}

export function init() {
  document.addEventListener("click", async (event) => {
    const el = event.target.closest("[data-action]");
    if (!el) return;

    const fn = handlers.get(el.dataset.action);
    if (!fn) return;

    event.preventDefault();
    popover.closeAll();

    const ctx = {
      el,
      event,
      row: el.closest("[data-row]"),
      id: el.dataset.id,
      name: el.dataset.name,
      dataset: el.dataset,
      fromDrawer: el.dataset.fromDrawer !== undefined,
    };

    try {
      await fn(ctx);
    } catch (err) {
      toast.show(e(err.message || "Не получилось"), { type: "error" });
    }
  });
}
