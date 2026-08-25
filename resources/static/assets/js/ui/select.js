/* Свой селект поверх нативного <select>.

   Список вариантов у нативного селекта рисует браузер, и оформить его под тему
   нельзя. Поэтому сам <select> остаётся в форме носителем значения (его читает
   collect() и заполняют обработчики), а видимой становится наша кнопка со
   списком. Список всплывает через popover.js, на телефоне — шторкой. */

import { e } from "./dom.js";
import * as popover from "./popover.js";

export function enhanceSelects(root = document) {
  root.querySelectorAll("select.select-native:not([data-native])").forEach((select) => {
    if (select.closest(".cselect")) return;

    const wrap = document.createElement("div");
    wrap.className = "cselect cselect--field";
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.hidden = true;
    select.tabIndex = -1;

    wrap.insertAdjacentHTML(
      "afterbegin",
      `<button type="button" class="cselect__btn" aria-haspopup="listbox" aria-expanded="false">
         <span class="cselect__value"></span>
         <svg class="icon icon--sm cselect__chev"><use href="#i-chevron-down"/></svg>
       </button>
       <div class="cselect__menu" role="listbox">
         ${Array.from(select.options)
           .map(
             (o) => `<button type="button" class="cselect__option" role="option" data-value="${e(o.value)}">
                       ${e(o.textContent.trim())}
                       <svg class="icon icon--check"><use href="#i-check"/></svg>
                     </button>`,
           )
           .join("")}
       </div>`,
    );

    // значение могло смениться из кода — подпись кнопки берём от селекта
    wrap.sync = () => {
      const option = select.options[select.selectedIndex];
      wrap.querySelector(".cselect__value").textContent = option ? option.textContent.trim() : "";
      wrap.querySelectorAll(".cselect__option").forEach((btn) => {
        const selected = btn.dataset.value === select.value;
        btn.classList.toggle("is-selected", selected);
        btn.setAttribute("aria-selected", selected ? "true" : "false");
      });
    };
    wrap.sync();

    wrap.addEventListener("cselect:pick", (ev) => {
      select.value = ev.detail.value;
      wrap.sync();
      select.dispatchEvent(new Event("change", { bubbles: true }));
    });
  });
}

/** Пересобрать подписи после программной установки значений (открытие формы, reset). */
export function refreshSelects(root = document) {
  root.querySelectorAll(".cselect--field").forEach((wrap) => wrap.sync?.());
}

/** Новый вариант — и в <select>, и в нарисованный список. */
export function addOption(select, value, label = value) {
  if (Array.from(select.options).some((o) => o.value === value)) return;
  select.add(new Option(label, value));
  select.closest(".cselect--field")?.querySelector(".cselect__menu")?.insertAdjacentHTML(
    "beforeend",
    `<button type="button" class="cselect__option" role="option" data-value="${e(value)}">
       ${e(label)}<svg class="icon icon--check"><use href="#i-check"/></svg>
     </button>`,
  );
}

export function init() {
  // Делегированно: селекты появляются на лету — например, на карточке картинки.
  document.addEventListener("click", (ev) => {
    const btn = ev.target.closest(".cselect__btn");
    if (!btn) return;

    const sel = btn.closest(".cselect");
    const menu = sel.querySelector(".cselect__menu");
    if (popover.isOpen(menu)) {
      popover.closeMenu(menu);
    } else {
      sel.classList.add("is-open");
      popover.openMenu(menu, btn, { align: "left", gap: 6, matchWidth: true, owner: sel });
    }
  });

  document.addEventListener("click", (ev) => {
    const opt = ev.target.closest(".cselect__option");
    if (!opt) return;

    const sel = opt.closest(".cselect");
    const menu = sel.querySelector(".cselect__menu");
    sel.querySelectorAll(".cselect__option").forEach((o) => {
      o.classList.remove("is-selected");
      o.setAttribute("aria-selected", "false");
    });
    opt.classList.add("is-selected");
    opt.setAttribute("aria-selected", "true");

    // содержимое пункта переезжает в кнопку — без галочки справа
    const clone = opt.cloneNode(true);
    clone.querySelector(".icon--check")?.remove();
    sel.querySelector(".cselect__value").innerHTML = clone.innerHTML;

    popover.closeMenu(menu);
    sel.querySelector(".cselect__btn")?.focus({ preventScroll: true });
    sel.dispatchEvent(new CustomEvent("cselect:pick", { detail: { value: opt.dataset.value } }));
  });
}
