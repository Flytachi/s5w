/* Загрузка файлов.

   Мелкое уходит одним запросом, тяжёлое — кусками по 8 МиБ. Порог не
   косметический: одним запросом сервер держит в памяти всё тело целиком,
   кусками — только кусок, сколько бы ни весил файл. Плюс обрыв на середине
   гигабайта стоит одного куска, а не всей загрузки. */

import { Api } from "../api.js";
import { Render } from "../render.js";
import { bucketId, e, node, plural } from "../ui/dom.js";
import { refreshSelects } from "../ui/select.js";
import * as toast from "../ui/toast.js";
import { toggleEmpty } from "../ui/counter.js";
import { imageOptionsMarkup, initImageOptions, isImage, readImageOptions, writeImageOptions } from "./image-options.js";

const CHUNK_THRESHOLD = 16 * 1024 * 1024;
const CHUNK_SIZE = 8 * 1024 * 1024;
const CHUNK_RETRIES = 3;

function parse(text) {
  try {
    return text ? JSON.parse(text) : null;
  } catch (err) {
    return null;
  }
}

/** Что случилось с файлом — словами, а не кодом. */
function describe(file) {
  if (file.processed?.applied) return file.processed.operations.join(", ");
  if (file.deduplicated) return "дедупликация — такой файл уже есть";
  return "без обработки";
}

function xhrSend(method, url, body, { headers = {}, onProgress, item } = {}) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url);
    Object.entries(headers).forEach(([name, value]) => xhr.setRequestHeader(name, value));

    // Прогресс отдаёт только XHR — у fetch его нет.
    if (onProgress) {
      xhr.upload.addEventListener("progress", (ev) => ev.lengthComputable && onProgress(ev.loaded, ev.total));
    }

    const fail = (status, message) => reject(Object.assign(new Error(message), { status }));

    xhr.addEventListener("load", () => {
      const data = parse(xhr.responseText);
      if (xhr.status >= 200 && xhr.status < 300) resolve(data);
      else fail(xhr.status, (data && data.message) || "сервер отказал");
    });
    xhr.addEventListener("error", () => fail(0, "соединение оборвалось"));
    xhr.addEventListener("abort", () => fail(-1, "отменено"));

    if (item) item.xhr = xhr;
    xhr.send(body);
  });
}

/** Три попытки на кусок: обрыв сети сам по себе не повод терять гигабайт. */
async function withRetries(attempt, resync) {
  for (let tries = 1; ; tries++) {
    try {
      return await attempt();
    } catch (err) {
      if (tries >= CHUNK_RETRIES || (err.status !== 0 && err.status !== 409)) throw err;
      const same = await resync();
      if (!same) throw err;
      await new Promise((r) => setTimeout(r, 400 * tries));
    }
  }
}

