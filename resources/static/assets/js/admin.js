/* s5w admin — поведение интерфейса.
   Базовые виджеты (сайдбар, дропдауны, селекты, модалки, вкладки, таблицы)
   взяты из winter-admin-template. Всё, что меняет данные, идёт через Api и
   обновляет страницу на месте — перезагрузок нет. */

document.addEventListener("DOMContentLoaded", () => {
  initSidebar();
  initFloatingMenus();
  initDropdowns();
  enhanceSelects();
  enhanceNumbers();
  initCustomSelects();
  initBucketSwitch();
  initModals();
  initTabs();
  initAlerts();
  initTables();
  initCopy();
  initAutoSubmit();
  initForms();
  initActions();
  initUpload();
  initLogin();
  initLogout();
  initDonut();
});

/* ============================================================
   Диаграмма по расширениям
   ============================================================ */

function initDonut() {
  const donut = document.querySelector("[data-donut]");
  if (!donut) return;

  const rows = document.querySelectorAll(".kinds tr[data-slice]");
  const tip = document.createElement("div");
  tip.className = "ring-tip";
  tip.hidden = true;
  document.body.appendChild(tip);

  const find = (index) => ({
    slice: donut.querySelector(`[data-slice="${index}"]`),
    row: document.querySelector(`.kinds tr[data-slice="${index}"]`),
  });

  const highlight = (index) => {
    donut.classList.toggle("is-hover", index !== null);
    donut.querySelectorAll(".ring__slice").forEach((s) => s.classList.remove("is-active"));
    rows.forEach((r) => r.classList.remove("is-active"));
    if (index === null) return;

    const { slice, row } = find(index);
    slice?.classList.add("is-active");
    row?.classList.add("is-active");
  };

  const show = (slice, event) => {
    const d = slice.dataset;
    tip.innerHTML = `<b>.${d.name}</b> ${d.size}<br><span>${d.share}% · ${d.count} шт</span>`;
    tip.hidden = false;
    move(event);
  };

  const move = (event) => {
    tip.style.left = Math.min(event.clientX + 14, window.innerWidth - tip.offsetWidth - 12) + "px";
    tip.style.top = Math.max(event.clientY - tip.offsetHeight - 12, 8) + "px";
  };

  donut.addEventListener("mouseover", (e) => {
    const slice = e.target.closest(".ring__slice");
    if (!slice) return;
    highlight(slice.dataset.slice);
    show(slice, e);
  });

  donut.addEventListener("mousemove", (e) => {
    if (!tip.hidden) move(e);
  });

  donut.addEventListener("mouseleave", () => {
    highlight(null);
    tip.hidden = true;
  });

  rows.forEach((row) => {
    row.addEventListener("mouseenter", () => highlight(row.dataset.slice));
    row.addEventListener("mouseleave", () => highlight(null));
  });
}

/* ============================================================
   Вход и выход
   ============================================================ */

function initLogin() {
  const form = document.querySelector("[data-login-form]");
  if (!form) return;

  const box = form.querySelector("[data-login-error]");
  const peek = form.querySelector("[data-password-peek]");
  const password = form.querySelector('[name="password"]');

  peek?.addEventListener("click", () => {
    const shown = password.type === "text";
    password.type = shown ? "password" : "text";
    peek.querySelector("use").setAttribute("href", shown ? "#i-eye" : "#i-lock");
    password.focus();
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const button = form.querySelector('[type="submit"]');
    box.hidden = true;
    setBusy(button, true);

    try {
      await Api.post("/admin/auth/login", {
        login: form.querySelector('[name="login"]').value.trim(),
        password: password.value,
      });
      location.href = form.dataset.next || "/admin/ui";
    } catch (err) {
      form.querySelector("[data-login-message]").textContent =
        err.status === 429 ? "Слишком много попыток. Попробуйте позже." : err.message;
      box.hidden = false;
      password.value = "";
      password.focus();
    } finally {
      setBusy(button, false);
    }
  });
}

function initLogout() {
  document.querySelector("[data-logout]")?.addEventListener("click", async (e) => {
    e.preventDefault();
    try {
      await Api.post("/admin/auth/logout");
    } finally {
      location.href = "/admin/ui/login";
    }
  });
}

/* ============================================================
   Виджеты каркаса
   ============================================================ */

function initSidebar() {
  const burger = document.querySelector("[data-sidebar-toggle]");
  const sidebar = document.querySelector(".sidebar");
  if (!burger || !sidebar) return;

  let overlay = document.querySelector(".sidebar-overlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.className = "sidebar-overlay";
    document.body.appendChild(overlay);
  }

  const close = () => {
    sidebar.classList.remove("is-open");
    overlay.classList.remove("is-open");
  };

  burger.addEventListener("click", () => {
    sidebar.classList.toggle("is-open");
    overlay.classList.toggle("is-open");
  });
  overlay.addEventListener("click", close);
}

/* ---------------- Всплывающие меню ----------------
   Меню лежат внутри прокручиваемых контейнеров (`.main`, `.table-wrap`,
   сайдбар), а `position: absolute` такие контейнеры обрезают: у нижних строк
   таблицы меню уезжало под край. Поэтому открытое меню переводим в
   `position: fixed` и считаем координаты от кнопки — оно больше никому не
   принадлежит и ничем не режется. */

const floatingMenus = new Map();

function placeMenu(menu, anchor, { align, gap, matchWidth }) {
  const margin = 8;
  const a = anchor.getBoundingClientRect();
  if (matchWidth) menu.style.width = `${a.width}px`;

  // Ставим «как есть» и смотрим, куда элемент попал на самом деле: у
  // фиксированного слоя началом координат может оказаться не окно, а любой
  // предок с transform, filter или backdrop-filter — у нас такие и .layout,
  // и подложка модалки. Вместо того чтобы их угадывать, правим на разницу.
  const put = (x, y) => {
    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;
    return menu.getBoundingClientRect();
  };

  /** Координаты при известных размерах меню. */
  const layout = (size) => {
    let top = a.bottom + gap;
    if (top + size.height > window.innerHeight - margin) {
      // вниз не помещается — раскрываем вверх
      top = a.top - gap - size.height;
    }

    // И вверх могло не поместиться — например, когда сама кнопка уехала за
    // край. Меню всё равно держим в окне: обрезанное меню бесполезно.
    top = Math.min(Math.max(margin, top), Math.max(margin, window.innerHeight - size.height - margin));

    let leftAt = align === "right" ? a.right - size.width : a.left;
    leftAt = Math.min(Math.max(margin, leftAt), document.documentElement.clientWidth - size.width - margin);

    return [leftAt, top];
  };

  // Три замера, и каждый нужен: в потоке ячейки меню меряется не так, как в
  // фиксированном слое (другая ширина — другая высота из-за переносов), а сам
  // слой может начинаться не от окна, если у предка есть backdrop-filter.
  let box = put(0, 0);
  let [wantLeft, wantTop] = layout(box);

  box = put(wantLeft, wantTop);
  [wantLeft, wantTop] = layout(box);

  const shifted = put(wantLeft, wantTop);
  if (Math.abs(shifted.left - wantLeft) > 0.5 || Math.abs(shifted.top - wantTop) > 0.5) {
    put(wantLeft + (wantLeft - shifted.left), wantTop + (wantTop - shifted.top));
  }
}

function openMenu(menu, anchor, opts = {}) {
  const options = { align: "right", gap: 8, matchWidth: false, ...opts };
  menu.classList.add("is-floating");
  floatingMenus.set(menu, { anchor, options });
  placeMenu(menu, anchor, options);
}

function closeMenu(menu) {
  menu.classList.remove("is-floating");
  menu.style.top = menu.style.left = menu.style.width = "";
  floatingMenus.delete(menu);
}

function closeAllMenus(except) {
  document.querySelectorAll(".dropdown.is-open").forEach((d) => {
    if (d === except) return;
    d.classList.remove("is-open");
    closeMenu(d.querySelector(".dropdown__menu"));
  });
  document.querySelectorAll(".cselect.is-open").forEach((c) => {
    if (c === except) return;
    c.classList.remove("is-open");
    closeMenu(c.querySelector(".cselect__menu"));
  });
}

/** Кнопка уехала за пределы окна — меню закрываем, тянуть его не за чем. */
function closeOffscreenMenus() {
  let hidden = false;
  floatingMenus.forEach(({ anchor }) => {
    const a = anchor.getBoundingClientRect();
    if (a.bottom < 0 || a.top > window.innerHeight) hidden = true;
  });
  if (hidden) closeAllMenus();
}

/** Прокрутка любого контейнера: тянем меню за кнопкой. */
function reflowMenus() {
  floatingMenus.forEach(({ anchor, options }, menu) => placeMenu(menu, anchor, options));
}

function initFloatingMenus() {
  // Считаем прямо в обработчике, без requestAnimationFrame: открытое меню
  // всегда одно, а откладывание на кадр давало заметное отставание от кнопки.
  const onScroll = () => {
    if (floatingMenus.size === 0) return;
    closeOffscreenMenus();
    reflowMenus();
  };

  // capture — иначе прокрутка внутренних контейнеров сюда не всплывёт
  window.addEventListener("scroll", onScroll, true);
  window.addEventListener("resize", onScroll);
}

function initDropdowns() {
  document.addEventListener("click", (e) => {
    const toggle = e.target.closest("[data-dropdown-toggle]");
    const dropdown = toggle?.closest(".dropdown");

    closeAllMenus(dropdown);
    if (!dropdown) return;

    const menu = dropdown.querySelector(".dropdown__menu");
    if (dropdown.classList.toggle("is-open")) {
      openMenu(menu, toggle, { align: "right" });
    } else {
      closeMenu(menu);
    }
  });
}

