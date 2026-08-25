/* Счётчики в плитках сводки и пустые состояния списков. */

export function setCounter(name, value) {
  const el = document.querySelector(`[data-counter="${name}"]`);
  if (!el) return;

  const next = Math.max(0, value);
  el.textContent = String(next);
  el.classList.toggle("is-zero", next === 0);
}

export function bumpCounter(name, delta) {
  const el = document.querySelector(`[data-counter="${name}"]`);
  if (el) setCounter(name, Number(el.textContent.replace(/\D/g, "")) + delta);
}

export function toggleEmpty(name, show) {
  const empty = document.querySelector(`[data-empty="${name}"]`);
  if (empty) empty.hidden = !show;
}

/** Убирает строку и включает пустое состояние, если список опустел. */
export function dropRow(row, group) {
  row.remove();
  const rows = document.querySelector(`[data-rows="${group}"]`);
  if (rows && rows.querySelectorAll("[data-row]").length === 0) toggleEmpty(group, true);
}
