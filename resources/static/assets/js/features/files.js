/* Файлы: карточка, переименование, перенос, удаление, временные ссылки. */

import { Api } from "../api.js";
import { Render } from "../render.js";
import { bucketId, e } from "../ui/dom.js";
import { t, plural } from "../i18n/index.js";
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
      <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-copy="${e(url)}" data-i18n-attr="aria-label:modal.secret.copy" aria-label="${e(t("modal.secret.copy"))}">
        <svg class="icon icon--sm"><use href="#i-copy"/></svg>
      </button>
      <a class="icon-btn icon-btn--ghost icon-btn--sm" href="${e(url)}" target="_blank" rel="noopener" data-i18n-attr="aria-label:common.open" aria-label="${e(t("common.open"))}">
        <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
      </a>
    </div>`;
}

function fileLinkRow(link) {
  const expired = new Date(link.expiresAt.replace(" ", "T")) <= new Date();
  const spent = link.maxDownloads !== null && link.downloads >= link.maxDownloads;
  const [tone, state] = link.revoked
    ? ["mute", t("link.dead.revoked")]
    : expired
      ? ["mute", t("link.dead.expired")]
      : spent
        ? ["mute", t("link.dead.limit")]
        : ["ok", Render.left(link.expiresAt)];

  const facts = [
    t(link.disposition.name === "ATTACHMENT" ? "link.byDownload" : "link.byOpening"),
    link.maxDownloads === null
      ? plural("link.downloads", link.downloads)
      : t("link.downloadedOf", { done: link.downloads, max: link.maxDownloads }),
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
                     data-i18n-attr="aria-label:modal.secret.copy;title:link.copy" aria-label="${e(t("modal.secret.copy"))}" title="${e(t("link.copy"))}">
               <svg class="icon icon--sm"><use href="#i-copy"/></svg>
             </button>
             <a class="icon-btn icon-btn--ghost icon-btn--sm" href="${e(link.url)}" target="_blank" rel="noopener"
                data-i18n-attr="aria-label:common.open;title:link.openNew" aria-label="${e(t("common.open"))}" title="${e(t("link.openNew"))}">
               <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
             </a>
             <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-action="link:revoke"
                     data-id="${link.id}" data-i18n-attr="aria-label:link.revoke;title:link.revokeOne" aria-label="${e(t("link.revoke"))}" title="${e(t("link.revokeOne"))}">
               <svg class="icon icon--sm"><use href="#i-x-circle"/></svg>
             </button>
           </span>`
      }
    </div>`;
}

/** Временные ссылки этого файла — грузим при открытии карточки. */
export async function loadFileLinks(dialog, slug) {
  const box = dialog.querySelector("[data-file-links]");
  box.innerHTML = `<span class="text-sm text-muted">${t("file.loading")}</span>`;

  try {
    const res = await Api.get(`/admin/buckets/${bucketId()}/files/${slug}/links`);
    // Пока грузили, карточку могли закрыть или открыть другую.
    if (drawerRow?.dataset.id !== slug) return;

    const links = res.data || [];
    box.innerHTML = links.length
      ? links.map(fileLinkRow).join("")
      : `<span class="text-sm text-muted">${t("file.noLinks")}</span>`;
  } catch (err) {
    box.innerHTML = `<span class="text-sm text-warn">${e(err.message || t("file.listFailed"))}</span>`;
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
  const sizeCell = dialog.querySelector("[data-file-size]");
  sizeCell.dataset.fmt = "bytes";
  sizeCell.dataset.value = d.size;
  sizeCell.textContent = Render.bytes(d.size);
  dialog.querySelector("[data-file-mime]").textContent = d.mime;
  const folderCell = dialog.querySelector("[data-file-folder]");
  if (d.folder) {
    delete folderCell.dataset.i18n;
    folderCell.textContent = d.folder;
  } else {
    folderCell.dataset.i18n = "files.bucketRoot";
    folderCell.textContent = t("files.bucketRoot");
  }
  dialog.querySelector("[data-file-created]").textContent = Render.date(d.created);
  const expiresCell = dialog.querySelector("[data-file-expires]");
  if (d.expires) {
    delete expiresCell.dataset.i18n;
    expiresCell.textContent = Render.left(d.expires) + " (" + Render.date(d.expires) + ")";
  } else {
    expiresCell.dataset.i18n = "time.forever";
    expiresCell.textContent = t("time.forever");
  }

  dialog.querySelector("[data-file-hash]").textContent = d.hash;
  dialog.querySelector("[data-file-hash-copy]").dataset.copy = d.hash;

  dialog.querySelector("[data-file-badges]").innerHTML = d.public
    ? `<span class="tone chan chan--o">/o</span><span class="text-sm text-muted" data-i18n="file.openToAll">${t("file.openToAll")}</span>`
    : `<span class="tone chan chan--p">/p</span><span class="text-sm text-muted" data-i18n="file.tokenOnly">${t("file.tokenOnly")}</span>`;

  dialog.querySelector("[data-file-urls]").innerHTML = [
    d.publicUrl ? urlRow(t("file.urlPublic"), d.publicUrl, "o") : "",
    urlRow(t("file.urlToken"), d.privateUrl, "p"),
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

  dialog.querySelector("[data-file-form-title]").textContent = t(renaming ? "file.rename" : "file.move");
  dialog.querySelector('[data-file-field="name"]').hidden = !renaming;
  dialog.querySelector('[data-file-field="folder"]').hidden = renaming;
  form.querySelector('[type="submit"]').textContent = t(renaming ? "file.rename" : "file.move");

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
  toast.show(t("file.saved", { name: "<b>" + e(file.name) + "</b>" }), {
    type: "ok",
    detail: file.folder ? t("file.inFolder", { folder: e(file.folder) }) : t("file.inRoot"),
  });
}

async function remove(ctx) {
  const row = sourceRow(ctx);
  if (!row) return;
  const name = row.dataset.name;
  const ok = await Modal.confirm({
    title: t("file.deleteTitle"),
    text: t("file.deleteText", { name: "<b>" + e(name) + "</b>" }),
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
  toast.show(t("file.deleted", { name: "<b>" + e(name) + "</b>" }), { type: "ok" });
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