/**
 * Нативный `<select>` подменяем своим компонентом.
 *
 * Список вариантов у нативного селекта рисует браузер, и оформить его под тему
 * нельзя — ни фон, ни отступы, ни галочку выбранного. Поэтому сам `<select>`
 * остаётся в форме носителем значения (его читает collect() и заполняют
 * обработчики), а видимой становится наша кнопка со списком.
 */
function enhanceSelects(root = document) {
  root.querySelectorAll("select.select-native:not([data-native])").forEach((select) => {
    if (select.closest(".cselect")) return;

    const wrap = document.createElement("div");
    wrap.className = "cselect cselect--field";
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.hidden = true;

    wrap.insertAdjacentHTML(
      "afterbegin",
      `<button type="button" class="cselect__btn">
         <span class="cselect__value"></span>
         <svg class="icon icon--sm cselect__chev"><use href="#i-chevron-down"/></svg>
       </button>
       <div class="cselect__menu">
         ${Array.from(select.options)
           .map(
             (o) => `<button type="button" class="cselect__option" data-value="${o.value.replace(/"/g, "&quot;")}">
                       ${o.textContent}
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
        btn.classList.toggle("is-selected", btn.dataset.value === select.value);
      });
    };
    wrap.sync();

    wrap.addEventListener("cselect:pick", (e) => {
      select.value = e.detail.value;
      wrap.sync();
      select.dispatchEvent(new Event("change", { bubbles: true }));
    });
  });
}

/** Числовому полю — свои стрелки: браузерные не поддаются оформлению. */
function enhanceNumbers(root = document) {
  root.querySelectorAll('input.input[type="number"]:not([data-plain])').forEach((input) => {
    if (input.closest(".number")) return;

    const box = document.createElement("div");
    box.className = "number";
    input.parentNode.insertBefore(box, input);
    box.appendChild(input);

    box.insertAdjacentHTML(
      "beforeend",
      `<div class="number__spin">
         <button type="button" data-step="up" tabindex="-1" aria-label="Больше">
           <svg class="icon"><use href="#i-chevron-down" style="transform:rotate(180deg);transform-origin:center"/></svg>
         </button>
         <button type="button" data-step="down" tabindex="-1" aria-label="Меньше">
           <svg class="icon"><use href="#i-chevron-down"/></svg>
         </button>
       </div>`,
    );

    box.querySelectorAll("[data-step]").forEach((btn) => {
      btn.addEventListener("click", () => {
        input[btn.dataset.step === "up" ? "stepUp" : "stepDown"]();
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.dispatchEvent(new Event("change", { bubbles: true }));
      });
    });
  });
}

/** Пересобрать подписи после программной установки значений (открытие формы, reset). */
function refreshSelects(root = document) {
  root.querySelectorAll(".cselect--field").forEach((wrap) => wrap.sync?.());
}

/** Свой селект: та же механика всплывания, ширина — по кнопке. */
function initCustomSelects() {
  // Делегированно, как и выбор варианта: селекты появляются на лету —
  // например, на карточке только что выбранной картинки.
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".cselect__btn");
    if (!btn) return;

    e.stopPropagation();
    const sel = btn.closest(".cselect");
    const menu = sel.querySelector(".cselect__menu");
    closeAllMenus(sel);
    if (sel.classList.toggle("is-open")) {
      openMenu(menu, btn, { align: "left", gap: 6, matchWidth: true });
    } else {
      closeMenu(menu);
    }
  });

  // Клик по варианту ловим делегированно: варианты появляются и на лету —
  // например, новая папка сразу должна оказаться в списке загрузки.
  document.addEventListener("click", (e) => {
    const opt = e.target.closest(".cselect__option");
    if (!opt) return;

    const sel = opt.closest(".cselect");
    const menu = sel.querySelector(".cselect__menu");
    sel.querySelectorAll(".cselect__option").forEach((o) => o.classList.remove("is-selected"));
    opt.classList.add("is-selected");

    // содержимое пункта переезжает в кнопку — без галочки справа
    const clone = opt.cloneNode(true);
    clone.querySelector(".icon--check")?.remove();
    sel.querySelector(".cselect__value").innerHTML = clone.innerHTML;

    sel.classList.remove("is-open");
    closeMenu(menu);
    sel.dispatchEvent(new CustomEvent("cselect:pick", { detail: { value: opt.dataset.value } }));
    sel.dispatchEvent(new CustomEvent("change", { detail: { value: opt.dataset.value } }));
  });

  // Клик мимо: гасим только селекты. closeAllMenus() здесь закрыл бы и
  // дропдаун, который в этом же клике открыл соседний обработчик.
  document.addEventListener("click", (e) => {
    if (e.target.closest(".cselect")) return;
    document.querySelectorAll(".cselect.is-open").forEach((sel) => {
      sel.classList.remove("is-open");
      closeMenu(sel.querySelector(".cselect__menu"));
    });
  });
}

/** Переключатель бакета в сайдбаре: раздел при смене сохраняется. */
function initBucketSwitch() {
  const sel = document.querySelector("[data-bucket-switch]");
  if (!sel) return;

  sel.addEventListener("change", (e) => {
    const section = sel.dataset.section === "overview" ? "" : "/" + sel.dataset.section;
    location.href = "/admin/ui/buckets/" + e.detail.value + section;
  });
}

function initModals() {
  document.addEventListener("click", (e) => {
    const opener = e.target.closest("[data-modal-open]");
    if (opener) {
      if (opener.dataset.modalOpen === "modal-bucket") openBucketModal(null);
      else if (opener.dataset.modalOpen === "modal-folder") openFolderModal(null);
      else openModal(opener.dataset.modalOpen);
      return;
    }
    if (e.target.closest("[data-modal-close]") || e.target.classList.contains("modal-backdrop")) {
      const backdrop = e.target.closest(".modal-backdrop") || e.target;
      backdrop.classList.remove("is-open");
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    document.querySelectorAll(".modal-backdrop.is-open").forEach((m) => m.classList.remove("is-open"));
    closeAllMenus();
  });
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return null;

  modal.classList.add("is-open");
  const first = modal.querySelector("input:not([type=hidden]), select, textarea");
  if (first) setTimeout(() => first.focus(), 60);

  return modal;
}

function closeModal(el) {
  const backdrop = el.closest(".modal-backdrop");
  if (backdrop) backdrop.classList.remove("is-open");
}

function initTabs() {
  document.querySelectorAll("[data-tabs]").forEach((tabs) => {
    const buttons = tabs.querySelectorAll(".tabs__btn");
    buttons.forEach((btn) => {
      btn.addEventListener("click", () => {
        buttons.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        const scope = document.querySelector(tabs.dataset.tabs) || document;
        scope.querySelectorAll(".tab-panel").forEach((p) => p.classList.remove("active"));
        const panel = document.getElementById(btn.dataset.tab);
        if (panel) panel.classList.add("active");
      });
    });
  });
}

function initAlerts() {
  document.addEventListener("click", (e) => {
    const closeBtn = e.target.closest(".alert__close");
    if (!closeBtn) return;
    const alert = closeBtn.closest(".alert");
    alert.style.transition = "opacity .2s";
    alert.style.opacity = "0";
    setTimeout(() => alert.remove(), 200);
  });
}

function initTables() {
  document.querySelectorAll("table[data-sortable]").forEach((table) => {
    table.querySelectorAll("th.sortable").forEach((th) => {
      th.addEventListener("click", () => {
        const tbody = table.querySelector("tbody");
        const index = Array.from(th.parentNode.children).indexOf(th);
        const asc = !th.classList.contains("asc");

        table.querySelectorAll("th.sortable").forEach((h) => h.classList.remove("asc", "desc"));
        th.classList.add(asc ? "asc" : "desc");

        Array.from(tbody.querySelectorAll("tr"))
          .sort((a, b) => {
            const av = a.children[index].innerText.trim();
            const bv = b.children[index].innerText.trim();
            const an = parseFloat(av.replace(/[^0-9.,-]/g, "").replace(",", "."));
            const bn = parseFloat(bv.replace(/[^0-9.,-]/g, "").replace(",", "."));
            const cmp = !isNaN(an) && !isNaN(bn) ? an - bn : av.localeCompare(bv, "ru");
            return asc ? cmp : -cmp;
          })
          .forEach((r) => tbody.appendChild(r));
      });
    });
  });

  document.querySelectorAll("[data-check-all]").forEach((master) => {
    master.addEventListener("change", () => {
      document.querySelectorAll(`[data-check="${master.dataset.checkAll}"]`).forEach((cb) => {
        cb.checked = master.checked;
        cb.closest("tr")?.classList.toggle("is-selected", cb.checked);
      });
    });
  });

  document.addEventListener("change", (e) => {
    const cb = e.target.closest("[data-check]");
    if (cb) cb.closest("tr")?.classList.toggle("is-selected", cb.checked);
  });
}

/** Селект-фильтр отправляет форму сам: лишняя кнопка «применить» ни к чему. */
function initAutoSubmit() {
  document.addEventListener("change", (e) => {
    const select = e.target.closest("[data-submit-on-change]");
    if (select) select.form?.submit();
  });
}

function toggleEmpty(name, show) {
  const empty = document.querySelector(`[data-empty="${name}"]`);
  if (empty) empty.hidden = !show;
}

/* ============================================================
   Уведомления и подтверждения
   ============================================================ */

const TOAST_ICON = { ok: "i-check-circle", error: "i-x-circle", warn: "i-alert-triangle", info: "i-info" };

/** showToast("Готово", { type: "ok", action: { label: "Отменить", onClick } }) */
function showToast(message, opts = {}) {
  let container = document.querySelector(".toast-container");
  if (!container) {
    container = document.createElement("div");
    container.className = "toast-container";
    document.body.appendChild(container);
  }

  const type = opts.type || "info";
  const toast = document.createElement("div");
  toast.className = "toast toast--" + type;
  toast.innerHTML = `
    <svg class="icon"><use href="#${opts.icon || TOAST_ICON[type]}"/></svg>
    <div class="toast__body">
      <div>${message}</div>
      ${opts.detail ? `<div class="toast__detail">${opts.detail}</div>` : ""}
    </div>
    ${opts.action ? `<button class="toast__action">${opts.action.label}</button>` : ""}
    <button class="toast__close" aria-label="Закрыть"><svg class="icon icon--sm"><use href="#i-x"/></svg></button>`;

  const remove = () => {
    toast.classList.add("is-leaving");
    toast.addEventListener("animationend", () => toast.remove(), { once: true });
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

  container.appendChild(toast);
  return toast;
}

/** Подтверждение действия. Модалка строится на лету — разметку держать негде. */
function confirmDialog({ title, text, confirmLabel = "Удалить", tone = "danger" }) {
  return new Promise((resolve) => {
    const backdrop = Render.node(`
      <div class="modal-backdrop is-open">
        <div class="modal modal--sm">
          <div class="modal__header">
            <span class="tone tone--${tone}" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
              <svg class="icon"><use href="#i-alert-triangle"/></svg>
            </span>
            <div class="modal__title">${title}</div>
          </div>
          <p class="modal__text">${text}</p>
          <div class="modal__footer">
            <button class="btn btn--ghost" data-no>Отмена</button>
            <button class="btn btn--${tone === "danger" ? "danger" : "dark"}" data-yes>${confirmLabel}</button>
          </div>
        </div>
      </div>`);

    const done = (value) => {
      backdrop.remove();
      resolve(value);
    };

    backdrop.querySelector("[data-yes]").addEventListener("click", () => done(true));
    backdrop.querySelector("[data-no]").addEventListener("click", () => done(false));
    backdrop.addEventListener("click", (e) => e.target === backdrop && done(false));
    document.addEventListener("keydown", function esc(e) {
      if (e.key === "Escape") { document.removeEventListener("keydown", esc); done(false); }
    });

    document.body.appendChild(backdrop);
    backdrop.querySelector("[data-yes]").focus();
  });
}

/* ============================================================
   Формы: submit → Api → обновление на месте
   ============================================================ */

const bucketId = () => document.body.dataset.bucketId || "";

function initForms() {
  document.querySelectorAll("form[data-api]").forEach((form) => {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const [method, template] = form.dataset.api.split(" ");
      const path = template
        .replace("{bucket}", bucketId())
        .replace("{slug}", form.dataset.slug || "")
        .replace("{name}", encodeURIComponent(form.dataset.name || ""));

      const button = form.querySelector('[type="submit"]');
      clearErrors(form);
      setBusy(button, true);

      try {
        const res = await Api[method.toLowerCase()](path, collect(form));
        closeModal(form);
        form.reset();
        refreshSelects(form);
        handleDone(form.dataset.done, res.data, form);
      } catch (err) {
        if (err.errors) {
          Object.keys(err.errors).forEach((name) => showFieldError(form, name, err.errors[name][0]));
          showToast("Проверьте поля формы", { type: "warn" });
        } else if (err.status === 409 && form.querySelector('[name="name"]')) {
          // Сервер отвечает «имя занято» без разбора по полям — кладём к имени сами.
          showFieldError(form, "name", "такое имя уже занято");
        } else {
          showToast(err.message || "Не получилось", { type: "error" });
        }
      } finally {
        setBusy(button, false);
      }
    });
  });
}

