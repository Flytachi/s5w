/* Разметка строк и карточек на клиенте — повторяет вьюхи из resources/views/admin.
   Нужна там, где запись появляется без перезагрузки: сервер её ещё не рисовал.
   Ячейки помечены так же, как на сервере: data-primary — главная, data-label —
   подпись для карточного вида на телефоне, data-actions — меню действий. */

import { e, node, rowNode } from "./ui/dom.js";
import { t, bytes, num, left } from "./i18n/index.js";

export { bytes, num, left };

const vars = (obj) => e(JSON.stringify(obj));

export const date = (iso) => {
  const d = new Date(iso);
  const p = (x) => String(x).padStart(2, "0");
  return `${p(d.getDate())}.${p(d.getMonth() + 1)}.${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
};

export const kind = (mime) => {
  mime = mime || "";
  if (mime.startsWith("image/")) return ["image", "i-image"];
  if (mime.startsWith("video/")) return ["video", "i-film"];
  if (mime.startsWith("audio/")) return ["audio", "i-music"];
  if (/zip|gzip|tar/.test(mime)) return ["arch", "i-archive"];
  return ["doc", "i-file"];
};

export const quotaState = (used, quota) => {
  const percent = quota > 0 ? Math.min(100, (used / quota) * 100) : 0;
  return [percent, percent >= 90 ? "is-danger" : percent >= 70 ? "is-warn" : ""];
};

const icon = (name, cls = "icon") => `<svg class="${cls}"><use href="#${name}"/></svg>`;

const label = (key) => `data-i18n="${key}"`;

const cell = (key) => `data-label="${e(t(key))}" data-i18n-attr="data-label:${key}"`;

const item = (key, name) => `<span ${label(key)}>${e(t(key))}</span> ${icon(name)}`;

const fmt = (name, value) => `data-fmt="${name}" data-value="${e(value)}"`;

const menuButton = () => `
  <button type="button" class="icon-btn icon-btn--ghost" data-dropdown-toggle
          data-i18n-attr="aria-label:common.actions" aria-label="${e(t("common.actions"))}" aria-haspopup="menu">
    ${icon("i-more-h")}
  </button>`;

/** Ячейка статуса — её перерисовывают, пока каталог заводится или сносится. */
export function statusCell(name) {
  const tone = name === "ACTIVE" ? "ok" : name === "CREATED" || name === "PENDING" ? "warn" : "mute";
  return `<span class="tone tone--${tone}"><span class="status-dot status-dot--current"></span>`
    + `<span data-i18n="status.${e(name)}">${e(t("status." + name))}</span></span>`;
}

/* ---------------- Бакет ---------------- */

export function bucketRow(b) {
  const [percent, state] = quotaState(b.bytes.used, b.bytes.quota);

  return rowNode(`
    <tr data-row="bucket" data-id="${e(b.id)}" data-name="${e(b.name)}">
      <td data-primary>
        <a class="fileline" href="/admin/ui/buckets/${e(b.id)}">
          <span class="ftype ftype--image">${icon("i-database")}</span>
          <span class="fileline__body">
            <span class="fileline__name" data-bucket-name>${e(b.name)}</span>
            <span class="fileline__meta">${e(b.description)}</span>
          </span>
        </a>
      </td>
      <td ${cell("table.status")} data-half data-cell="status">${statusCell(b.status.name)}</td>
      <td ${cell("table.quota")} class="col-quota">
        <div class="quota ${state}">
          <div class="quota__bar"><div class="quota__fill" style="width: ${percent}%"></div></div>
          <div class="quota__meta">
            <span><b ${fmt("bytes", b.bytes.used)}>${bytes(b.bytes.used)}</b>
              <span ${label("quota.of")}>${e(t("quota.of"))}</span>
              <span ${fmt("bytes", b.bytes.quota)}>${bytes(b.bytes.quota)}</span></span>
            <span>${Math.round(percent)}%</span>
          </div>
        </div>
      </td>
      <td ${cell("table.filesBlobs")} data-half class="num nowrap">0 <span class="text-muted">/ 0</span></td>
      <td ${cell("table.cache")} data-half><span class="tone tone--mute" ${label("cache.default")}>${e(t("cache.default"))}</span></td>
      <td ${cell("table.created")} class="text-muted text-sm nowrap">${date(b.createdAt)}</td>
      <td data-actions>
        <div class="dropdown">
          ${menuButton()}
          <div class="dropdown__menu" role="menu">
            <a class="dropdown__item" href="/admin/ui/buckets/${e(b.id)}">${item("common.open", "i-arrow-right")}</a>
            <button class="dropdown__item" data-action="bucket:edit" data-id="${e(b.id)}"
                    data-name="${e(b.name)}" data-description="${e(b.description)}" data-quota="${b.bytes.quota}">
              ${item("common.edit", "i-edit")}
            </button>
            <button class="dropdown__item" data-action="bucket:cache" data-id="${e(b.id)}" data-max-age="" data-visibility="">
              ${item("bucket.cachePolicy", "i-clock")}
            </button>
            <button class="dropdown__item" data-action="bucket:delete" data-id="${e(b.id)}" data-name="${e(b.name)}">
              ${item("common.delete", "i-trash")}
            </button>
          </div>
        </div>
      </td>
    </tr>`);
}

/* ---------------- Папка ---------------- */

const RETENTION = {
  0: "retention.NONE", 1: "retention.DAY", 2: "retention.WEEK", 3: "retention.MONTH",
  4: "retention.QUARTER", 5: "retention.HALF_YEAR", 6: "retention.YEAR",
};

const VISIBILITY = {
  PUBLIC: "cache.visibility.SHARED",
  SHARED: "cache.visibility.SHARED",
  PRIVATE: "cache.visibility.PRIVATE",
  NO_STORE: "cache.visibility.NO_STORE",
};

/**
 * Метки папки: закрыта, со сроком хранения, со своим кэшем.
 * Повторяет $marks из admin/bucket-files.php — правки нужны в обоих местах.
 */
export function folderMarks({ isPublic, retention, maxAge, visibility }) {
  const marks = [];
  if (!isPublic) marks.push(["i-lock", "brand", "folder.mark.private", {}]);
  if (Number(retention) > 0) {
    marks.push(["i-clock", "warn", "folder.mark.retention", { value: "@" + RETENTION[retention] }]);
  }

  const has = (v) => v !== null && v !== undefined && v !== "";
  if (has(maxAge) || has(visibility)) {
    const markVars = {};
    if (has(visibility)) markVars.vis = "@" + (VISIBILITY[visibility] || visibility);
    if (has(maxAge)) markVars.age = Number(maxAge) + " s";
    const key = has(visibility) && has(maxAge)
      ? "folder.mark.cacheBoth"
      : has(visibility) ? "folder.mark.cacheVis" : "folder.mark.cacheAge";
    marks.push(["i-zap", "temp", key, markVars]);
  }
  return marks;
}

export const markHint = ([, , key, markVars]) => t(key, markVars);

export const folderBadges = (marks) =>
  marks
    .map(([name, tone, key, markVars]) =>
      `<svg class="icon icon--sm text-${tone}" role="img" data-i18n-attr="aria-label:${key}"`
      + ` data-i18n-vars="${vars(markVars)}" aria-label="${e(t(key, markVars))}"><use href="#${name}"/></svg>`)
    .join("");

// Без экранирования: значение уходит в свойство title, а не в разметку.
export const folderTitle = (name, marks) =>
  marks.length === 0 ? name : name + " — " + marks.map(markHint).join(", ");

/** Папка в списке менеджера. */
export function folderCard(f) {
  const marks = folderMarks({ isPublic: f.public, retention: f.retention.id, maxAge: null, visibility: null });

  return node(`
    <div class="fm__folder-wrap" data-row="folder" data-name="${e(f.name)}" data-public="${f.public ? "1" : ""}"
         data-retention="${f.retention.id}" data-files="0" data-max-age="" data-visibility="">
      <a class="fm__folder" href="?folder=${encodeURIComponent(f.name)}" title="${e(folderTitle(f.name, marks))}">
        ${icon("i-folder")}
        <span class="fm__folder-name" data-folder-name>${e(f.name)}</span>
        <span class="fm__badges">${folderBadges(marks)}</span>
        <span class="fm__count">0</span>
      </a>
      <div class="dropdown fm__folder-menu">
        <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle
                data-i18n-attr="aria-label:common.actions" aria-label="${e(t("common.actions"))}" aria-haspopup="menu">
          ${icon("i-more-v", "icon icon--sm")}
        </button>
        <div class="dropdown__menu" role="menu">
          <button class="dropdown__item" data-action="folder:edit" data-name="${e(f.name)}">
            ${item("common.edit", "i-edit")}
          </button>
          <button class="dropdown__item" data-action="folder:cache" data-name="${e(f.name)}" data-max-age="" data-visibility="">
            ${item("bucket.cachePolicy", "i-clock")}
          </button>
          <button class="dropdown__item" data-action="folder:delete" data-name="${e(f.name)}" data-files="0">
            ${item("common.delete", "i-trash")}
          </button>
        </div>
      </div>
    </div>`);
}

/* ---------------- Файл ---------------- */

export function fileRow(f) {
  const [k, ic] = kind(f.content.mime);
  const channel = f.public ? "o" : "p";

  return rowNode(`
    <tr data-row="file" data-id="${e(f.id)}" data-name="${e(f.name)}" data-mime="${e(f.content.mime)}"
        data-size="${f.content.size}" data-hash="${e(f.content.hash)}" data-folder="${e(f.folder || "")}"
        data-public="${f.public ? "1" : ""}" data-created="${e(f.createdAt)}" data-expires="${e(f.expiresAt || "")}"
        data-private-url="${e(f.privateUrl)}" data-public-url="${e(f.publicUrl || "")}">
      <td data-primary>
        <button type="button" class="fileline fileline--button" data-action="file:info">
          <span class="ftype ftype--${k}">${icon(ic)}</span>
          <span class="fileline__body">
            <span class="fileline__name">${e(f.name)}</span>
            <span class="fileline__meta"><span ${fmt("bytes", f.content.size)}>${bytes(f.content.size)}</span><span class="dot-sep"></span>${e(f.content.mime)}</span>
          </span>
        </button>
      </td>
      <td ${cell("table.channel")} data-half><span class="tone chan chan--${channel}">/${channel}</span></td>
      <td ${cell("table.uploaded")} data-half class="text-sm nowrap"><span class="text-muted" ${label("time.now")}>${e(t("time.now"))}</span></td>
      <td data-actions>
        <div class="dropdown">
          ${menuButton()}
          <div class="dropdown__menu" role="menu">
            <button class="dropdown__item" data-action="file:info">${item("file.details", "i-eye")}</button>
            <button class="dropdown__item" data-copy="${e(f.publicUrl || f.privateUrl)}">
              ${item("file.copyLink", "i-copy")}
            </button>
            <button class="dropdown__item" data-action="link:open">${item("file.tempLink", "i-link")}</button>
            <button class="dropdown__item" data-action="file:rename">${item("file.rename", "i-edit")}</button>
            <button class="dropdown__item" data-action="file:move">${item("file.move", "i-folder")}</button>
            <button class="dropdown__item" data-action="file:delete">${item("common.delete", "i-trash")}</button>
          </div>
        </div>
      </td>
    </tr>`);
}

/* ---------------- Токен ---------------- */

export function tokenRow(tok) {
  const full = tok.access.name === "FULL";

  return rowNode(`
    <tr data-row="token" data-id="${tok.id}" data-name="${e(tok.name)}" data-status="ACTIVE" data-access="${tok.access.name}">
      <td data-primary>
        <div class="fileline">
          <span class="ftype ftype--${full ? "doc" : "arch"}">${icon("i-key")}</span>
          <span class="fileline__body">
            <span class="fileline__name" title="${e(tok.name)}">${e(tok.name)}</span>
            <span class="fileline__meta mono">${tok.tail ? "s5w_…" + e(tok.tail) : "—"}</span>
          </span>
        </div>
      </td>
      <td ${cell("table.access")} data-half data-cell="access">
        <span class="tone tone--${full ? "warn" : "mute"}" data-i18n="access.${e(tok.access.name)}">${e(t("access." + tok.access.name))}</span>
      </td>
      <td ${cell("table.state")} data-half data-cell="status">
        <span class="tone tone--ok"><span class="status-dot status-dot--current"></span>
          <span ${label("token.state.active")}>${e(t("token.state.active"))}</span></span>
      </td>
      <td ${cell("table.expiry")} data-half class="text-sm nowrap">${
        tok.expiresAt === null
          ? `<span class="text-muted" ${label("time.forever")}>${e(t("time.forever"))}</span>`
          : `<span ${fmt("left", tok.expiresAt)}>${e(left(tok.expiresAt))}</span>`
      }</td>
      <td ${cell("table.lastUsed")} data-half class="text-sm text-muted nowrap">
        <span ${label("token.neverUsed")}>${e(t("token.neverUsed"))}</span>
      </td>
      <td ${cell("table.issued")} class="text-sm text-muted nowrap">
        <span ${label("time.now")}>${e(t("time.now"))}</span>
      </td>
      <td data-actions>
        <div class="dropdown">
          ${menuButton()}
          <div class="dropdown__menu" role="menu">
            <button class="dropdown__item" data-action="token:rotate" data-id="${tok.id}" data-name="${e(tok.name)}">
              ${item("token.rotate", "i-refresh")}
            </button>
            <button class="dropdown__item" data-action="token:toggle" data-id="${tok.id}">
              <span data-toggle-label ${label("token.disable")}>${e(t("token.disable"))}</span> ${icon("i-lock")}
            </button>
            <button class="dropdown__item" data-action="token:delete" data-id="${tok.id}" data-name="${e(tok.name)}">
              ${item("common.delete", "i-trash")}
            </button>
          </div>
        </div>
      </td>
    </tr>`);
}

export const Render = {
  bucketRow, folderCard, folderMarks, folderBadges, folderTitle, markHint, fileRow, tokenRow, statusCell,
  bytes, num, date, left, kind, quotaState, e, node,
};
