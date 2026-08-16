/* Разметка строк и карточек на клиенте — повторяет вьюхи из resources/views/admin.
   Нужна, пока новые записи появляются без перезагрузки: сервер их не рисует.
   Когда бэкенд начнёт отдавать готовые фрагменты, эти функции заменятся вставкой
   присланного HTML — точки вызова останутся теми же. */

(function () {
  const e = (s) =>
    String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

  const bytes = (n) => {
    const units = ["Б", "КБ", "МБ", "ГБ", "ТБ"];
    let i = 0;
    n = Number(n) || 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return (i === 0 ? Math.round(n) : n.toFixed(1).replace(".", ",")) + " " + units[i];
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

  /* ---------------- Бакет ---------------- */

  function bucketRow(b) {
    const [percent, state] = quotaState(b.bytes.used, b.bytes.quota);
    const tone = b.status.name === "ACTIVE" ? "ok" : b.status.name === "CREATED" || b.status.name === "PENDING" ? "warn" : "mute";

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
        <td data-cell="status">
          <span class="tone tone--${tone}"><span class="status-dot" style="background: currentColor"></span>${e(b.status.name)}</span>
        </td>
        <td>
          <div class="quota ${state}">
            <div class="quota__bar"><div class="quota__fill" style="width: ${percent}%"></div></div>
            <div class="quota__meta">
              <span><b>${bytes(b.bytes.used)}</b> из ${bytes(b.bytes.quota)}</span>
              <span>${Math.round(percent)}%</span>
            </div>
          </div>
        </td>
        <td class="num">${num(b.files)}</td>
        <td class="num text-muted">${num(b.blobs)}</td>
        <td><span class="tone tone--mute">по умолчанию</span></td>
        <td class="text-muted text-sm nowrap">${date(b.createdAt)}</td>
        <td>
          <div class="dropdown">
            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
              <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
            </button>
            <div class="dropdown__menu">
              <a class="dropdown__item" href="/admin/ui/buckets/${e(b.id)}">Открыть <svg class="icon"><use href="#i-arrow-right"/></svg></a>
              <button class="dropdown__item" data-action="bucket:delete" data-id="${e(b.id)}" data-name="${e(b.name)}">
                Удалить <svg class="icon"><use href="#i-trash"/></svg>
              </button>
            </div>
          </div>
        </td>
      </tr>`);
  }

  /* ---------------- Папка ---------------- */

  function folderCard(f) {
    const retention =
      f.retention.name === "NONE"
        ? '<span class="tone tone--mute">без срока</span>'
        : `<span class="tone tone--warn"><svg class="icon"><use href="#i-clock"/></svg> ${e(f.retention.name.toLowerCase())}</span>`;

    return node(`
      <div class="card" data-row="folder" data-name="${e(f.name)}">
        <div class="card__header">
          <span class="ftype ftype--${f.public ? "audio" : "doc"}"><svg class="icon"><use href="#i-folder"/></svg></span>
          <div>
            <div class="card__title" data-folder-name>${e(f.name)}</div>
            <div class="card__subtitle">${num(f.files)} файлов · ${bytes(f.bytes)}</div>
          </div>
          <div class="card__spacer"></div>
          <div class="dropdown">
            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
              <svg class="icon icon--sm"><use href="#i-more-v"/></svg>
            </button>
            <div class="dropdown__menu">
              <button class="dropdown__item" data-action="folder:cache" data-name="${e(f.name)}">
                Политика кэша <svg class="icon"><use href="#i-clock"/></svg>
              </button>
              <button class="dropdown__item" data-action="folder:delete" data-name="${e(f.name)}">
                Удалить <svg class="icon"><use href="#i-trash"/></svg>
              </button>
            </div>
          </div>
        </div>
        <div class="row mt-2" style="gap:8px" data-cell="badges">
          ${f.public
            ? '<span class="tone tone--ok"><svg class="icon"><use href="#i-globe"/></svg> публичная</span>'
            : '<span class="tone tone--brand"><svg class="icon"><use href="#i-lock"/></svg> по токену</span>'}
          ${retention}
        </div>
      </div>`);
  }

  /* ---------------- Файл ---------------- */

  function fileRow(f, bucketId) {
    const [k, icon] = kind(f.content.mime);
    const processed = f.processed.applied
      ? `<span class="tone tone--brand" title="${e(f.processed.operations.join(", "))}">
           <svg class="icon"><use href="#i-zap"/></svg>
           ${bytes(f.processed.source.size)} → ${bytes(f.processed.result.size)}
         </span>`
      : `<span class="tone tone--mute">${e(f.processed.reason)}</span>`;

    return rowNode(`
      <tr data-row="file" data-id="${e(f.id)}" data-name="${e(f.name)}">
        <td>
          <label class="check"><input type="checkbox" data-check="files">
            <span class="check__box"><svg class="icon"><use href="#i-check"/></svg></span>
          </label>
        </td>
        <td>
          <div class="fileline">
            <span class="ftype ftype--${k}"><svg class="icon"><use href="#${icon}"/></svg></span>
            <span class="fileline__body">
              <span class="fileline__name">${e(f.name)}</span>
              <span class="fileline__meta">
                <span class="mono">${e(f.id)}</span><span class="dot-sep"></span>${e(f.content.mime)}
                ${f.deduplicated ? '<span class="dot-sep"></span><span class="text-ok">дедуп</span>' : ""}
              </span>
            </span>
          </div>
        </td>
        <td>${f.folder ? `<span class="tone tone--mute"><svg class="icon"><use href="#i-folder"/></svg> ${e(f.folder)}</span>`
                       : '<span class="text-muted text-sm">корень</span>'}</td>
        <td>${processed}</td>
        <td class="num nowrap">${bytes(f.content.size)}</td>
        <td>${f.public ? '<span class="tone chan chan--o">/o</span>' : '<span class="tone chan chan--p">/p</span>'}</td>
        <td class="text-sm nowrap"><span class="text-muted">бессрочно</span></td>
        <td>
          <div class="dropdown">
            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
              <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
            </button>
            <div class="dropdown__menu">
              <button class="dropdown__item" data-copy="${location.origin}/o/${e(bucketId)}/${e(f.id)}">
                Копировать ссылку <svg class="icon"><use href="#i-copy"/></svg>
              </button>
              <button class="dropdown__item" data-action="link:open" data-slug="${e(f.id)}" data-name="${e(f.name)}">
                Временная ссылка <svg class="icon"><use href="#i-link"/></svg>
              </button>
              <button class="dropdown__item" data-action="file:delete" data-id="${e(f.id)}" data-name="${e(f.name)}">
                Удалить <svg class="icon"><use href="#i-trash"/></svg>
              </button>
            </div>
          </div>
        </td>
      </tr>`);
  }

  /* ---------------- Токен ---------------- */

  function tokenRow(t) {
    return rowNode(`
      <tr data-row="token" data-id="${t.id}" data-name="${e(t.name)}" data-status="${t.status.name}">
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
        <td class="text-sm text-muted nowrap">${date(t.createdAt)}</td>
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

  /* ---------------- Ссылка ---------------- */

  function linkRow(l) {
    const limit =
      l.maxDownloads === null
        ? `<span class="text-sm">0 <span class="text-muted">без лимита</span></span>`
        : `<span class="tone tone--mute">0 / ${l.maxDownloads}</span>`;

    return rowNode(`
      <tr data-row="link" data-id="${l.id === null ? "" : l.id}">
        <td>
          <div class="fileline">
            <span class="ftype ftype--video"><svg class="icon"><use href="#i-link"/></svg></span>
            <span class="fileline__body">
              <span class="fileline__name">${e(l.file || "файл")}</span>
              <span class="fileline__meta mono">${e(l.slug)}</span>
            </span>
          </div>
        </td>
        <td>
          <span class="tone tone--${l.disposition.name === "ATTACHMENT" ? "brand" : "mute"}">
            ${l.disposition.name === "ATTACHMENT" ? "скачивание" : "просмотр"}
          </span>
        </td>
        <td>${limit}</td>
        <td class="text-sm nowrap" data-cell="expiry">${left(l.expiresAt)}</td>
        <td class="text-sm text-muted">${e(l.note)}</td>
        <td>
          <div class="row" style="justify-content:flex-end; flex-wrap:nowrap">
            <span class="copyable mono" data-copy="${e(l.url)}">/t/${e(l.url.split("/t/")[1].slice(0, 10))}…
              <svg class="icon"><use href="#i-copy"/></svg>
            </span>
            ${l.id !== null
              ? `<button class="icon-btn icon-btn--ghost icon-btn--sm" data-action="link:revoke" data-id="${l.id}" aria-label="Отозвать">
                   <svg class="icon icon--sm"><use href="#i-x-circle"/></svg>
                 </button>`
              : ""}
          </div>
        </td>
      </tr>`);
  }

  window.Render = { bucketRow, folderCard, fileRow, tokenRow, linkRow, bytes, num, date, left, kind, quotaState, e, node };
})();