/**
 * Значения формы под то, что ждут DTO на сервере.
 *
 * Пустое число и пустой выбор — это `null` (поля объявлены как `?int` и
 * `?Enum`), а пустая строка так и остаётся строкой: у текстовых полей вроде
 * `description` в DTO стоит дефолт `''`, и `null` там не примут.
 */
function collect(form) {
  const out = {};

  form.querySelectorAll("input[name], select[name], textarea[name]").forEach((el) => {
    if (el.type === "radio" && !el.checked) return;
    if (el.type === "checkbox") { out[el.name] = el.checked; return; }

    // Пустое значение у переключателя — это «наследовать», то есть тот же null.
    const nullable = el.type === "number" || el.type === "radio" || el.tagName === "SELECT";
    out[el.name] = el.value === "" && nullable ? null : el.value;
  });

  // квота собирается из числа и единицы
  if (out.quota !== undefined) {
    out.quotaBytes = Number(out.quota) * Number(out.unit || 1048576);
    delete out.quota;
    delete out.unit;
  }

  return out;
}

function setBusy(button, busy) {
  if (!button) return;
  button.disabled = busy;
  button.dataset.label = button.dataset.label || button.textContent.trim();
  button.textContent = busy ? "…" : button.dataset.label;
}

function clearErrors(form) {
  form.querySelectorAll(".is-invalid").forEach((el) => el.classList.remove("is-invalid"));
  form.querySelectorAll(".field__error").forEach((el) => el.remove());
}

function showFieldError(form, name, message) {
  const input = form.querySelector(`[name="${name}"]`);
  if (!input) return;

  input.classList.add("is-invalid");
  const error = document.createElement("span");
  error.className = "field__error";
  error.textContent = message;
  input.closest(".field")?.appendChild(error);
  input.focus();
}

/* ============================================================
   Что делать с ответом
   ============================================================ */

function handleDone(kind, data, form) {
  switch (kind) {
    case "bucket:created": return onBucketCreated(data);
    case "bucket:updated": return onBucketUpdated(data, form);
    case "folder:created": return onFolderCreated(data);
    case "token:created": return onTokenCreated(data);
    case "link:created": return onLinkCreated(data, form);
    case "cache:saved": return onCacheSaved(data, form);
    case "file:updated": return onFileUpdated(data);
    case "folder:updated": return onFolderUpdated(data, form);
  }
}

function onBucketCreated(bucket) {
  const rows = document.querySelector('[data-rows="buckets"]');
  showToast(`Бакет <b>${Render.e(bucket.name)}</b> создаётся`, {
    type: "info",
    detail: "статус сменится сам",
  });

  if (!rows) {
    setTimeout(() => location.reload(), 900);
    return;
  }

  const row = Render.bucketRow(bucket);
  rows.prepend(row);
  toggleEmpty("buckets", false);
  bumpCounter("buckets-total", 1);
  bumpCounter("buckets-pending", 1);

  // Ответ пришёл 202: строка есть, каталога ещё нет. Спрашиваем статус, пока
  // provisioner не доложит ACTIVE — это его настоящая работа, не анимация.
  watchStatus(bucket.id, (status) => {
    row.querySelector('[data-cell="status"]').innerHTML = Render.statusCell(status);
    if (status === "ACTIVE") {
      bumpCounter("buckets-pending", -1);
      bumpCounter("buckets-active", 1);
      showToast(`Бакет <b>${Render.e(bucket.name)}</b> готов`, { type: "ok" });
      return true;
    }
    return false;
  });
}

/**
 * Опрашивает бакет, пока обработчик не скажет «хватит».
 * Исчезнувший бакет (404) — тоже ответ: так заканчивается удаление.
 */
async function watchStatus(id, onStatus, attempts = 12) {
  for (let i = 0; i < attempts; i++) {
    await new Promise((r) => setTimeout(r, 400 + i * 120));
    try {
      const res = await Api.get(`/admin/buckets/${id}`);
      if (onStatus(res.data.status.name)) return;
    } catch (err) {
      if (err.status === 404) {
        onStatus("GONE");
        return;
      }
    }
  }
}

/** Папка, созданная только что, должна сразу быть в списках выбора. */
function addFolderOption(name) {
  document.querySelectorAll("[data-upload-folder], #modal-file [name=folder]").forEach((select) => {
    if (Array.from(select.options).some((o) => o.value === name)) return;

    select.add(new Option(name, name));
    select.closest(".cselect--field")?.querySelector(".cselect__menu")?.insertAdjacentHTML(
      "beforeend",
      `<button type="button" class="cselect__option" data-value="${Render.e(name)}">
         ${Render.e(name)}<svg class="icon icon--check"><use href="#i-check"/></svg>
       </button>`,
    );
  });
}

function onFolderCreated(folder) {
  addFolderOption(folder.name);
  const list = document.querySelector('[data-rows="folders"]');
  if (list) {
    list.appendChild(Render.folderCard(folder));
    list.querySelector("p.text-muted")?.remove();
  }
  showToast(`Папка <b>${Render.e(folder.name)}</b> создана`, {
    type: "ok",
    detail: folder.public ? "публичная, файлы пойдут в /o" : "приватная, файлы только по токену",
  });
}

function onTokenCreated(data) {
  const token = data.accessToken;
  const full = token.access.name === "FULL";

  const rows = document.querySelector('[data-rows="tokens"]');
  if (rows) {
    rows.prepend(Render.tokenRow(token));
    toggleEmpty("tokens", false);
  }

  bumpCounter("tokens-active", 1);
  if (full) bumpCounter("tokens-full", 1);

  showSecret("Токен выпущен", data.token, null, tokenCheck(data.token, full));
}

function onLinkCreated(link, form) {
  link.file = form.dataset.fileName;

  const rows = document.querySelector('[data-rows="links"]');
  if (rows && link.id !== null) {
    rows.prepend(Render.linkRow(link));
    toggleEmpty("links", false);
  }

  // Карточка файла осталась открытой позади — обновим её список.
  const drawer = document.querySelector("#drawer-file.is-open");
  if (drawer && drawerRow) loadFileLinks(drawer, drawerRow.dataset.id);

  showSecret("Ссылка выпущена", link.url, "Показывается один раз — скопируйте сейчас.");
  showToast(link.id === null ? "Ссылка выпущена, в списке её не будет" : "Ссылка добавлена в список", {
    type: "info",
  });
}

