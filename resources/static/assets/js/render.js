/* Разметка строк и карточек на клиенте — повторяет вьюхи из resources/views/admin.
   Нужна там, где запись появляется без перезагрузки: сервер её ещё не рисовал.
   Ячейки помечены так же, как на сервере: data-primary — главная, data-label —
   подпись для карточного вида на телефоне, data-actions — меню действий. */

import { e, node, rowNode } from "./ui/dom.js";

export const bytes = (n) => {
  const units = ["Б", "КБ", "МБ", "ГБ", "ТБ"];
  let i = 0;
  n = Number(n) || 0;
  while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
  return (i === 0 ? Math.round(n) : n.toFixed(1)) + " " + units[i];
};

export const num = (n) => String(Number(n) || 0).replace(/\B(?=(\d{3})+(?!\d))/g, " ");

export const date = (iso) => {
  const d = new Date(iso);
  const p = (x) => String(x).padStart(2, "0");
  return `${p(d.getDate())}.${p(d.getMonth() + 1)}.${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
};

export const left = (iso) => {
  if (!iso) return "бессрочно";
  const diff = (new Date(iso) - Date.now()) / 1000;
  if (diff <= 0) return "истёк";
  for (const [size, label] of [[86400, "д"], [3600, "ч"], [60, "мин"]]) {
    if (diff >= size) return "ещё " + Math.floor(diff / size) + " " + label;
  }
  return "меньше минуты";
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

const menuButton = () => `
  <button type="button" class="icon-btn icon-btn--ghost" data-dropdown-toggle aria-label="Действия" aria-haspopup="menu">
    ${icon("i-more-h")}
  </button>`;

/** Ячейка статуса — её перерисовывают, пока каталог заводится или сносится. */
export function statusCell(name) {
  const tone = name === "ACTIVE" ? "ok" : name === "CREATED" || name === "PENDING" ? "warn" : "mute";
  return `<span class="tone tone--${tone}"><span class="status-dot status-dot--current"></span>${e(name)}</span>`;
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
      <td data-label="Статус" data-half data-cell="status">${statusCell(b.status.name)}</td>
      <td data-label="Квота" class="col-quota">
        <div class="quota ${state}">
          <div class="quota__bar"><div class="quota__fill" style="width: ${percent}%"></div></div>
          <div class="quota__meta">
            <span><b>${bytes(b.bytes.used)}</b> из ${bytes(b.bytes.quota)}</span>
            <span>${Math.round(percent)}%</span>
          </div>
        </div>
      </td>
      <td data-label="Файлы / блобы" data-half class="num nowrap">0 <span class="text-muted">/ 0</span></td>
      <td data-label="Кэш" data-half><span class="tone tone--mute">по умолчанию</span></td>
      <td data-label="Создан" class="text-muted text-sm nowrap">${date(b.createdAt)}</td>
      <td data-actions>
        <div class="dropdown">
          ${menuButton()}
          <div class="dropdown__menu" role="menu">
            <a class="dropdown__item" href="/admin/ui/buckets/${e(b.id)}">Открыть ${icon("i-arrow-right")}</a>
            <button class="dropdown__item" data-action="bucket:edit" data-id="${e(b.id)}"
                    data-name="${e(b.name)}" data-description="${e(b.description)}" data-quota="${b.bytes.quota}">
              Изменить ${icon("i-edit")}
            </button>
            <button class="dropdown__item" data-action="bucket:cache" data-id="${e(b.id)}" data-max-age="" data-visibility="">
              Политика кэша ${icon("i-clock")}
            </button>
            <button class="dropdown__item" data-action="bucket:delete" data-id="${e(b.id)}" data-name="${e(b.name)}">
              Удалить ${icon("i-trash")}
            </button>
          </div>
        </div>
      </td>
    </tr>`);
}

/* ---------------- Папка ---------------- */

const RETENTION = {
  0: "без срока", 1: "день", 2: "неделя", 3: "месяц",
  4: "три месяца", 5: "полгода", 6: "год",
};

const VISIBILITY = { PUBLIC: "публичный", SHARED: "публичный", PRIVATE: "приватный", NO_STORE: "не хранить" };

/**
 * Метки папки: закрыта, со сроком хранения, со своим кэшем.
 * Повторяет $marks из admin/bucket-files.php — правки нужны в обоих местах.
 */
export function folderMarks({ isPublic, retention, maxAge, visibility }) {
  const marks = [];
  if (!isPublic) marks.push(["i-lock", "brand", "только по токену"]);
  if (Number(retention) > 0) marks.push(["i-clock", "warn", "срок хранения: " + RETENTION[retention]]);

  const has = (v) => v !== null && v !== undefined && v !== "";
  if (has(maxAge) || has(visibility)) {
    const parts = [];
    if (has(visibility)) parts.push(VISIBILITY[visibility] || visibility);
    if (has(maxAge)) parts.push(Number(maxAge) + " с");
    marks.push(["i-zap", "temp", "свой кэш: " + parts.join(" · ")]);
  }
  return marks;
}

