/* Графики рисует сервер разметкой; здесь только подсказки и подсветка.
   Одна плавающая подсказка на всё: и для столбиков трафика, и для кольца. */

import { e } from "./dom.js";

let tip = null;

function tooltip() {
  if (tip) return tip;
  tip = document.createElement("div");
  tip.className = "ttip";
  tip.setAttribute("role", "tooltip");
  document.body.appendChild(tip);
  return tip;
}

/** Держим подсказку в окне: у крайних столбцов она иначе уезжает за край. */
function placeNear(box) {
  const own = tip.getBoundingClientRect();
  const left = Math.min(
    Math.max(8, box.left + box.width / 2 - own.width / 2),
    window.innerWidth - own.width - 8,
  );
  const above = box.top - own.height - 10;
  tip.style.left = left + "px";
  tip.style.top = (above < 8 ? box.bottom + 10 : above) + "px";
}

function placeAtPointer(event) {
  const own = tip.getBoundingClientRect();
  tip.style.left = Math.min(event.clientX + 14, window.innerWidth - own.width - 12) + "px";
  tip.style.top = Math.max(event.clientY - own.height - 12, 8) + "px";
}

const hide = () => tip?.classList.remove("is-open");

/* --- Столбики трафика --------------------------------------------------- */

function initTraffic() {
  const charts = document.querySelectorAll("[data-tchart]");
  if (!charts.length) return;

  const set = (key, col, chart) => `
    <div class="ttip__row">
      <span class="ttip__dot" style="background:${e(chart.dataset[key + "Color"])}"></span>
      <span class="ttip__name">${e(chart.dataset[key + "Label"])}</span>
      <span class="ttip__value">${e(col.dataset[key + "Value"])}</span>
    </div>`;

  charts.forEach((chart) => {
    const showCol = (col) => {
      tooltip().innerHTML = `<div class="ttip__title">${e(col.dataset.title)}</div>${set("a", col, chart)}${set("b", col, chart)}`;
      tip.classList.add("is-open");
      placeNear(col.getBoundingClientRect());
    };

    chart.addEventListener("pointerover", (ev) => {
      const col = ev.target.closest(".tchart__col");
      if (col) showCol(col);
    });
    chart.addEventListener("pointermove", (ev) => {
      const col = ev.target.closest(".tchart__col");
      if (col) placeNear(col.getBoundingClientRect());
    });
    chart.addEventListener("pointerleave", hide);
    // Касание: показать на тап, скрыть по тапу мимо.
    chart.addEventListener("click", (ev) => {
      const col = ev.target.closest(".tchart__col");
      if (col) showCol(col);
    });
  });
}

/* --- Кольцо по расширениям ---------------------------------------------- */

function initDonut() {
  const donut = document.querySelector("[data-donut]");
  if (!donut) return;

  const rows = document.querySelectorAll(".kinds tr[data-slice]");

  const highlight = (index) => {
    donut.classList.toggle("is-hover", index !== null);
    donut.querySelectorAll(".ring__slice").forEach((s) => s.classList.remove("is-active"));
    rows.forEach((r) => r.classList.remove("is-active"));
    if (index === null) return;
    donut.querySelector(`[data-slice="${index}"]`)?.classList.add("is-active");
    document.querySelector(`.kinds tr[data-slice="${index}"]`)?.classList.add("is-active");
  };

  const show = (slice, event) => {
    const d = slice.dataset;
    tooltip().innerHTML = `<div class="ttip__title">${e(d.name)}</div>
      <div class="ttip__row"><span class="ttip__name">${e(d.size)}</span><span class="ttip__value">${e(d.share)}%</span></div>
      <div class="ttip__row"><span class="ttip__name">${e(d.count)} шт</span></div>`;
    tip.classList.add("is-open");
    placeAtPointer(event);
  };

  donut.addEventListener("pointerover", (ev) => {
    const slice = ev.target.closest(".ring__slice");
    if (!slice) return;
    highlight(slice.dataset.slice);
    show(slice, ev);
  });
  donut.addEventListener("pointermove", (ev) => {
    if (tip?.classList.contains("is-open") && ev.target.closest(".ring__slice")) placeAtPointer(ev);
  });
  donut.addEventListener("pointerleave", () => { highlight(null); hide(); });

  rows.forEach((row) => {
    row.addEventListener("pointerenter", () => highlight(row.dataset.slice));
    row.addEventListener("pointerleave", () => highlight(null));
  });
}

export function init() {
  initTraffic();
  initDonut();
  document.addEventListener("click", (ev) => {
    if (!ev.target.closest("[data-tchart], [data-donut]")) hide();
  });
  window.addEventListener("scroll", hide, true);
}