function onCacheSaved(data, form) {
  const name = form.dataset.name;
  showToast(name ? `Кэш папки <b>${Render.e(name)}</b> сохранён` : "Кэш бакета сохранён", { type: "ok" });

  const card = name ? document.querySelector(`[data-row="folder"][data-name="${CSS.escape(name)}"]`) : null;
  if (!card) return;

  // Метку кэша ставим сразу: перезагружать страницу ради значка незачем.
  const cache = data.cache || {};
  card.dataset.maxAge = cache.maxAge ?? "";
  card.dataset.visibility = cache.visibility ? cache.visibility.name : "";

  card.querySelectorAll('[data-action="folder:cache"]').forEach((item) => {
    item.dataset.maxAge = card.dataset.maxAge;
    item.dataset.visibility = card.dataset.visibility;
  });

  const marks = Render.folderMarks({
    isPublic: card.dataset.public === "1",
    retention: card.dataset.retention,
    maxAge: card.dataset.maxAge,
    visibility: card.dataset.visibility,
  });
  card.querySelector(".fm__badges").innerHTML = Render.folderBadges(marks);
  card.querySelector(".fm__folder").title = Render.folderTitle(name, marks);
}

function showSecret(title, value, note, check) {
  const modal = openModal("modal-secret");
  if (!modal) return;

  modal.querySelector("[data-secret-title]").textContent = title;
  modal.querySelector("[data-secret-value]").textContent = value;
  modal.querySelector("[data-secret-copy]").dataset.copy = value;
  if (note) modal.querySelector(".modal__text").innerHTML = note;

  const box = modal.querySelector("[data-secret-check]");
  box.hidden = !check;
  if (check) {
    box.querySelector("[data-secret-curl]").textContent = check.curl;
    box.querySelector("[data-secret-curl-copy]").dataset.copy = check.curl;
    box.querySelector("[data-secret-check-hint]").textContent = check.hint;
  }
}

function tokenCheck(secret, full) {
  return {
    curl: `curl -H "Authorization: Bearer ${secret}" ${location.origin}/v1/check`,
    hint: full
      ? "Ответит бакетом, видом токена и остатком места. Этому токену открыт весь /v1."
      : "Ответит бакетом, видом токена и остатком места. Этот токен умеет только /p/<slug>.",
  };
}

function setCounter(name, value) {
  const el = document.querySelector(`[data-counter="${name}"]`);
  if (!el) return;

  const next = Math.max(0, value);
  el.textContent = next;
  el.classList.toggle("is-zero", next === 0);
}

function bumpCounter(name, delta) {
  const el = document.querySelector(`[data-counter="${name}"]`);
  if (el) setCounter(name, Number(el.textContent.replace(/\D/g, "")) + delta);
}

/* ============================================================
   Действия над строками
   ============================================================ */

function initActions() {
  document.addEventListener("click", async (e) => {
    const el = e.target.closest("[data-action]");
    if (!el) return;

    e.preventDefault();
    closeAllMenus();

    const { action, id, name } = el.dataset;
    const row = el.closest("[data-row]");

    try {
      switch (action) {
        case "bucket:delete": return await deleteBucket(row, el, id, name);
        case "bucket:edit": return openBucketModal(el.dataset);

        case "file:info": return openFileDrawer(row);
        case "file:rename": return openFileModal(el.dataset.fromDrawer !== undefined ? drawerRow : row, "rename");
        case "file:move": return openFileModal(el.dataset.fromDrawer !== undefined ? drawerRow : row, "move");
        case "file:delete": return await deleteFile(el, row);

        case "folder:edit": return openFolderModal(row);
        case "folder:delete": return await deleteFolder(el, row, name);

        case "token:delete": return await deleteToken(el, row, id, name);

        case "token:rotate": return await rotateToken(row, id, name);
        case "token:toggle": return await toggleToken(row, el, id);
        case "link:revoke": return await revokeLink(el.closest(".link-row") ?? row, id);
        case "links:revoke-all": return await revokeAllLinks();
        case "links:purge": return await purgeLinks(el.dataset.state);
        case "link:open": {
          // из строки, а не из кнопки: после переименования data-атрибуты
          // кнопок в меню остаются старыми, а строка обновляется
          const source = el.dataset.fromDrawer !== undefined ? drawerRow : row;
          return openLinkModal(source.dataset.id, source.dataset.name);
        }
        case "folder:cache": return openCacheModal(el.dataset, true);
        case "bucket:cache": return openCacheModal(el.dataset, false);
      }
    } catch (err) {
      showToast(err.message || "Не получилось", { type: "error" });
    }
  });
}

/** Убирает строку и включает пустое состояние, если список опустел. */
function dropRow(row, group) {
  row.remove();
  const rows = document.querySelector(`[data-rows="${group}"]`);
  if (rows && rows.children.length === 0) toggleEmpty(group, true);
}

async function deleteFile(el, row) {
  const name = row.dataset.name;
  const ok = await confirmDialog({
    title: "Удалить файл?",
    text: `<b>${Render.e(name)}</b> исчезнет из списка. Содержимое сотрётся с диска, только
           если на него не осталось ссылок — общий блоб переживёт удаление одного из своих файлов.`,
  });
  if (!ok) return;

  el.disabled = true;
  await Api.delete(`/admin/buckets/${bucketId()}/files/${row.dataset.id}`);

  closeModal(el);
  dropRow(row, "files");
  showToast(`Файл <b>${Render.e(name)}</b> удалён`, { type: "ok" });
}

async function deleteFolder(el, row, name) {
  const files = Number(el.dataset.files || row.dataset.files || 0);
  const ok = await confirmDialog({
    title: "Удалить папку?",
    text: files > 0
      ? `Вместе с папкой <b>${Render.e(name)}</b> удалятся ${files} ${plural(files, "файл", "файла", "файлов")} внутри.`
      : `Папка <b>${Render.e(name)}</b> пуста.`,
  });
  if (!ok) return;

  el.disabled = true;
  const res = await Api.delete(`/admin/buckets/${bucketId()}/folders/${encodeURIComponent(name)}`);

  dropRow(row, "folders");
  showToast(`Папка <b>${Render.e(name)}</b> удалена`, {
    type: "ok",
    detail: files > 0 ? `вместе с ${files} ${plural(files, "файлом", "файлами", "файлами")}` : null,
  });
}

async function deleteToken(el, row, id, name) {
  const ok = await confirmDialog({
    title: "Удалить токен?",
    text: `Клиент с этим токеном сразу начнёт получать 401. Восстановить нельзя —
           можно только выпустить новый.`,
  });
  if (!ok) return;

  el.disabled = true;
  await Api.delete(`/admin/buckets/${bucketId()}/tokens/${id}`);

  if (row.dataset.status === "ACTIVE") countToken(row, -1);
  else bumpCounter("tokens-inactive", -1);

  dropRow(row, "tokens");
  showToast(`Токен <b>${Render.e(name)}</b> удалён`, { type: "ok" });
}

/**
 * Удаление бакета необратимо и происходит фоном: строка сразу уходит в PENDING,
 * пропадает она только когда сервер перестанет её отдавать. Кнопки «отменить»
 * здесь нет намеренно — откатывать уже нечего.
 */
async function deleteBucket(row, el, id, name) {
  const ok = await confirmDialog({
    title: "Удалить бакет?",
    text: `Вместе с <b>${Render.e(name)}</b> уйдут все файлы, папки, токены и ссылки — каскадом.
           Каталог со всем содержимым сносится фоном, отменить будет нечем.`,
  });
  if (!ok) return;

  el.disabled = true;
  await Api.delete(`/admin/buckets/${id}`);

  const statusCell = row.querySelector('[data-cell="status"]');
  if (statusCell.textContent.includes("ACTIVE")) bumpCounter("buckets-active", -1);
  bumpCounter("buckets-pending", 1);

  row.style.opacity = ".55";
  statusCell.innerHTML = Render.statusCell("PENDING");
  showToast(`Бакет <b>${Render.e(name)}</b> удаляется`, { type: "info", detail: "удаляется в фоне" });

  watchStatus(id, (status) => {
    if (status !== "GONE") return false;
    row.remove();
    bumpCounter("buckets-pending", -1);
    bumpCounter("buckets-total", -1);
    if (document.querySelector('[data-rows="buckets"]')?.children.length === 0) toggleEmpty("buckets", true);
    showToast(`Бакет <b>${Render.e(name)}</b> удалён`, { type: "ok" });
    return true;
  });
}

/** Одна и та же форма создаёт и правит: меняются заголовок, метод и адрес. */
function openBucketModal(data) {
  const modal = openModal("modal-bucket");
  const form = modal.querySelector("form");
  const editing = !!data;

  clearErrors(form);
  modal.querySelector(".modal__title").textContent = editing ? "Изменить бакет" : "Новый бакет";
  modal.querySelector(".modal__text").hidden = editing;
  form.querySelector('[type="submit"]').textContent = editing ? "Сохранить" : "Создать";
  form.dataset.api = editing ? `PUT /admin/buckets/${data.id}` : "POST /admin/buckets";
  form.dataset.done = editing ? "bucket:updated" : "bucket:created";
  form.dataset.id = editing ? data.id : "";

  form.querySelector('[name="name"]').value = editing ? data.name : "";
  form.querySelector('[name="description"]').value = editing ? data.description : "";
  form.querySelector('[name="unit"]').value = "1048576";
  form.querySelector('[name="quota"]').value = editing ? Math.round(Number(data.quota) / 1048576) : 512;
  refreshSelects(form);
}

function onBucketUpdated(bucket, form) {
  const row = document.querySelector(`[data-row="bucket"][data-id="${CSS.escape(bucket.id)}"]`);
  if (row) {
    row.dataset.name = bucket.name;
    row.querySelector("[data-bucket-name]").textContent = bucket.name;
    row.querySelector(".fileline__meta").textContent = bucket.description;
    const [percent, state] = Render.quotaState(bucket.bytes.used, bucket.bytes.quota);
    const quota = row.querySelector(".quota");
    quota.className = "quota " + state;
    quota.querySelector(".quota__fill").style.width = percent + "%";
    quota.querySelector(".quota__meta").innerHTML =
      `<span><b>${Render.bytes(bucket.bytes.used)}</b> из ${Render.bytes(bucket.bytes.quota)}</span><span>${Math.round(percent)}%</span>`;
  }
  showToast(`Бакет <b>${Render.e(bucket.name)}</b> сохранён`, { type: "ok" });
}