export const folderBadges = (marks) =>
  marks
    .map(([name, tone, hint]) => `<svg class="icon icon--sm text-${tone}" role="img" aria-label="${e(hint)}"><use href="#${name}"/></svg>`)
    .join("");

// Без экранирования: значение уходит в свойство title, а не в разметку.
export const folderTitle = (name, marks) =>
  marks.length === 0 ? name : name + " — " + marks.map((m) => m[2]).join(", ");

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
        <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия" aria-haspopup="menu">
          ${icon("i-more-v", "icon icon--sm")}
        </button>
        <div class="dropdown__menu" role="menu">
          <button class="dropdown__item" data-action="folder:edit" data-name="${e(f.name)}">
            Изменить ${icon("i-edit")}
          </button>
          <button class="dropdown__item" data-action="folder:cache" data-name="${e(f.name)}" data-max-age="" data-visibility="">
            Политика кэша ${icon("i-clock")}
          </button>
          <button class="dropdown__item" data-action="folder:delete" data-name="${e(f.name)}" data-files="0">
            Удалить ${icon("i-trash")}
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
            <span class="fileline__meta">${bytes(f.content.size)}<span class="dot-sep"></span>${e(f.content.mime)}</span>
          </span>
        </button>
      </td>
      <td data-label="Канал" data-half><span class="tone chan chan--${channel}">/${channel}</span></td>
      <td data-label="Загружен" data-half class="text-sm nowrap"><span class="text-muted">только что</span></td>
      <td data-actions>
        <div class="dropdown">
          ${menuButton()}
          <div class="dropdown__menu" role="menu">
            <button class="dropdown__item" data-action="file:info">Подробнее ${icon("i-eye")}</button>
            <button class="dropdown__item" data-copy="${e(f.publicUrl || f.privateUrl)}">
              Копировать ссылку ${icon("i-copy")}
            </button>
            <button class="dropdown__item" data-action="link:open">Временная ссылка ${icon("i-link")}</button>
            <button class="dropdown__item" data-action="file:rename">Переименовать ${icon("i-edit")}</button>
            <button class="dropdown__item" data-action="file:move">Переместить ${icon("i-folder")}</button>
            <button class="dropdown__item" data-action="file:delete">Удалить ${icon("i-trash")}</button>
          </div>
        </div>
      </td>
    </tr>`);
}

/* ---------------- Токен ---------------- */

export function tokenRow(t) {
  const full = t.access.name === "FULL";

  return rowNode(`
    <tr data-row="token" data-id="${t.id}" data-name="${e(t.name)}" data-status="ACTIVE" data-access="${t.access.name}">
      <td data-primary>
        <div class="fileline">
          <span class="ftype ftype--${full ? "doc" : "arch"}">${icon("i-key")}</span>
          <span class="fileline__body">
            <span class="fileline__name" title="${e(t.name)}">${e(t.name)}</span>
            <span class="fileline__meta mono">${t.tail ? "s5w_…" + e(t.tail) : "—"}</span>
          </span>
        </div>
      </td>
      <td data-label="Доступ" data-half data-cell="access">
        <span class="tone tone--${full ? "warn" : "mute"}">${e(t.accessLabel)}</span>
      </td>
      <td data-label="Состояние" data-half data-cell="status">
        <span class="tone tone--ok"><span class="status-dot status-dot--current"></span> активен</span>
      </td>
      <td data-label="Срок" data-half class="text-sm nowrap">${t.expiresAt === null ? '<span class="text-muted">бессрочно</span>' : left(t.expiresAt)}</td>
      <td data-label="Использован" data-half class="text-sm text-muted nowrap">не использовался</td>
      <td data-label="Выпущен" class="text-sm text-muted nowrap">только что</td>
      <td data-actions>
        <div class="dropdown">
          ${menuButton()}
          <div class="dropdown__menu" role="menu">
            <button class="dropdown__item" data-action="token:rotate" data-id="${t.id}" data-name="${e(t.name)}">
              Ротация ${icon("i-refresh")}
            </button>
            <button class="dropdown__item" data-action="token:toggle" data-id="${t.id}">
              <span data-toggle-label>Выключить</span> ${icon("i-lock")}
            </button>
            <button class="dropdown__item" data-action="token:delete" data-id="${t.id}" data-name="${e(t.name)}">
              Удалить ${icon("i-trash")}
            </button>
          </div>
        </div>
      </td>
    </tr>`);
}

export const Render = {
  bucketRow, folderCard, folderMarks, folderBadges, folderTitle, fileRow, tokenRow, statusCell,
  bytes, num, date, left, kind, quotaState, e, node,
};
