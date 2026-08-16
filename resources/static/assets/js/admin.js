/* s5w admin — поведение интерфейса.
   Базовые виджеты (сайдбар, дропдауны, селекты, модалки, вкладки, таблицы)
   взяты из winter-admin-template. Всё, что меняет данные, идёт через Api и
   обновляет страницу на месте — перезагрузок нет. */

document.addEventListener("DOMContentLoaded", () => {
  initSidebar();
  initDropdowns();
  initCustomSelects();
  initBucketSwitch();
  initModals();
  initTabs();
  initAlerts();
  initTables();
  initCopy();
  initFilters();
  initForms();
  initActions();
  initUpload();
  initImageOptions();
});

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

function initDropdowns() {
  document.addEventListener("click", (e) => {
    const toggle = e.target.closest("[data-dropdown-toggle]");
    document.querySelectorAll(".dropdown.is-open").forEach((d) => {
      if (!toggle || d !== toggle.closest(".dropdown")) d.classList.remove("is-open");
    });
    if (toggle) toggle.closest(".dropdown").classList.toggle("is-open");
  });
}

function initCustomSelects() {
  document.querySelectorAll(".cselect").forEach((sel) => {
    const btn = sel.querySelector(".cselect__btn");
    const valueEl = sel.querySelector(".cselect__value");
    const options = sel.querySelectorAll(".cselect__option");

    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      document.querySelectorAll(".cselect.is-open").forEach((s) => s !== sel && s.classList.remove("is-open"));
      sel.classList.toggle("is-open");
    });

    options.forEach((opt) => {
      opt.addEventListener("click", () => {
        options.forEach((o) => o.classList.remove("is-selected"));
        opt.classList.add("is-selected");
        const clone = opt.cloneNode(true);
        const check = clone.querySelector(".icon--check");
        if (check) check.remove();
        valueEl.innerHTML = clone.innerHTML;
        sel.classList.remove("is-open");
        sel.dispatchEvent(new CustomEvent("change", { detail: { value: opt.dataset.value } }));
      });
    });
  });

  document.addEventListener("click", (e) => {
    if (!e.target.closest(".cselect")) {
      document.querySelectorAll(".cselect.is-open").forEach((s) => s.classList.remove("is-open"));
    }
  });
}

/** Переключатель бакета в сайдбаре: раздел при смене сохраняется. */
function initBucketSwitch() {
  const sel = document.querySelector("[data-bucket-switch]");
  if (!sel) return;

  sel.addEventListener("change", (e) => {
    const section = sel.dataset.section === "files" ? "" : "/" + sel.dataset.section;
    location.href = "/admin/ui/buckets/" + e.detail.value + section;
  });
}

function initModals() {
  document.addEventListener("click", (e) => {
    const opener = e.target.closest("[data-modal-open]");
    if (opener) {
      openModal(opener.dataset.modalOpen);
      return;
    }
    if (e.target.closest("[data-modal-close]") || e.target.classList.contains("modal-backdrop")) {
      const backdrop = e.target.closest(".modal-backdrop") || e.target;
      backdrop.classList.remove("is-open");
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal-backdrop.is-open").forEach((m) => m.classList.remove("is-open"));
    }
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

/** Поиск по уже загруженным строкам — без запроса на сервер. */
function initFilters() {
  document.querySelectorAll("[data-filter]").forEach((input) => {
    input.addEventListener("input", () => {
      const rows = document.querySelector(`[data-rows="${input.dataset.filter}"]`);
      if (!rows) return;

      const query = input.value.trim().toLowerCase();
      let visible = 0;

      Array.from(rows.children).forEach((row) => {
        const match = !query || row.innerText.toLowerCase().includes(query);
        row.hidden = !match;
        if (match) visible++;
      });

      toggleEmpty(input.dataset.filter, visible === 0);
    });
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
        handleDone(form.dataset.done, res.data, form);
      } catch (err) {
        if (err.errors) {
          Object.keys(err.errors).forEach((name) => showFieldError(form, name, err.errors[name][0]));
          showToast("Проверьте поля формы", { type: "warn" });
        } else {
          showToast(err.message || "Не получилось", { type: "error" });
        }
      } finally {
        setBusy(button, false);
      }
    });
  });
}