async function rotateToken(row, id, name) {
  const ok = await confirmDialog({
    title: "Сменить значение токена?",
    text: `Старое значение перестанет работать сразу. Название <b>${Render.e(name)}</b>,
           вид доступа и срок останутся прежними.`,
    confirmLabel: "Сменить",
    tone: "brand",
  });
  if (!ok) return;

  const res = await Api.post(`/admin/buckets/${bucketId()}/tokens/${id}/rotate`);
  const token = res.data.accessToken;

  const tail = row?.querySelector(".fileline__meta");
  if (tail) tail.textContent = "s5w_…" + token.tail;

  showSecret(
    "Новое значение токена",
    res.data.token,
    "Старый уже недействителен. Значение показывается один раз.",
    tokenCheck(res.data.token, token.access.name === "FULL"),
  );
}

async function toggleToken(row, el, id) {
  const next = row.dataset.status === "ACTIVE" ? 0 : 1;
  const res = await Api.patch(`/admin/buckets/${bucketId()}/tokens/${id}/status`, { status: next });

  row.dataset.status = res.data.status.name;
  row.querySelector('[data-cell="status"]').innerHTML = res.data.expired
    ? '<span class="tone tone--danger">просрочен</span>'
    : next === 1
      ? '<span class="tone tone--ok"><span class="status-dot" style="background:currentColor"></span> активен</span>'
      : '<span class="tone tone--mute">выключен</span>';
  el.childNodes[0].nodeValue = next === 1 ? "Выключить " : "Включить ";
  row.style.opacity = next === 1 && !res.data.expired ? "" : ".55";

  countToken(row, next === 1 ? 1 : -1);
  bumpCounter("tokens-inactive", next === 1 ? -1 : 1);

  showToast(next === 1 ? "Токен включён" : "Токен выключен", { type: next === 1 ? "ok" : "warn" });
}

function countToken(row, delta) {
  if (row.dataset.expired === "1") {
    bumpCounter("tokens-expired", delta);
    return;
  }

  bumpCounter("tokens-active", delta);
  if (row.dataset.access === "FULL") bumpCounter("tokens-full", delta);
}

async function revokeLink(row, id) {
  if (!(await confirmDialog({ title: "Отозвать ссылку?", text: "Адрес сразу начнёт отвечать 404.", confirmLabel: "Отозвать" }))) return;

  await Api.delete(`/admin/buckets/${bucketId()}/links/${id}`);

  // Ссылку отзывают из двух мест: строки таблицы в разделе и карточки файла.
  if (row.classList.contains("link-row")) {
    row.classList.add("is-off");
    row.querySelector(".link-row__head .tone").outerHTML = '<span class="tone tone--danger">отозвана</span>';
  } else {
    row.dataset.revoked = "1";
    row.style.opacity = ".55";
    row.querySelector('[data-cell="expiry"]').innerHTML = '<span class="tone tone--danger">отозвана</span>';
  }
  row.querySelector('[data-action="link:revoke"]')?.remove();

  bumpCounter("links-active", -1);

  showToast("Ссылка отозвана", { type: "ok" });
}

/**
 * Уборка мёртвых строк. После неё меняются и счётчики, и постраничность,
 * поэтому проще перечитать страницу, чем чинить её по кусочкам.
 */
async function purgeLinks(state) {
  const about = {
    revoked: {
      title: "Удалить отозванные ссылки?",
      text: "Строки исчезнут из списка. Сами адреса и так уже ничего не открывают.",
    },
    expired: {
      title: "Удалить истёкшие ссылки?",
      text: "Строки с вышедшим сроком исчезнут из списка. Живые ссылки останутся.",
    },
  }[state];

  if (!(await confirmDialog({ ...about, confirmLabel: "Удалить" }))) return;

  const res = await Api.delete(`/admin/buckets/${bucketId()}/links/purge/${state}`);
  const removed = res.data.removed;

  showToast(
    removed === 0
      ? "Нечего убирать"
      : `Удалено ${removed} ${plural(removed, "ссылка", "ссылки", "ссылок")}`,
    { type: removed === 0 ? "info" : "ok" },
  );

  if (removed > 0) setTimeout(() => location.reload(), 700);
}

async function revokeAllLinks() {
  const ok = await confirmDialog({
    title: "Отозвать все ссылки?",
    text: "Перестанут работать все выданные ссылки бакета, включая те, которых нет в списке.",
    confirmLabel: "Отозвать все",
  });
  if (!ok) return;

  const res = await Api.delete(`/admin/buckets/${bucketId()}/links`);

  document.querySelectorAll('[data-row="link"]').forEach((row) => {
    row.dataset.revoked = "1";
    row.style.opacity = ".55";
    row.querySelector('[data-cell="expiry"]').innerHTML = '<span class="tone tone--danger">отозвана</span>';
    row.querySelector('[data-action="link:revoke"]')?.remove();
  });

  const epoch = document.querySelector('[data-counter="epoch"]');
  if (epoch) epoch.textContent = res.data.epoch;
  setCounter("links-active", 0);

  showToast("Все ссылки погашены", { type: "ok", detail: "все выданные адреса закрыты" });
}

/* ============================================================
   Карточка файла (drawer)
   ============================================================ */

/** Строка, которую открыли в карточке: из неё же действуют кнопки внизу. */
let drawerRow = null;

function openFileDrawer(row) {
  drawerRow = row;
  const modal = openModal("drawer-file");
  const d = row.dataset;
  const [kindClass, icon] = Render.kind(d.mime);

  const icon_ = modal.querySelector("[data-file-icon]");
  icon_.className = "ftype ftype--" + kindClass;
  icon_.innerHTML = `<svg class="icon"><use href="#${icon}"/></svg>`;

  modal.querySelector("[data-file-name]").textContent = d.name;
  modal.querySelector("[data-file-slug]").textContent = d.id;
  modal.querySelector("[data-file-size]").textContent = Render.bytes(d.size);
  modal.querySelector("[data-file-mime]").textContent = d.mime;
  modal.querySelector("[data-file-folder]").textContent = d.folder || "корень бакета";
  modal.querySelector("[data-file-created]").textContent = Render.date(d.created);
  modal.querySelector("[data-file-expires]").textContent = d.expires
    ? Render.left(d.expires) + " (" + Render.date(d.expires) + ")"
    : "бессрочно";

  const hash = modal.querySelector("[data-file-hash]");
  hash.textContent = d.hash;
  modal.querySelector("[data-file-hash-copy]").dataset.copy = d.hash;

  modal.querySelector("[data-file-badges]").innerHTML = [
    d.public
      ? '<span class="tone chan chan--o">/o</span><span class="text-sm text-muted">открыт всем, кто знает адрес</span>'
      : '<span class="tone chan chan--p">/p</span><span class="text-sm text-muted">только по токену бакета</span>',
  ].join(" ");

  modal.querySelector("[data-file-urls]").innerHTML = [
    d.publicUrl ? urlRow("Открытая", d.publicUrl, "o") : "",
    urlRow("По токену", d.privateUrl, "p"),
  ].join("");

  loadFileLinks(modal, d.id);
}

/** Временные ссылки этого файла — грузим при открытии карточки. */
async function loadFileLinks(modal, slug) {
  const box = modal.querySelector("[data-file-links]");
  box.innerHTML = '<span class="text-sm text-muted">загружаем…</span>';

  try {
    const res = await Api.get(`/admin/buckets/${bucketId()}/files/${slug}/links`);
    // Пока грузили, карточку могли закрыть или открыть другую.
    if (drawerRow?.dataset.id !== slug) return;

    const links = res.data || [];
    box.innerHTML = links.length
      ? links.map(fileLinkRow).join("")
      : '<span class="text-sm text-muted">Отзываемых ссылок нет</span>';
  } catch (err) {
    box.innerHTML = `<span class="text-sm text-warn">${Render.e(err.message || "не удалось получить список")}</span>`;
  }
}

function fileLinkRow(link) {
  const expired = new Date(link.expiresAt.replace(" ", "T")) <= new Date();
  const spent = link.maxDownloads !== null && link.downloads >= link.maxDownloads;
  const [tone, state] = link.revoked
    ? ["mute", "отозвана"]
    : expired
      ? ["mute", "истекла"]
      : spent
        ? ["mute", "лимит исчерпан"]
        : ["ok", Render.left(link.expiresAt)];

  const facts = [
    link.disposition.name === "ATTACHMENT" ? "скачиванием" : "открытием",
    link.maxDownloads === null
      ? `${link.downloads} ${plural(link.downloads, "скачивание", "скачивания", "скачиваний")}`
      : `скачали ${link.downloads} из ${link.maxDownloads}`,
  ];

  return `
    <div class="link-row" data-link-id="${link.id}">
      <span class="tone chan chan--t">/t</span>
      <span class="link-row__body">
        <span class="link-row__head">
          <span class="tone tone--${tone}">${Render.e(state)}</span>
          ${link.note ? `<span class="text-sm">${Render.e(link.note)}</span>` : ""}
        </span>
        <span class="text-sm text-muted">${facts.join(" · ")}</span>
      </span>
      ${
        link.revoked || expired
          ? ""
          : `<button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-copy="${Render.e(link.url)}"
                     aria-label="Копировать" title="Копировать адрес">
               <svg class="icon icon--sm"><use href="#i-copy"/></svg>
             </button>
             <a class="icon-btn icon-btn--ghost icon-btn--sm" href="${Render.e(link.url)}" target="_blank" rel="noopener"
                aria-label="Открыть" title="Открыть в новой вкладке">
               <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
             </a>
             <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-action="link:revoke"
                     data-id="${link.id}" aria-label="Отозвать" title="Отозвать ссылку">
               <svg class="icon icon--sm"><use href="#i-x-circle"/></svg>
             </button>`
      }
    </div>`;
}

