/* Файлы: карточка, переименование, перенос, удаление, временные ссылки. */

import { Api } from "../api.js";
import { Render } from "../render.js";
import { bucketId, e, plural } from "../ui/dom.js";
import * as actions from "../ui/actions.js";
import * as forms from "../ui/forms.js";
import { Modal } from "../ui/modal.js";
import { refreshSelects } from "../ui/select.js";
import * as toast from "../ui/toast.js";
import { dropRow } from "../ui/counter.js";

/** Строка, которую открыли в карточке: из неё же действуют кнопки внизу. */
let drawerRow = null;

export const currentDrawerRow = () => drawerRow;

const sourceRow = ({ row, fromDrawer }) => (fromDrawer ? drawerRow : row);

function urlRow(label, url, channel) {
  return `
    <div class="url-row">
      <span class="tone chan chan--${channel}">/${channel}</span>
      <span class="url-row__value mono" title="${e(label)}">${e(url)}</span>
      <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-copy="${e(url)}" aria-label="Копировать">
        <svg class="icon icon--sm"><use href="#i-copy"/></svg>
      </button>
      <a class="icon-btn icon-btn--ghost icon-btn--sm" href="${e(url)}" target="_blank" rel="noopener" aria-label="Открыть">
        <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
      </a>
    </div>`;
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
          <span class="tone tone--${tone}">${e(state)}</span>
          ${link.note ? `<span class="text-sm">${e(link.note)}</span>` : ""}
        </span>
        <span class="text-sm text-muted">${facts.join(" · ")}</span>
      </span>
      ${
        link.revoked || expired
          ? ""
          : `<span class="link-row__actions">
             <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-copy="${e(link.url)}"
                     aria-label="Копировать" title="Копировать адрес">
               <svg class="icon icon--sm"><use href="#i-copy"/></svg>
             </button>
             <a class="icon-btn icon-btn--ghost icon-btn--sm" href="${e(link.url)}" target="_blank" rel="noopener"
                aria-label="Открыть" title="Открыть в новой вкладке">
               <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
             </a>
             <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-action="link:revoke"
                     data-id="${link.id}" aria-label="Отозвать" title="Отозвать ссылку">
               <svg class="icon icon--sm"><use href="#i-x-circle"/></svg>
             </button>
           </span>`
      }
    </div>`;
}

/** Временные ссылки этого файла — грузим при открытии карточки. */
export async function loadFileLinks(dialog, slug) {
  const box = dialog.querySelector("[data-file-links]");
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
    box.innerHTML = `<span class="text-sm text-warn">${e(err.message || "не удалось получить список")}</span>`;
  }
}

function openDrawer(row, trigger) {
  drawerRow = row;
  const dialog = Modal.open("drawer-file", { trigger });
  if (!dialog) return;
  const d = row.dataset;
  const [kindClass, icon] = Render.kind(d.mime);

  const badge = dialog.querySelector("[data-file-icon]");
  badge.className = "ftype ftype--" + kindClass;
  badge.innerHTML = `<svg class="icon"><use href="#${icon}"/></svg>`;

  dialog.querySelector("[data-file-name]").textContent = d.name;
  dialog.querySelector("[data-file-slug]").textContent = d.id;
  dialog.querySelector("[data-file-size]").textContent = Render.bytes(d.size);
  dialog.querySelector("[data-file-mime]").textContent = d.mime;
  dialog.querySelector("[data-file-folder]").textContent = d.folder || "корень бакета";
  dialog.querySelector("[data-file-created]").textContent = Render.date(d.created);
  dialog.querySelector("[data-file-expires]").textContent = d.expires
    ? Render.left(d.expires) + " (" + Render.date(d.expires) + ")"
    : "бессрочно";

  dialog.querySelector("[data-file-hash]").textContent = d.hash;
  dialog.querySelector("[data-file-hash-copy]").dataset.copy = d.hash;

  dialog.querySelector("[data-file-badges]").innerHTML = d.public
    ? '<span class="tone chan chan--o">/o</span><span class="text-sm text-muted">открыт всем, кто знает адрес</span>'
    : '<span class="tone chan chan--p">/p</span><span class="text-sm text-muted">только по токену бакета</span>';

  dialog.querySelector("[data-file-urls]").innerHTML = [
    d.publicUrl ? urlRow("Открытая", d.publicUrl, "o") : "",
    urlRow("По токену", d.privateUrl, "p"),
  ].join("");

  loadFileLinks(dialog, d.id);
}

/**
 * Одна форма на два действия: имя и папка меняются одной ручкой API, но
 * спрашиваем только то, за чем пришли — второе поле уходит как есть.
 */
function prepareFileForm(dialog, { row, mode }) {
  const form = dialog.querySelector("form");
  const renaming = mode === "rename";

  forms.clearErrors(form);
  form.dataset.slug = row.dataset.id;
  form.querySelector('[name="name"]').value = row.dataset.name;
  form.querySelector('[name="folder"]').value = row.dataset.folder || "";
  refreshSelects(form);

  dialog.querySelector("[data-file-form-title]").textContent = renaming ? "Переименовать" : "Переместить";
  dialog.querySelector('[data-file-field="name"]').hidden = !renaming;
  dialog.querySelector('[data-file-field="folder"]').hidden = renaming;
  form.querySelector('[type="submit"]').textContent = renaming ? "Переименовать" : "Переместить";

  if (renaming) setTimeout(() => form.querySelector('[name="name"]').select(), 80);
}

function onUpdated(file) {
  const row = document.querySelector(`[data-row="file"][data-id="${CSS.escape(file.id)}"]`);
  if (row) {
    row.dataset.name = file.name;
    row.dataset.folder = file.folder || "";
    row.dataset.public = file.public ? "1" : "";
    row.querySelector(".fileline__name").textContent = file.name;
  }
  toast.show(`Файл <b>${e(file.name)}</b> сохранён`, {
    type: "ok",
    detail: file.folder ? "лежит в папке " + e(file.folder) : "лежит в корне бакета",
  });
}

async function remove(ctx) {
  const row = sourceRow(ctx);
  if (!row) return;
  const name = row.dataset.name;
  const ok = await Modal.confirm({
    title: "Удалить файл?",
    text: `<b>${e(name)}</b> исчезнет из списка. Содержимое сотрётся с диска, только
           если на него не осталось ссылок — общий блоб переживёт удаление одного из своих файлов.`,
  });
  if (!ok) return;

  ctx.el.disabled = true;
  try {
    await Api.delete(`/admin/buckets/${bucketId()}/files/${row.dataset.id}`);
  } finally {
    ctx.el.disabled = false;
  }

  if (ctx.fromDrawer) Modal.close("drawer-file");
  dropRow(row, "files");
  toast.show(`Файл <b>${e(name)}</b> удалён`, { type: "ok" });
}

function prepareLinkForm(dialog, { slug, name }) {
  const form = dialog.querySelector("form");
  form.dataset.slug = slug;
  form.dataset.fileName = name;
  dialog.querySelector("[data-link-file]").textContent = name;

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

export function init() {
  Modal.onOpen("modal-file", prepareFileForm);
  Modal.onOpen("modal-link", prepareLinkForm);
  forms.onDone("file:updated", onUpdated);

  actions.register("file:info", ({ row, el }) => row && openDrawer(row, el));
  actions.register("file:rename", (ctx) => {
    const row = sourceRow(ctx);
    if (row) Modal.open("modal-file", { trigger: ctx.el, context: { row, mode: "rename" } });
  });
  actions.register("file:move", (ctx) => {
    const row = sourceRow(ctx);
    if (row) Modal.open("modal-file", { trigger: ctx.el, context: { row, mode: "move" } });
  });
  actions.register("file:delete", remove);
  actions.register("link:open", (ctx) => {
    // из строки, а не из кнопки: после переименования data-атрибуты
    // кнопок в меню остаются старыми, а строка обновляется
    const row = sourceRow(ctx);
    if (row) Modal.open("modal-link", { trigger: ctx.el, context: { slug: row.dataset.id, name: row.dataset.name } });
  });
}