export function init() {
  const drop = document.querySelector("[data-upload-drop]");
  if (!drop) return;

  const input = drop.querySelector("input[type=file]");
  const list = document.querySelector("[data-upload-list]");
  const hint = drop.querySelector("[data-upload-hint]");
  const total = document.querySelector("[data-upload-total]");
  const startBtn = document.querySelector("[data-upload-start]");

  /** Очередь: файл + карточка в списке. Загрузка стартует по кнопке. */
  let queue = [];

  const stop = (ev) => { ev.preventDefault(); ev.stopPropagation(); };
  ["dragenter", "dragover"].forEach((name) => drop.addEventListener(name, (ev) => { stop(ev); drop.classList.add("is-drag"); }));
  ["dragleave", "drop"].forEach((name) => drop.addEventListener(name, (ev) => { stop(ev); drop.classList.remove("is-drag"); }));

  drop.addEventListener("drop", (ev) => enqueue(ev.dataTransfer.files));
  input.addEventListener("change", () => { enqueue(input.files); input.value = ""; });
  startBtn.addEventListener("click", start);

  function enqueue(files) {
    Array.from(files || []).forEach((file) => {
      const [kind, icon] = Render.kind(file.type);
      const image = isImage(file);

      const card = node(`
        <div class="upload-item${image ? " upload-item--image" : ""}">
          <div class="upload-item__head">
            <div class="fileline">
              <div class="ftype ftype--${kind}"><svg class="icon"><use href="#${icon}"/></svg></div>
              <div class="fileline__body">
                <div class="fileline__name">${e(file.name)}</div>
                <div class="fileline__meta">${Render.bytes(file.size)}<span class="dot-sep"></span><span data-state>в очереди</span></div>
              </div>
            </div>
            <div class="upload-item__side">
              <span class="tone tone--mute" data-badge>ждёт</span>
              <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-drop-item aria-label="Убрать">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
              </button>
            </div>
          </div>
          <div class="progress mt-2"><div class="progress__bar"></div></div>
          ${image ? imageOptionsMarkup() : ""}
        </div>`);

      const item = { file, card, done: false, upload: null, xhr: null, options: card.querySelector("[data-image-options]") };
      card.querySelector("[data-drop-item]").addEventListener("click", () => {
        queue = queue.filter((q) => q !== item);
        // Убрали карточку на полпути — снимаем и запрос, и недокачанное на сервере.
        if (item.xhr) item.xhr.abort();
        if (item.upload) Api.delete(`/admin/buckets/${bucketId()}/uploads/${item.upload}`).catch(() => {});
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

    toast.show("Настройки разошлись по остальным картинкам", { type: "ok", timeout: 2000 });
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
      const options = item.options ? readImageOptions(item.options) : {};
      await (item.file.size > CHUNK_THRESHOLD || item.upload
        ? sendChunked(item, folder, options)
        : send(item, folder, options));
    }

    refresh();
  }

  /** Одна карточка очереди: полоса, бейдж и подпись под именем. */
  function itemUi(item) {
    const bar = item.card.querySelector(".progress__bar");
    const badge = item.card.querySelector("[data-badge]");
    const state = item.card.querySelector("[data-state]");

    return {
      working(text) {
        bar.classList.remove("is-failed");
        badge.className = "tone tone--brand";
        badge.textContent = "0%";
        state.textContent = text;
      },
      progress(loaded, size) {
        const percent = Math.min(100, Math.round((loaded / size) * 100));
        bar.style.width = percent + "%";
        badge.textContent = percent + "%";
      },
      done(file) {
        bar.style.width = "100%";
        badge.className = "tone tone--ok";
        badge.textContent = "готово";
        state.textContent = describe(file);
        addFileRow(file);
        setTimeout(() => { item.card.remove(); refresh(); }, 2500);
      },
      failed(err, hintText) {
        bar.style.width = "100%";
        bar.classList.add("is-failed");
        badge.className = "tone tone--danger";
        badge.textContent = err.status > 0 ? String(err.status) : "сеть";
        state.textContent = hintText || err.message;
        toast.show(`<b>${e(item.file.name)}</b> не загрузился`, { type: "error", detail: e(err.message) });
      },
    };
  }

  /** Кусками: сессия → PATCH по смещению → complete. Прогресс идёт внутри куска. */
  async function sendChunked(item, folder, options) {
    const ui = itemUi(item);
    const base = `/admin/buckets/${bucketId()}/uploads`;
    const size = item.file.size;

    ui.working("подготовка");

    try {
      let session;

      if (item.upload) {
        // Продолжаем оборванную: сервер помнит, сколько байт уже дошло.
        session = (await Api.get(`${base}/${item.upload}`)).data;
        ui.working("докачка");
      } else {
        session = (await Api.post(base, {
          name: item.file.name,
          size,
          folder: folder || null,
          format: options.format || "ORIGINAL",
          quality: options.quality === null || options.quality === undefined ? null : Number(options.quality),
          maxWidth: options.maxWidth ? Number(options.maxWidth) : null,
          maxHeight: options.maxHeight ? Number(options.maxHeight) : null,
        })).data;

        // Содержимое нашлось по хешу — загружать нечего.
        if (session.file) {
          ui.done(session.file);
          item.done = true;
          return;
        }

        item.upload = session.id;
        ui.working("загрузка");
      }

      const step = session.chunkSize || CHUNK_SIZE;
      let offset = session.offset;
      ui.progress(offset, size);

      while (offset < size) {
        const end = Math.min(offset + step, size);
        const slice = item.file.slice(offset, end);
        const sent = offset;

        const res = await withRetries(
          () => xhrSend("PATCH", `${base}/${item.upload}`, slice, {
            headers: { "Upload-Offset": String(sent), "Content-Type": "application/octet-stream" },
            onProgress: (loaded) => ui.progress(sent + loaded, size),
            item,
          }),
          async () => {
            // Перед повтором спрашиваем сервер, сколько он на самом деле принял.
            const fresh = (await Api.get(`${base}/${item.upload}`)).data;
            offset = fresh.offset;
            return fresh.offset === sent;
          },
        );

        offset = res.offset;
        ui.progress(offset, size);
      }

      ui.working("сборка");
      const file = (await Api.post(`${base}/${item.upload}/complete`)).data;

      item.upload = null;
      item.done = true;
      ui.done(file);
    } catch (err) {
      if (err && err.status === -1) return; // отменили руками
      item.done = false;
      ui.failed(err, item.upload ? "оборвалось — нажмите «Загрузить», чтобы продолжить" : null);
    }
  }

  /** Одним запросом: multipart через XHR ради прогресса. */
  async function send(item, folder, options) {
    const ui = itemUi(item);
    const form = new FormData();

    form.append("file", item.file);
    if (folder) form.append("folder", folder);
    Object.entries(options).forEach(([key, value]) => value !== null && form.append(key, value));

    ui.working("загрузка");

    try {
      const body = await xhrSend("POST", `/admin/buckets/${bucketId()}/files`, form, {
        onProgress: (loaded, size) => ui.progress(loaded, size),
        item,
      });
      item.done = true;
      ui.done(body);
    } catch (err) {
      if (err && err.status === -1) return; // отменили руками
      // Не done: повторное «Загрузить» отправит файл ещё раз.
      item.done = false;
      ui.failed(err, err.status === 0 ? "соединение оборвалось — нажмите «Загрузить» ещё раз" : null);
    }
  }

  function addFileRow(file) {
    const rows = document.querySelector('[data-rows="files"]');
    if (!rows) return;

    rows.prepend(Render.fileRow(file));
    toggleEmpty("files", false);
    toast.show(`<b>${e(file.name)}</b> загружен`, {
      type: "ok",
      detail: file.processed?.applied
        ? `${Render.bytes(file.processed.source.size)} → ${Render.bytes(file.processed.result.size)}`
        : null,
    });
  }
}