function urlRow(label, url, channel) {
  return `
    <div class="url-row">
      <span class="tone chan chan--${channel}">/${channel}</span>
      <span class="url-row__value mono">${Render.e(url)}</span>
      <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-copy="${Render.e(url)}" aria-label="Копировать">
        <svg class="icon icon--sm"><use href="#i-copy"/></svg>
      </button>
      <a class="icon-btn icon-btn--ghost icon-btn--sm" href="${Render.e(url)}" target="_blank" rel="noopener" aria-label="Открыть">
        <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
      </a>
    </div>`;
}

/**
 * Одна форма на два действия: имя и папка меняются одной ручкой API, но
 * спрашиваем только то, за чем пришли — второе поле уходит как есть.
 */
function openFileModal(row, mode) {
  const modal = openModal("modal-file");
  const form = modal.querySelector("form");
  const renaming = mode === "rename";

  clearErrors(form);
  form.dataset.slug = row.dataset.id;
  form.querySelector('[name="name"]').value = row.dataset.name;
  form.querySelector('[name="folder"]').value = row.dataset.folder || "";
  refreshSelects(form);

  modal.querySelector("[data-file-form-title]").textContent = renaming ? "Переименовать" : "Переместить";
  modal.querySelector('[data-file-field="name"]').hidden = !renaming;
  modal.querySelector('[data-file-field="folder"]').hidden = renaming;
  form.querySelector('[type="submit"]').textContent = renaming ? "Переименовать" : "Переместить";

  const focus = renaming ? form.querySelector('[name="name"]') : null;
  if (focus) setTimeout(() => focus.select(), 80);
}

function onFileUpdated(file) {
  const row = document.querySelector(`[data-row="file"][data-id="${CSS.escape(file.id)}"]`);
  if (row) {
    row.dataset.name = file.name;
    row.dataset.folder = file.folder || "";
    row.dataset.public = file.public ? "1" : "";
    row.querySelector(".fileline__name").textContent = file.name;
  }
  showToast(`Файл <b>${Render.e(file.name)}</b> сохранён`, {
    type: "ok",
    detail: file.folder ? "лежит в папке " + file.folder : "лежит в корне бакета",
  });
}

/** Папка: та же форма создаёт и правит. */
function openFolderModal(row) {
  const modal = openModal("modal-folder");
  const form = modal.querySelector("form");
  const editing = row !== null && row !== undefined;

  clearErrors(form);
  modal.querySelector(".modal__title").textContent = editing ? "Изменить папку" : "Новая папка";
  form.querySelector('[type="submit"]').textContent = editing ? "Сохранить" : "Создать";
  form.dataset.api = editing
    ? `PUT /admin/buckets/{bucket}/folders/${encodeURIComponent(row.dataset.name)}`
    : "POST /admin/buckets/{bucket}/folders";
  form.dataset.done = editing ? "folder:updated" : "folder:created";
  form.dataset.name = editing ? row.dataset.name : "";

  form.querySelector('[name="name"]').value = editing ? row.dataset.name : "";
  form.querySelector('[name="public"]').checked = editing ? row.dataset.public === "1" : false;
  form.querySelector('[name="retention"]').value = editing ? row.dataset.retention : "0";
  refreshSelects(form);
}

function onFolderUpdated(folder, form) {
  showToast(`Папка <b>${Render.e(folder.name)}</b> сохранена`, { type: "ok" });
  setTimeout(() => location.reload(), 600);
}

function openLinkModal(slug, name) {
  const modal = openModal("modal-link");
  const form = modal.querySelector("form");
  form.dataset.slug = slug;
  form.dataset.fileName = name;
  modal.querySelector("[data-link-file]").textContent = name;

  // Долгая ссылка без строки в базе закрывается только вместе со всеми —
  // об этом лучше сказать до выпуска, а не после.
  const warn = () => {
    const stateful = form.elements.revocable.checked || form.elements.maxDownloads.value !== "";
    form.querySelector("[data-link-warn]").hidden = stateful || Number(form.elements.ttl.value) < 604800;
  };

  warn();
  if (!form.dataset.warnBound) {
    form.addEventListener("change", warn);
    form.addEventListener("input", warn);
    form.dataset.warnBound = "1";
  }
}

/**
 * Что получится в Cache-Control при таких настройках.
 * Повторяет CachePolicy::resolve — если правила там поменяются, править и здесь.
 */
function cacheHeader({ maxAge, visibility, filePublic, channel }) {
  // видимость: настройка, иначе выводится из флага файла
  let scope = visibility || (filePublic ? "SHARED" : "PRIVATE");
  if (scope === "NO_STORE") return "no-store";

  // приватный файл и каналы /p и /t публичными не бывают
  if (!filePublic || channel !== "o") scope = "PRIVATE";

  const age = maxAge !== null && maxAge !== "" ? Number(maxAge) : scope === "SHARED" ? CACHE_DEFAULT_AGE : 0;
  const word = scope === "SHARED" ? "public" : "private";

  if (age <= 0) return `${word}, max-age=0, must-revalidate`;
  return `${word}, max-age=${age}` + (scope === "SHARED" ? ", immutable" : "");
}

function renderCachePreview(form) {
  const box = form.querySelector("[data-cache-preview]");
  let maxAge = form.querySelector('[name="maxAge"]').value;
  // elements, а не querySelector: видимость выбирается группой переключателей.
  let visibility = { 1: "SHARED", 2: "PRIVATE", 3: "NO_STORE" }[form.elements.visibility.value] || null;
  const inherited = maxAge === "" && visibility === null;

  // Пустое поле у папки — это настройка бакета, а не дефолт сервиса: показываем то,
  // что клиент увидит на самом деле.
  if (form.dataset.level === "folder") {
    const body = document.body.dataset;
    if (visibility === null && body.cacheVisibility) visibility = body.cacheVisibility;
    if (maxAge === "" && body.cacheMaxAge) maxAge = body.cacheMaxAge;
  }

  const row = (channel, label, filePublic) => `
    <div class="cache-preview__row">
      <span class="tone chan chan--${channel}">/${channel}</span>
      <span class="cache-preview__body">
        <span class="cache-preview__value">${cacheHeader({ maxAge, visibility, filePublic, channel })}</span>
        <span class="cache-preview__note">${label}</span>
      </span>
    </div>`;

  const words = CACHE_WORDING[form.dataset.level] || CACHE_WORDING.folder;

  box.innerHTML =
    `<div class="cache-preview__note">${inherited
      ? words.empty
      : "Что уйдёт в заголовке при таких настройках:"}</div>` +
    row("o", "файл в публичной папке", true) +
    row("p", "по токену", false) +
    row("t", "по временной ссылке — но не дольше её самой", false);
}

const CACHE_WORDING = {
  folder: {
    intro: `Когда клиент скачал файл, копия остаётся у него в браузере, а по пути — ещё и у
            CDN, прокси и провайдера. Здесь решается, <b>кому</b> из них можно держать эту
            копию и <b>сколько</b>. Папка перекрывает бакет, бакет — дефолт сервиса.`,
    title: "Как в бакете",
    text: `Решает бакет, а если и там ничего не выбрано — сам сервис. Обычный выбор,
           пока у папки нет причин отличаться.`,
    empty: "Ничего не задано — берётся с уровня выше. Сейчас вышло бы так:",
  },
  bucket: {
    intro: `Когда клиент скачал файл, копия остаётся у него в браузере, а по пути — ещё и у
            CDN, прокси и провайдера. Здесь решается, <b>кому</b> из них можно держать эту
            копию и <b>сколько</b>. Бакет — верхний уровень: заданное здесь работает для всех
            папок, пока папка не решит иначе.`,
    empty: "Так отдаются файлы этого бакета:",
  },
};

/** Здравый срок под выбранный режим: подставляется при смене, devops перебьёт руками. */
const CACHE_DEFAULT_AGE = 86400;

const CACHE_TTL_SUGGEST = {
  bucket: { 1: CACHE_DEFAULT_AGE, 2: 3600, 3: null },
  folder: { "": null, 1: CACHE_DEFAULT_AGE, 2: 3600, 3: null },
};

const CACHE_TTL_HINT = {
  "": "Пусто — срок берётся с бакета.",
  1: "Пусто — сутки. Ноль — браузер перепроверяет каждый раз.",
  2: "Пусто — без кэша: браузер перепроверяет каждый раз.",
  3: "При этом режиме срок в заголовок не попадает.",
};

function openCacheModal(data, isFolder) {
  const modal = openModal("modal-cache");
  const form = modal.querySelector("form");
  const words = isFolder ? CACHE_WORDING.folder : CACHE_WORDING.bucket;

  clearErrors(form);
  form.dataset.name = isFolder ? data.name : "";
  form.dataset.level = isFolder ? "folder" : "bucket";
  form.dataset.api = isFolder
    ? "PATCH /admin/buckets/{bucket}/folders/{name}/cache"
    : `PATCH /admin/buckets/${data.id || bucketId()}/cache`;

  form.querySelector("[data-cache-intro]").innerHTML = words.intro;
  if (isFolder) {
    form.querySelector("[data-cache-auto-title]").textContent = words.title;
    form.querySelector("[data-cache-auto-text]").textContent = words.text;
  }

  // Наследовать бакету не от кого: «как уровнем выше» есть только у папки.
  form.querySelector('[data-cache-opt="auto"]').hidden = !isFolder;

  const ttl = form.querySelector('[name="maxAge"]');
  ttl.value = data.maxAge ?? "";
  ttl.dataset.suggested = "";
  form.elements.visibility.value = { SHARED: "1", PRIVATE: "2", NO_STORE: "3" }[data.visibility] || "";
  modal.querySelector("[data-cache-target]").textContent = isFolder ? "· папка " + data.name : "· весь бакет";

  syncCacheTtl(form);
  refreshSelects(form);
  renderCachePreview(form);
  if (!form.dataset.previewBound) {
    form.addEventListener("input", () => renderCachePreview(form));
    form.addEventListener("change", (e) => {
      if (e.target.name === "visibility") syncCacheTtl(form, true);
      renderCachePreview(form);
    });
    form.querySelectorAll("[data-cache-presets] [data-ttl]").forEach((button) => {
      button.addEventListener("click", () => {
        ttl.value = button.dataset.ttl;
        ttl.dataset.suggested = "";
        renderCachePreview(form);
      });
    });
    form.dataset.previewBound = "1";
  }
}

