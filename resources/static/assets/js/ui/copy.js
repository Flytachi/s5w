/* Копирование в буфер: [data-copy="значение"]. */

import * as toast from "./toast.js";

export function copyText(value) {
  const done = () => toast.show("Скопировано", { type: "ok", timeout: 1800 });

  if (navigator.clipboard) {
    navigator.clipboard.writeText(value).then(done, done);
    return;
  }

  const ta = document.createElement("textarea");
  ta.value = value;
  document.body.appendChild(ta);
  ta.select();
  document.execCommand("copy");
  ta.remove();
  done();
}

export function init() {
  document.addEventListener("click", (e) => {
    const el = e.target.closest("[data-copy]");
    if (!el) return;
    e.preventDefault();
    copyText(el.dataset.copy);
  });
}