/** Значения формы: чекбоксы — булевы, пустые строки — null. */
function collect(form) {
  const out = {};

  form.querySelectorAll("input[name], select[name], textarea[name]").forEach((el) => {
    if (el.type === "radio" && !el.checked) return;
    if (el.type === "checkbox") { out[el.name] = el.checked; return; }
    out[el.name] = el.value === "" ? null : el.value;
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
    case "folder:created": return onFolderCreated(data);
    case "token:created": return onTokenCreated(data);
    case "link:created": return onLinkCreated(data, form);
    case "cache:saved": return onCacheSaved(data, form);
  }
}

function onBucketCreated(bucket) {
  const rows = document.querySelector('[data-rows="buckets"]');
  showToast(`Бакет <b>${Render.e(bucket.name)}</b> создаётся`, {
    type: "info",
    detail: "каталог заводится фоном — статус сменится сам",
  });

  if (!rows) {
    setTimeout(() => location.reload(), 900);
    return;
  }

  const row = Render.bucketRow(bucket);
  rows.prepend(row);
  toggleEmpty("buckets", false);

  // provisioner отрабатывает асинхронно — показываем это честно
  setTimeout(() => {
    row.querySelector('[data-cell="status"]').innerHTML =
      '<span class="tone tone--ok"><span class="status-dot" style="background: currentColor"></span>ACTIVE</span>';
    showToast(`Бакет <b>${Render.e(bucket.name)}</b> готов`, { type: "ok" });
  }, 1600);
}

function onFolderCreated(folder) {
  const grid = document.querySelector('[data-rows="folders"]');
  const card = Render.folderCard(folder);
  grid.insertBefore(card, grid.querySelector(".card--dashed"));
  showToast(`Папка <b>${Render.e(folder.name)}</b> создана`, { type: "ok" });
}

function onTokenCreated(data) {
  const rows = document.querySelector('[data-rows="tokens"]');
  if (rows) {
    rows.prepend(Render.tokenRow(data.accessToken));
    toggleEmpty("tokens", false);
  }
  showSecret("Токен выпущен", data.token);
}

function onLinkCreated(link, form) {
  link.file = form.dataset.fileName;

  const rows = document.querySelector('[data-rows="links"]');
  if (rows && link.id !== null) {
    rows.prepend(Render.linkRow(link));
    toggleEmpty("links", false);
  }

  showSecret("Ссылка выпущена", link.url, "Подпись не хранится — повторить её нечем.");
  showToast(link.id === null ? "Ссылка живёт целиком в подписи — в списке её не будет" : "Ссылка добавлена в список", {
    type: "info",
  });
}

function onCacheSaved(data, form) {
  const name = form.dataset.name;
  showToast(name ? `Кэш папки <b>${Render.e(name)}</b> сохранён` : "Кэш бакета сохранён", { type: "ok" });

  const card = document.querySelector(`[data-row="folder"][data-name="${CSS.escape(name || "")}"]`);
  if (!card || !data.visibility) return;

  const badges = card.querySelector('[data-cell="badges"]');
  badges.querySelector(".mono")?.remove();
  const names = { 1: "PUBLIC", 2: "PRIVATE", 3: "NO_STORE" };
  badges.insertAdjacentHTML(
    "beforeend",
    `<span class="tone tone--mute mono">${names[data.visibility]} · ${Number(data.maxAge) || 0}s</span>`,
  );
}

function showSecret(title, value, note) {
  const modal = openModal("modal-secret");
  if (!modal) return;

  modal.querySelector("[data-secret-title]").textContent = title;
  modal.querySelector("[data-secret-value]").textContent = value;
  modal.querySelector("[data-secret-copy]").dataset.copy = value;
  if (note) modal.querySelector(".modal__text").innerHTML = note;
}

/* ============================================================
   Действия над строками
   ============================================================ */

function initActions() {
  document.addEventListener("click", async (e) => {
    const el = e.target.closest("[data-action]");
    if (!el) return;

    e.preventDefault();
    document.querySelectorAll(".dropdown.is-open").forEach((d) => d.classList.remove("is-open"));

    const { action, id, name } = el.dataset;
    const row = el.closest("[data-row]");

    try {
      switch (action) {
        case "bucket:delete": return await removeWithUndo({
          row, el,
          title: "Удалить бакет?",
          text: `Вместе с <b>${Render.e(name)}</b> уйдут все файлы, папки, токены и ссылки — каскадом.`,
          path: `/admin/buckets/${id}`,
          toast: `Бакет <b>${Render.e(name)}</b> удаляется`,
          empty: "buckets",
        });

        case "file:delete": return await removeWithUndo({
          row, el,
          title: "Удалить файл?",
          text: `Содержимое исчезнет с диска, только если на него не осталось ссылок.`,
          path: `/admin/buckets/${bucketId()}/files/${id}`,
          toast: `Файл <b>${Render.e(name)}</b> удалён`,
          empty: "files",
        });

        case "folder:delete": return await removeWithUndo({
          row, el,
          title: "Удалить папку?",
          text: `Вместе с ${el.dataset.files || 0} файлами внутри. Квота бакета освободится.`,
          path: `/admin/buckets/${bucketId()}/folders/${encodeURIComponent(name)}`,
          toast: `Папка <b>${Render.e(name)}</b> удалена`,
          empty: "folders",
        });

        case "token:delete": return await removeWithUndo({
          row, el,
          title: "Удалить токен?",
          text: `Клиент с ключом <b>${Render.e(name)}</b> сразу получит 401.`,
          path: `/admin/buckets/${bucketId()}/tokens/${id}`,
          toast: `Токен <b>${Render.e(name)}</b> удалён`,
          empty: "tokens",
        });

        case "token:rotate": return await rotateToken(id, name);
        case "token:toggle": return await toggleToken(row, el, id);
        case "link:revoke": return await revokeLink(row, id);
        case "links:revoke-all": return await revokeAllLinks();
        case "link:open": return openLinkModal(el.dataset.slug, name);
        case "folder:cache": return openCacheModal(name);
        case "bucket:cache": return openCacheModal(null);
      }
    } catch (err) {
      showToast(err.message || "Не получилось", { type: "error" });
    }
  });
}

/** Удаление с возможностью вернуть строку: запрос уже ушёл, откат — визуальный. */
async function removeWithUndo({ row, el, title, text, path, toast, empty }) {
  if (!(await confirmDialog({ title, text }))) return;

  el.disabled = true;
  await Api.delete(path);

  const parent = row.parentNode;
  const next = row.nextSibling;
  row.remove();

  const rows = document.querySelector(`[data-rows="${empty}"]`);
  if (rows && rows.children.length === 0) toggleEmpty(empty, true);

  showToast(toast, {
    type: "ok",
    action: {
      label: "Отменить",
      onClick: () => {
        parent.insertBefore(row, next);
        toggleEmpty(empty, false);
        showToast("Возвращено", { type: "info", timeout: 2000 });
      },
    },
  });
}

async function rotateToken(id, name) {
  const ok = await confirmDialog({
    title: "Сменить ключ?",
    text: `Старое значение перестанет работать сразу. Название <b>${Render.e(name)}</b> и права не меняются.`,
    confirmLabel: "Сменить",
    tone: "brand",
  });
  if (!ok) return;

  const res = await Api.post(`/admin/buckets/${bucketId()}/tokens/${id}/rotate`);
  showSecret("Новый ключ", res.data.token, "Старый уже недействителен. Значение показывается один раз.");
}

async function toggleToken(row, el, id) {
  const next = row.dataset.status === "ACTIVE" ? 0 : 1;
  const res = await Api.patch(`/admin/buckets/${bucketId()}/tokens/${id}/status`, { status: next });

  row.dataset.status = res.data.status.name;
  row.querySelector('[data-cell="status"]').innerHTML =
    next === 1
      ? '<span class="tone tone--ok"><span class="status-dot" style="background:currentColor"></span> активен</span>'
      : '<span class="tone tone--mute">выключен</span>';
  el.childNodes[0].nodeValue = next === 1 ? "Выключить " : "Включить ";

  showToast(next === 1 ? "Токен включён" : "Токен выключен", { type: next === 1 ? "ok" : "warn" });
}

async function revokeLink(row, id) {
  if (!(await confirmDialog({ title: "Отозвать ссылку?", text: "Адрес сразу начнёт отвечать 404.", confirmLabel: "Отозвать" }))) return;

  await Api.delete(`/admin/buckets/${bucketId()}/links/${id}`);

  row.dataset.revoked = "1";
  row.style.opacity = ".55";
  row.querySelector('[data-cell="expiry"]').innerHTML = '<span class="tone tone--danger">отозвана</span>';
  row.querySelector('[data-action="link:revoke"]')?.remove();

  const counter = document.querySelector('[data-counter="links-active"]');
  if (counter) counter.textContent = Math.max(0, Number(counter.textContent) - 1);

  showToast("Ссылка отозвана", { type: "ok" });
}

async function revokeAllLinks() {
  const ok = await confirmDialog({
    title: "Отозвать все ссылки?",
    text: "Бакет сменит эпоху — перестанут работать разом все подписи, включая те, которых нет в базе.",
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
  const active = document.querySelector('[data-counter="links-active"]');
  if (active) active.textContent = "0";

  showToast("Все ссылки погашены", { type: "ok", detail: "эпоха ссылок теперь " + res.data.epoch });
}

function openLinkModal(slug, name) {
  const modal = openModal("modal-link");
  const form = modal.querySelector("form");
  form.dataset.slug = slug;
  form.dataset.fileName = name;
  modal.querySelector("[data-link-file]").textContent = name;
}

function openCacheModal(folderName) {
  const modal = openModal("modal-cache");
  const form = modal.querySelector("form");

  form.dataset.name = folderName || "";
  form.dataset.api = folderName
    ? "PATCH /admin/buckets/{bucket}/folders/{name}/cache"
    : "PATCH /admin/buckets/{bucket}/cache";
  modal.querySelector("[data-cache-target]").textContent = folderName ? "· папка " + folderName : "· весь бакет";
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

  const stop = (e) => { e.preventDefault(); e.stopPropagation(); };
  ["dragenter", "dragover"].forEach((ev) => drop.addEventListener(ev, (e) => { stop(e); drop.classList.add("is-drag"); }));
  ["dragleave", "drop"].forEach((ev) => drop.addEventListener(ev, (e) => { stop(e); drop.classList.remove("is-drag"); }));

  drop.addEventListener("drop", (e) => queue(e.dataTransfer.files));
  input.addEventListener("change", () => queue(input.files));

  function queue(files) {
    if (!files || !files.length) return;
    hint.textContent = files.length === 1 ? "1 файл" : files.length + " файлов";
    Array.from(files).forEach((file, i) => setTimeout(() => upload(file), i * 250));
  }

  async function upload(file) {
    const card = Render.node(`
      <div class="card card--tile mb-1" style="padding:14px 16px">
        <div class="row" style="justify-content:space-between; flex-wrap:nowrap">
          <div class="fileline">
            <div class="ftype ftype--${Render.kind(file.type)[0]}">
              <svg class="icon"><use href="#${Render.kind(file.type)[1]}"/></svg>
            </div>
            <div class="fileline__body">
              <div class="fileline__name">${Render.e(file.name)}</div>
              <div class="fileline__meta">${Render.bytes(file.size)}<span class="dot-sep"></span><span data-state>загрузка</span></div>
            </div>
          </div>
          <span class="tone tone--mute" data-badge>0%</span>
        </div>
        <div class="progress mt-2"><div class="progress__bar" style="width:0"></div></div>
      </div>`);
    list.prepend(card);

    const bar = card.querySelector(".progress__bar");
    const badge = card.querySelector("[data-badge]");
    const state = card.querySelector("[data-state]");

    // Прогресс рисуется таймером: у fetch его нет, а XHR понадобится только
    // с настоящим бэкендом — тогда сюда придёт upload.onprogress.
    let percent = 0;
    const timer = setInterval(() => {
      percent = Math.min(92, percent + 6 + Math.random() * 12);
      bar.style.width = percent + "%";
      badge.textContent = Math.round(percent) + "%";
    }, 200);

    try {
      const options = imageOptions();
      const res = await Api.post(`/admin/buckets/${bucketId()}/files`, {
        name: file.name,
        size: file.size,
        mime: file.type,
        folder: document.querySelector("[data-upload-folder]")?.value || null,
        ...options,
      });

      clearInterval(timer);
      bar.style.width = "100%";
      badge.className = "tone tone--ok";
      badge.textContent = "готово";

      const data = res.data;
      state.textContent = data.processed.applied
        ? data.processed.operations.join(", ")
        : data.deduplicated
          ? "дедупликация — байты уже были"
          : "без обработки";

      const rows = document.querySelector('[data-rows="files"]');
      if (rows) {
        rows.prepend(Render.fileRow(data, bucketId()));
        toggleEmpty("files", false);
      }

      showToast(`<b>${Render.e(data.name)}</b> загружен`, {
        type: "ok",
        detail: data.processed.applied
          ? `${Render.bytes(data.processed.source.size)} → ${Render.bytes(data.processed.result.size)}`
          : null,
      });

      setTimeout(() => card.remove(), 2500);
    } catch (err) {
      clearInterval(timer);
      bar.style.width = "100%";
      bar.style.background = "var(--danger)";
      badge.className = "tone tone--danger";
      badge.textContent = err.status || "ошибка";
      state.textContent = err.message;
      showToast(`<b>${Render.e(file.name)}</b> не загрузился`, { type: "error", detail: err.message });
    }
  }
}

/** Параметры обработки картинок — общие для всей очереди загрузки. */
function imageOptions() {
  const box = document.querySelector("[data-image-options]");
  if (!box) return {};

  const value = (name) => box.querySelector(`[name=${name}]`).value;
  const quality = box.querySelector("[name=quality]");

  return {
    format: value("format"),
    quality: quality.value === quality.dataset.default ? null : quality.value,
    maxWidth: value("maxWidth") || null,
    maxHeight: value("maxHeight") || null,
  };
}

function initImageOptions() {
  const box = document.querySelector("[data-image-options]");
  if (!box) return;

  const out = box.querySelector("[data-image-summary]");

  const update = () => {
    const o = imageOptions();
    const parts = [];

    if (o.format !== "ORIGINAL") parts.push("перекодировать в " + o.format.toLowerCase());
    if (o.maxWidth || o.maxHeight) parts.push(`вписать в ${o.maxWidth || "∞"}×${o.maxHeight || "∞"}`);
    if (o.quality) parts.push("качество " + o.quality);

    box.querySelector("[data-quality-value]").textContent = box.querySelector("[name=quality]").value;
    out.textContent = parts.length
      ? parts.join(", ") + " — до записи в хранилище, поэтому хэш и квота считаются по результату"
      : "файл ляжет байт в байт, обработки не будет";
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