/** Срок, который действует у бакета: его показываем папке как «как в бакете (N)». */
function inheritedAge() {
  const body = document.body.dataset;
  if (body.cacheMaxAge) return body.cacheMaxAge;
  if (body.cacheVisibility === "NO_STORE") return null;
  if (body.cacheVisibility === "PRIVATE") return "0";
  return String(CACHE_DEFAULT_AGE);
}

/** Пустое поле не должно молчать: в подсказке — число, которое уйдёт клиенту. */
function ttlPlaceholder(level, value) {
  if (value === "3") return "—";
  if (value === "2") return "0 — без кэша";
  if (value === "1") return CACHE_DEFAULT_AGE + " — по умолчанию";

  const age = inheritedAge();
  return age === null ? "как в бакете" : `как в бакете (${age})`;
}

/**
 * Срок под выбранный режим: при смене режима подставляется здравое число, но только
 * если поле пустое или в нём стоит прошлая подсказка — руками введённое не затираем.
 * У режима «Никому» срока нет вовсе, поэтому поле гасится.
 */
function syncCacheTtl(form, switched = false) {
  const ttl = form.querySelector('[name="maxAge"]');
  const value = form.elements.visibility.value;
  const level = form.dataset.level === "bucket" ? "bucket" : "folder";
  const suggest = CACHE_TTL_SUGGEST[level][value === "" ? "" : Number(value)] ?? null;
  const off = value === "3";

  form.querySelector("[data-cache-ttl]").classList.toggle("is-off", off);
  ttl.disabled = off;
  ttl.placeholder = ttlPlaceholder(level, value);
  form.querySelectorAll("[data-cache-presets] [data-ttl]").forEach((b) => (b.disabled = off));
  form.querySelector("[data-cache-ttl-hint]").textContent = CACHE_TTL_HINT[value === "" ? "" : Number(value)];

  if (!switched) return;

  const untouched = ttl.value === "" || ttl.value === ttl.dataset.suggested;
  if (off) {
    ttl.value = "";
    ttl.dataset.suggested = "";
    return;
  }
  if (suggest !== null && untouched) {
    ttl.value = String(suggest);
    ttl.dataset.suggested = String(suggest);
  } else if (suggest === null && untouched) {
    ttl.value = "";
    ttl.dataset.suggested = "";
  }
}

/* ============================================================
   Загрузка файлов
   ============================================================ */

function initUpload() {
  const drop = document.querySelector("[data-upload-drop]");
  if (!drop) return;

  const input = drop.querySelector("input[type=file]");
  const list = document.querySelector("[data-upload-list]");
  const hint = drop.querySelector("[data-upload-hint]");
  const total = document.querySelector("[data-upload-total]");
  const startBtn = document.querySelector("[data-upload-start]");

  /** Очередь: файл + строка в списке. Загрузка стартует по кнопке. */
  let queue = [];

  const stop = (e) => { e.preventDefault(); e.stopPropagation(); };
  ["dragenter", "dragover"].forEach((ev) => drop.addEventListener(ev, (e) => { stop(e); drop.classList.add("is-drag"); }));
  ["dragleave", "drop"].forEach((ev) => drop.addEventListener(ev, (e) => { stop(e); drop.classList.remove("is-drag"); }));

  drop.addEventListener("drop", (e) => enqueue(e.dataTransfer.files));
  input.addEventListener("change", () => { enqueue(input.files); input.value = ""; });
  startBtn.addEventListener("click", start);

  function enqueue(files) {
    Array.from(files || []).forEach((file) => {
      const [kind, icon] = Render.kind(file.type);
      const image = isImage(file);

      const card = Render.node(`
        <div class="upload-item${image ? " upload-item--image" : ""}">
          <div class="row" style="justify-content:space-between; flex-wrap:nowrap">
            <div class="fileline">
              <div class="ftype ftype--${kind}"><svg class="icon"><use href="#${icon}"/></svg></div>
              <div class="fileline__body">
                <div class="fileline__name">${Render.e(file.name)}</div>
                <div class="fileline__meta">${Render.bytes(file.size)}<span class="dot-sep"></span><span data-state>в очереди</span></div>
              </div>
            </div>
            <div class="row" style="flex-wrap:nowrap; gap:8px">
              <span class="tone tone--mute" data-badge>ждёт</span>
              <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-drop-item aria-label="Убрать">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
              </button>
            </div>
          </div>
          <div class="progress mt-2"><div class="progress__bar" style="width:0"></div></div>
          ${image ? imageOptionsMarkup() : ""}
        </div>`);

      const item = { file, card, done: false, options: card.querySelector("[data-image-options]") };
      card.querySelector("[data-drop-item]").addEventListener("click", () => {
        queue = queue.filter((q) => q !== item);
        card.remove();
        refresh();
      });

      list.prepend(card);
      queue.push(item);
      if (item.options) initImageOptions(item.options, file.type, () => applyToAll(item));
    });

    refresh();
  }

  /** «Применить ко всем» — чтобы десять картинок не настраивать по десять раз. */
  function applyToAll(from) {
    const source = readImageOptions(from.options);

    queue
      .filter((q) => q.options && q !== from && !q.done)
      .forEach((q) => {
        writeImageOptions(q.options, source);
        refreshSelects(q.options);
      });

    showToast("Настройки разошлись по остальным картинкам", { type: "ok", timeout: 2000 });
  }

  function refresh() {
    const waiting = queue.filter((q) => !q.done);
    // Кнопка «ко всем» нужна, только когда есть кому раздавать.
    const images = waiting.filter((q) => q.options).length;
    queue.forEach((q) => q.options && (q.options.querySelector("[data-image-apply]").hidden = images < 2));

    hint.textContent = waiting.length ? "в очереди: " + waiting.length : "или нажмите, чтобы выбрать";
    total.textContent = waiting.length
      ? `${waiting.length} ${plural(waiting.length, "файл", "файла", "файлов")} · ${Render.bytes(
          waiting.reduce((sum, q) => sum + q.file.size, 0),
        )}`
      : "";
    startBtn.disabled = waiting.length === 0;
  }

  async function start() {
    const waiting = queue.filter((q) => !q.done);
    if (waiting.length === 0) return;

    startBtn.disabled = true;
    const folder = document.querySelector("[data-upload-folder]").value;

    for (const item of waiting) {
      // последовательно, а не пачкой: у бакета одна квота, и по одному
      // понятнее, на каком файле она кончилась
      await send(item, folder, item.options ? readImageOptions(item.options) : {});
    }

    refresh();
  }

  function send(item, folder, options) {
    return new Promise((resolve) => {
      const bar = item.card.querySelector(".progress__bar");
      const badge = item.card.querySelector("[data-badge]");
      const state = item.card.querySelector("[data-state]");
      const form = new FormData();

      form.append("file", item.file);
      if (folder) form.append("folder", folder);
      Object.entries(options).forEach(([key, value]) => value !== null && form.append(key, value));

      state.textContent = "загрузка";
      badge.className = "tone tone--brand";

      const xhr = new XMLHttpRequest();
      xhr.open("POST", `/admin/buckets/${bucketId()}/files`);

      // прогресс отдаёт сам XHR — у fetch его нет
      xhr.upload.addEventListener("progress", (e) => {
        if (!e.lengthComputable) return;
        const percent = Math.round((e.loaded / e.total) * 100);
        bar.style.width = percent + "%";
        badge.textContent = percent + "%";
      });

      xhr.addEventListener("load", () => {
        item.done = true;
        const body = parse(xhr.responseText);

        if (xhr.status >= 200 && xhr.status < 300) {
          bar.style.width = "100%";
          badge.className = "tone tone--ok";
          badge.textContent = "готово";
          state.textContent = describe(body);
          addFileRow(body);
          setTimeout(() => { item.card.remove(); refresh(); }, 2500);
        } else {
          bar.style.width = "100%";
          bar.style.background = "var(--danger)";
          badge.className = "tone tone--danger";
          badge.textContent = String(xhr.status);
          state.textContent = body?.message || "не загрузился";
          showToast(`<b>${Render.e(item.file.name)}</b> не загрузился`, { type: "error", detail: body?.message });
        }
        resolve();
      });

      xhr.addEventListener("error", () => {
        item.done = true;
        badge.className = "tone tone--danger";
        badge.textContent = "сеть";
        state.textContent = "соединение оборвалось";
        resolve();
      });

      xhr.send(form);
    });
  }

  function parse(text) {
    try {
      return text ? JSON.parse(text) : null;
    } catch (e) {
      return null;
    }
  }

  /** Что случилось с файлом — словами, а не кодом. */
  function describe(file) {
    if (file.processed?.applied) return file.processed.operations.join(", ");
    if (file.deduplicated) return "дедупликация — такой файл уже есть";
    return "без обработки";
  }

  function addFileRow(file) {
    const rows = document.querySelector('[data-rows="files"]');
    if (!rows) return;

    rows.prepend(Render.fileRow(file));
    toggleEmpty("files", false);
    showToast(`<b>${Render.e(file.name)}</b> загружен`, {
      type: "ok",
      detail: file.processed?.applied
        ? `${Render.bytes(file.processed.source.size)} → ${Render.bytes(file.processed.result.size)}`
        : null,
    });
  }
}

