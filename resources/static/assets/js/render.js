/* Разметка строк и карточек на клиенте — повторяет вьюхи из resources/views/admin.
   Нужна там, где запись появляется без перезагрузки: сервер её ещё не рисовал. */

(function () {
  const e = (s) =>
    String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

  const bytes = (n) => {
    const units = ["Б", "КБ", "МБ", "ГБ", "ТБ"];
    let i = 0;
    n = Number(n) || 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return (i === 0 ? Math.round(n) : n.toFixed(1)) + " " + units[i];
  };

  const num = (n) => String(Number(n) || 0).replace(/\B(?=(\d{3})+(?!\d))/g, " ");

  const date = (iso) => {
    const d = new Date(iso);
    const p = (x) => String(x).padStart(2, "0");
    return `${p(d.getDate())}.${p(d.getMonth() + 1)}.${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
  };

  const left = (iso) => {
    if (!iso) return "бессрочно";
    const diff = (new Date(iso) - Date.now()) / 1000;
    if (diff <= 0) return "истёк";
    for (const [size, label] of [[86400, "д"], [3600, "ч"], [60, "мин"]]) {
      if (diff >= size) return "ещё " + Math.floor(diff / size) + " " + label;
    }
    return "меньше минуты";
  };

  const kind = (mime) => {
    mime = mime || "";
    if (mime.startsWith("image/")) return ["image", "i-image"];
    if (mime.startsWith("video/")) return ["video", "i-film"];
    if (mime.startsWith("audio/")) return ["audio", "i-music"];
    if (/zip|gzip|tar/.test(mime)) return ["arch", "i-archive"];
    return ["doc", "i-file"];
  };

  const quotaState = (used, quota) => {
    const percent = quota > 0 ? Math.min(100, (used / quota) * 100) : 0;
    return [percent, percent >= 90 ? "is-danger" : percent >= 70 ? "is-warn" : ""];
  };

  const node = (html) => {
    const tpl = document.createElement("template");
    tpl.innerHTML = html.trim();
    return tpl.content.firstElementChild;
  };

  const rowNode = (html) => {
    const tbody = document.createElement("tbody");
    tbody.innerHTML = html.trim();
    return tbody.firstElementChild;
  };

  /** Ячейка статуса — её перерисовывают, пока каталог заводится или сносится. */
  function statusCell(name) {
    const tone = name === "ACTIVE" ? "ok" : name === "CREATED" || name === "PENDING" ? "warn" : "mute";
    return `<span class="tone tone--${tone}"><span class="status-dot" style="background: currentColor"></span>${e(name)}</span>`;
  }

  /* ---------------- Бакет ---------------- */

  function bucketRow(b) {
    const [percent, state] = quotaState(b.bytes.used, b.bytes.quota);

    return rowNode(`
      <tr data-row="bucket" data-id="${e(b.id)}" data-name="${e(b.name)}">
        <td>
          <a class="fileline" href="/admin/ui/buckets/${e(b.id)}">
            <span class="ftype ftype--image"><svg class="icon"><use href="#i-database"/></svg></span>
            <span class="fileline__body">
              <span class="fileline__name" data-bucket-name>${e(b.name)}</span>
              <span class="fileline__meta">${e(b.description)}</span>
            </span>
          </a>
        </td>
        <td data-cell="status">${statusCell(b.status.name)}</td>
        <td class="col-quota">
          <div class="quota ${state}">
            <div class="quota__bar"><div class="quota__fill" style="width: ${percent}%"></div></div>
            <div class="quota__meta">
              <span><b>${bytes(b.bytes.used)}</b> из ${bytes(b.bytes.quota)}</span>
              <span>${Math.round(percent)}%</span>
            </div>
          </div>
        </td>
        <td class="num">0</td>
        <td class="num text-muted">0</td>
        <td><span class="tone tone--mute">по умолчанию</span></td>
        <td class="text-muted text-sm nowrap">${date(b.createdAt)}</td>
        <td>
          <div class="dropdown">
            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
              <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
            </button>
            <div class="dropdown__menu">
              <a class="dropdown__item" href="/admin/ui/buckets/${e(b.id)}">Открыть <svg class="icon"><use href="#i-arrow-right"/></svg></a>
              <button class="dropdown__item" data-action="bucket:edit" data-id="${e(b.id)}"
                      data-name="${e(b.name)}" data-description="${e(b.description)}" data-quota="${b.bytes.quota}">
                Изменить <svg class="icon"><use href="#i-edit"/></svg>
              </button>
              <button class="dropdown__item" data-action="bucket:cache" data-id="${e(b.id)}" data-max-age="" data-visibility="">
                Политика кэша <svg class="icon"><use href="#i-clock"/></svg>
              </button>
              <button class="dropdown__item" data-action="bucket:delete" data-id="${e(b.id)}" data-name="${e(b.name)}">
                Удалить <svg class="icon"><use href="#i-trash"/></svg>
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

  const VISIBILITY = { PUBLIC: "публичный", PRIVATE: "приватный", NO_STORE: "не хранить" };

  /**
   * Метки папки: закрыта, со сроком хранения, со своим кэшем.
   * Повторяет $marks из admin/bucket-files.php — правки нужны в обоих местах.
   */
  function folderMarks({ isPublic, retention, maxAge, visibility }) {
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

  const folderBadges = (marks) =>
    marks
      .map(([icon, tone, hint]) => `<svg class="icon icon--sm text-${tone}" aria-label="${e(hint)}"><use href="#${icon}"/></svg>`)
      .join("");

  // Без экранирования: значение уходит в свойство title, а не в разметку.
  const folderTitle = (name, marks) =>
    marks.length === 0 ? name : name + " — " + marks.map((m) => m[2]).join(", ");

  /** Папка в списке менеджера. */
  function folderCard(f) {
    const marks = folderMarks({ isPublic: f.public, retention: f.retention.id, maxAge: null, visibility: null });

    return node(`
      <div class="fm__folder-wrap" data-row="folder" data-name="${e(f.name)}" data-public="${f.public ? "1" : ""}"
           data-retention="${f.retention.id}" data-files="0" data-max-age="" data-visibility="">
        <a class="fm__folder" href="?folder=${encodeURIComponent(f.name)}" title="${e(folderTitle(f.name, marks))}">
          <svg class="icon"><use href="#i-folder"/></svg>
          <span class="fm__folder-name" data-folder-name>${e(f.name)}</span>
          <span class="fm__badges">${folderBadges(marks)}</span>
          <span class="fm__count">0</span>
        </a>
        <div class="dropdown fm__folder-menu">
          <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
            <svg class="icon icon--sm"><use href="#i-more-v"/></svg>
          </button>
          <div class="dropdown__menu">
            <button class="dropdown__item" data-action="folder:edit" data-name="${e(f.name)}">
              Изменить <svg class="icon"><use href="#i-edit"/></svg>
            </button>
            <button class="dropdown__item" data-action="folder:cache" data-name="${e(f.name)}" data-max-age="" data-visibility="">
              Политика кэша <svg class="icon"><use href="#i-clock"/></svg>
            </button>
            <button class="dropdown__item" data-action="folder:delete" data-name="${e(f.name)}" data-files="0">
              Удалить <svg class="icon"><use href="#i-trash"/></svg>
            </button>
          </div>
        </div>
      </div>`);
  }

  /* ---------------- Файл ---------------- */

  function fileRow(f) {
    const [k, icon] = kind(f.content.mime);
    const channel = f.public ? "o" : "p";

    return rowNode(`
      <tr data-row="file" data-id="${e(f.id)}" data-name="${e(f.name)}" data-mime="${e(f.content.mime)}"
          data-size="${f.content.size}" data-hash="${e(f.content.hash)}" data-folder="${e(f.folder || "")}"
          data-public="${f.public ? "1" : ""}" data-created="${e(f.createdAt)}" data-expires="${e(f.expiresAt || "")}"
          data-private-url="${e(f.privateUrl)}" data-public-url="${e(f.publicUrl || "")}">
        <td>
          <button type="button" class="fileline fileline--button" data-action="file:info">
            <span class="ftype ftype--${k}"><svg class="icon"><use href="#${icon}"/></svg></span>
            <span class="fileline__body">
              <span class="fileline__name">${e(f.name)}</span>
              <span class="fileline__meta">${bytes(f.content.size)}<span class="dot-sep"></span>${e(f.content.mime)}</span>
            </span>
          </button>
        </td>
        <td><span class="tone chan chan--${channel}">/${channel}</span></td>
        <td class="text-sm nowrap"><span class="text-muted">только что</span></td>
        <td style="width:60px">
          <div class="dropdown">
            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
              <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
            </button>
            <div class="dropdown__menu">
              <button class="dropdown__item" data-action="file:info">Подробнее <svg class="icon"><use href="#i-eye"/></svg></button>
              <button class="dropdown__item" data-copy="${e(f.publicUrl || f.privateUrl)}">
                Копировать ссылку <svg class="icon"><use href="#i-copy"/></svg>
              </button>
              <button class="dropdown__item" data-action="link:open">Временная ссылка <svg class="icon"><use href="#i-link"/></svg></button>
              <button class="dropdown__item" data-action="file:rename">Переименовать <svg class="icon"><use href="#i-edit"/></svg></button>
              <button class="dropdown__item" data-action="file:move">Переместить <svg class="icon"><use href="#i-folder"/></svg></button>
              <button class="dropdown__item" data-action="file:delete">Удалить <svg class="icon"><use href="#i-trash"/></svg></button>
            </div>
          </div>
        </td>
      </tr>`);
  }

  /* ---------------- Токен ---------------- */

  function tokenRow(t) {
    return rowNode(`
      <tr data-row="token" data-id="${t.id}" data-name="${e(t.name)}" data-status="ACTIVE">
        <td>
          <div class="fileline">
            <span class="ftype ftype--image"><svg class="icon"><use href="#i-key"/></svg></span>
            <span class="fileline__body">
              <span class="fileline__name">${e(t.name)}</span>
              <span class="fileline__meta">id ${t.id}</span>
            </span>
          </div>
        </td>
        <td data-cell="status">
          <span class="tone tone--ok"><span class="status-dot" style="background:currentColor"></span> активен</span>
        </td>
        <td class="text-sm nowrap">${left(t.expiresAt)}</td>
        <td class="text-sm text-muted nowrap">не использовался</td>
        <td class="text-sm text-muted nowrap">только что</td>
        <td>
          <div class="row" style="justify-content:flex-end; flex-wrap:nowrap">
            <button class="btn btn--ghost btn--sm" data-action="token:rotate" data-id="${t.id}" data-name="${e(t.name)}">
              <svg class="icon icon--sm"><use href="#i-refresh"/></svg> Ротация
            </button>
            <div class="dropdown">
              <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
                <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
              </button>
              <div class="dropdown__menu">
                <button class="dropdown__item" data-action="token:toggle" data-id="${t.id}">
                  Выключить <svg class="icon"><use href="#i-lock"/></svg>
                </button>
                <button class="dropdown__item" data-action="token:delete" data-id="${t.id}" data-name="${e(t.name)}">
                  Удалить <svg class="icon"><use href="#i-trash"/></svg>
                </button>
              </div>
            </div>
          </div>
        </td>
      </tr>`);
  }

  window.Render = {
    bucketRow, folderCard, folderMarks, folderBadges, folderTitle, fileRow, tokenRow, statusCell,
    bytes, num, date, left, kind, quotaState, e, node,
  };
})();