function plural(count, one, few, many) {
  const mod100 = count % 100;
  if (mod100 >= 11 && mod100 <= 14) return many;
  return [many, one, few, few, few, many, many, many, many, many][count % 10];
}

/* ============================================================
   Обработка картинок — на карточке самой картинки
   Настройки нужны не «вообще при загрузке», а конкретному файлу: у одного
   свой формат, другой трогать не надо. Поэтому панель живёт в очереди
   рядом с превью и появляется только там, где сервис умеет что-то сделать.
   ============================================================ */

/** Форматы, которые сервис умеет пережимать (см. ImageProcessor::SUPPORTED). */
const IMAGE_MIMES = ["image/jpeg", "image/png", "image/gif", "image/webp", "image/avif"];

const isImage = (file) => IMAGE_MIMES.includes(file.type);

/** Качество по умолчанию у сервиса — ImageFormat::defaultQuality(). */
const FORMAT_QUALITY = { AVIF: 55, PNG: 60, WEBP: 82, JPEG: 82 };

const FORMAT_ABOUT = {
  WEBP: "жмёт заметно лучше jpeg и умеет прозрачность — обычный выбор для веба",
  JPEG: "открывается везде, но прозрачности не знает",
  PNG: "без потерь и с прозрачностью — для графики и скриншотов, фото станет тяжелее",
  AVIF: "жмёт сильнее всех, но кодируется дольше и старые браузеры его не покажут",
};

const MIME_FORMAT = {
  "image/webp": "WEBP",
  "image/jpeg": "JPEG",
  "image/png": "PNG",
  "image/avif": "AVIF",
  "image/gif": "GIF",
};

/** Чем выбор навредит именно этому файлу. null — всё в порядке. */
function formatWarning(target, sourceMime) {
  if (MIME_FORMAT[sourceMime] === target) {
    return "Файл уже в этом формате — перекодировать нечего.";
  }
  if (target === "JPEG" && sourceMime !== "image/jpeg") {
    return "У jpeg нет прозрачности: прозрачные места зальются белым.";
  }
  if (target === "PNG" && sourceMime === "image/jpeg") {
    return "Фотография в png тяжелее исходника — сервис тогда оставит оригинал.";
  }
  return null;
}

function imageOptionsMarkup() {
  const option = (key, title, hint, body) => `
    <div class="opt" data-opt="${key}">
      <label class="switch">
        <input type="checkbox" data-opt-toggle>
        <span class="switch__track"></span>
        <span class="opt__label">
          <span class="opt__title">${title}</span>
          <span class="opt__hint">${hint}</span>
        </span>
      </label>
      <div class="opt__body" hidden>${body}</div>
    </div>`;

  return `
    <div class="fold fold--inline mt-2" data-image-options>
      <button type="button" class="fold__head" data-image-toggle aria-expanded="false">
        <svg class="icon icon--sm"><use href="#i-crop"/></svg>
        <span class="fold__title">Обработка</span>
        <span class="fold__note" data-image-note></span>
        <svg class="icon icon--sm fold__chev"><use href="#i-chevron-down"/></svg>
      </button>

      <div class="fold__body" hidden>
        <div class="stack">
          ${option(
            "resize",
            "Уменьшить размер",
            "вписать в рамку, пропорции сохранятся",
            `<div class="row" style="flex-wrap:nowrap; gap:10px">
               <div class="field w-full">
                 <label class="field__label">Ширина до, px</label>
                 <input class="input" type="number" name="maxWidth" placeholder="не ограничивать">
               </div>
               <div class="field w-full">
                 <label class="field__label">Высота до, px</label>
                 <input class="input" type="number" name="maxHeight" placeholder="не ограничивать">
               </div>
             </div>
             <span class="field__hint">Увеличивать не будем: что меньше рамки — останется как есть.</span>`,
          )}

          ${option(
            "quality",
            "Задать качество",
            "иначе сервис подберёт сам",
            `<label class="field__label">Качество · <b data-quality-value>82</b></label>
             <input class="range" type="range" name="quality" min="1" max="100" value="82">
             <span class="field__hint" data-quality-hint></span>`,
          )}

          ${option(
            "format",
            "Сменить формат",
            "перекодировать в другой контейнер",
            `<select class="select-native" name="format">
               <option value="WEBP">WEBP</option>
               <option value="JPEG">JPEG</option>
               <option value="PNG">PNG</option>
               <option value="AVIF">AVIF</option>
             </select>
             <span class="field__hint" data-format-about></span>
             <span class="field__hint text-warn" data-format-warn hidden></span>`,
          )}
        </div>

        <div class="row mt-2" style="gap:10px">
          <span class="text-sm text-muted" data-image-summary style="flex:1; min-width:0"></span>
          <button type="button" class="btn btn--ghost btn--sm" data-image-apply hidden>
            <svg class="icon icon--sm"><use href="#i-copy"/></svg> Ко всем картинкам
          </button>
        </div>
      </div>
    </div>`;
}

const optionOn = (box, key) => box.querySelector(`[data-opt="${key}"] [data-opt-toggle]`).checked;

/**
 * Выключенная настройка — это null, а не значение поля: сервис решит сам.
 * Так «качество 82» в форме не означает молча включённое сжатие.
 */
function readImageOptions(box) {
  const value = (name) => box.querySelector(`[name=${name}]`).value;

  return {
    format: optionOn(box, "format") ? value("format") : "ORIGINAL",
    quality: optionOn(box, "quality") ? value("quality") : null,
    maxWidth: optionOn(box, "resize") ? value("maxWidth") || null : null,
    maxHeight: optionOn(box, "resize") ? value("maxHeight") || null : null,
  };
}

function writeImageOptions(box, options) {
  const setOption = (key, on) => {
    const input = box.querySelector(`[data-opt="${key}"] [data-opt-toggle]`);
    input.checked = on;
    box.querySelector(`[data-opt="${key}"] .opt__body`).hidden = !on;
  };

  setOption("resize", options.maxWidth !== null || options.maxHeight !== null);
  setOption("quality", options.quality !== null);
  setOption("format", options.format !== "ORIGINAL");

  box.querySelector("[name=maxWidth]").value = options.maxWidth ?? "";
  box.querySelector("[name=maxHeight]").value = options.maxHeight ?? "";
  if (options.quality !== null) box.querySelector("[name=quality]").value = options.quality;
  if (options.format !== "ORIGINAL") box.querySelector("[name=format]").value = options.format;

  box.dispatchEvent(new Event("change", { bubbles: false }));
}

/** Что настройки сделают с файлом — словами. */
function describeImageOptions(o) {
  const parts = [];
  if (o.format !== "ORIGINAL") parts.push("в " + o.format.toLowerCase());
  if (o.maxWidth || o.maxHeight) parts.push(`до ${o.maxWidth || "∞"}×${o.maxHeight || "∞"}`);
  if (o.quality) parts.push("качество " + o.quality);
  return parts;
}

/** @param {() => void} onApplyAll — раздать эти же настройки остальным картинкам. */
function initImageOptions(box, sourceMime, onApplyAll) {
  const toggle = box.querySelector("[data-image-toggle]");
  const body = box.querySelector(".fold__body");
  const note = box.querySelector("[data-image-note]");
  const out = box.querySelector("[data-image-summary]");
  const quality = box.querySelector("[name=quality]");

  enhanceSelects(box);
  enhanceNumbers(box);

  toggle.addEventListener("click", () => {
    const open = toggle.getAttribute("aria-expanded") !== "true";
    toggle.setAttribute("aria-expanded", String(open));
    box.classList.toggle("is-open", open);
    body.hidden = !open;
  });

  box.querySelectorAll("[data-opt-toggle]").forEach((input) => {
    input.addEventListener("change", () => {
      input.closest(".opt").querySelector(".opt__body").hidden = !input.checked;
    });
  });

  box.querySelector("[data-image-apply]").addEventListener("click", onApplyAll);

  const update = () => {
    const o = readImageOptions(box);
    const parts = describeImageOptions(o);
    const target = o.format === "ORIGINAL" ? MIME_FORMAT[sourceMime] : o.format;

    // Пока качество не тронуто, ползунок стоит там, откуда сервис начнёт для
    // выбранного формата: у avif своя шкала, 82 у него — уже не «как webp».
    const preset = FORMAT_QUALITY[target] ?? 82;
    if (!optionOn(box, "quality")) quality.value = preset;
    box.querySelector("[data-quality-value]").textContent = quality.value;
    box.querySelector("[data-quality-hint]").textContent =
      `Меньше — легче файл и заметнее артефакты. Без этой настройки сервис берёт ${preset}.`;

    box.querySelector("[data-format-about]").textContent =
      o.format === "ORIGINAL" ? "" : FORMAT_ABOUT[o.format];

    const warn = box.querySelector("[data-format-warn]");
    const text = o.format === "ORIGINAL" ? null : formatWarning(o.format, sourceMime);
    warn.hidden = text === null;
    warn.textContent = text ?? "";

    box.classList.toggle("is-set", parts.length > 0);
    note.textContent = parts.length ? parts.join(" · ") : "как есть";
    out.textContent = parts.length
      ? "Квота считается по результату обработки."
      : "Файл ляжет байт в байт.";
  };

  box.addEventListener("input", update);
  box.addEventListener("change", update);
  update();
}

/* ============================================================
   Копирование
   ============================================================ */

function initCopy() {
  document.addEventListener("click", (e) => {
    const el = e.target.closest("[data-copy]");
    if (!el) return;

    const value = el.dataset.copy;
    const done = () => showToast("Скопировано", { type: "ok", timeout: 1800 });

    if (navigator.clipboard) {
      navigator.clipboard.writeText(value).then(done, done);
    } else {
      const ta = document.createElement("textarea");
      ta.value = value;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      ta.remove();
      done();
    }
  });
}
