/* Политика кэша для бакета и папки: форма и живой разбор заголовка. */

import { bucketId, e } from "../ui/dom.js";
import * as actions from "../ui/actions.js";
import * as forms from "../ui/forms.js";
import { Modal } from "../ui/modal.js";
import { refreshSelects } from "../ui/select.js";
import * as toast from "../ui/toast.js";
import { patchFolderCard } from "./folders.js";

/** Здравый срок под выбранный режим: подставляется при смене, devops перебьёт руками. */
const DEFAULT_AGE = 86400;

const TTL_SUGGEST = {
  bucket: { 1: DEFAULT_AGE, 2: 3600, 3: null },
  folder: { "": null, 1: DEFAULT_AGE, 2: 3600, 3: null },
};

const TTL_HINT = {
  "": "Пусто — срок берётся с бакета.",
  1: "Пусто — сутки. Ноль — браузер перепроверяет каждый раз.",
  2: "Пусто — без кэша: браузер перепроверяет каждый раз.",
  3: "При этом режиме срок в заголовок не попадает.",
};

const WORDING = {
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

  const age = maxAge !== null && maxAge !== "" ? Number(maxAge) : scope === "SHARED" ? DEFAULT_AGE : 0;
  const word = scope === "SHARED" ? "public" : "private";

  if (age <= 0) return `${word}, max-age=0, must-revalidate`;
  return `${word}, max-age=${age}` + (scope === "SHARED" ? ", immutable" : "");
}

function renderPreview(form) {
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
        <span class="cache-preview__value">${e(cacheHeader({ maxAge, visibility, filePublic, channel }))}</span>
        <span class="cache-preview__note">${label}</span>
      </span>
    </div>`;

  const words = WORDING[form.dataset.level] || WORDING.folder;

  box.innerHTML =
    `<div class="cache-preview__note">${inherited ? words.empty : "Что уйдёт в заголовке при таких настройках:"}</div>` +
    row("o", "файл в публичной папке", true) +
    row("p", "по токену", false) +
    row("t", "по временной ссылке — но не дольше её самой", false);
}

/** Срок, который действует у бакета: его показываем папке как «как в бакете (N)». */
function inheritedAge() {
  const body = document.body.dataset;
  if (body.cacheMaxAge) return body.cacheMaxAge;
  if (body.cacheVisibility === "NO_STORE") return null;
  if (body.cacheVisibility === "PRIVATE") return "0";
  return String(DEFAULT_AGE);
}

/** Пустое поле не должно молчать: в подсказке — число, которое уйдёт клиенту. */
function ttlPlaceholder(level, value) {
  if (value === "3") return "—";
  if (value === "2") return "0 — без кэша";
  if (value === "1") return DEFAULT_AGE + " — по умолчанию";

  const age = inheritedAge();
  return age === null ? "как в бакете" : `как в бакете (${age})`;
}

/**
 * Срок под выбранный режим: при смене режима подставляется здравое число, но только
 * если поле пустое или в нём стоит прошлая подсказка — руками введённое не затираем.
 * У режима «Никому» срока нет вовсе, поэтому поле гасится.
 */
function syncTtl(form, switched = false) {
  const ttl = form.querySelector('[name="maxAge"]');
  const value = form.elements.visibility.value;
  const level = form.dataset.level === "bucket" ? "bucket" : "folder";
  const suggest = TTL_SUGGEST[level][value === "" ? "" : Number(value)] ?? null;
  const off = value === "3";

  form.querySelector("[data-cache-ttl]").classList.toggle("is-off", off);
  ttl.disabled = off;
  ttl.placeholder = ttlPlaceholder(level, value);
  form.querySelectorAll("[data-cache-presets] [data-ttl]").forEach((b) => (b.disabled = off));
  form.querySelector("[data-cache-ttl-hint]").textContent = TTL_HINT[value === "" ? "" : Number(value)];

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

function prepare(dialog, { data, isFolder }) {
  const form = dialog.querySelector("form");
  const words = isFolder ? WORDING.folder : WORDING.bucket;

  forms.clearErrors(form);
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
  dialog.querySelector("[data-cache-target]").textContent = isFolder ? "· папка " + data.name : "· весь бакет";

  syncTtl(form);
  refreshSelects(form);
  renderPreview(form);

  if (!form.dataset.previewBound) {
    form.addEventListener("input", () => renderPreview(form));
    form.addEventListener("change", (ev) => {
      if (ev.target.name === "visibility") syncTtl(form, true);
      renderPreview(form);
    });
    form.querySelectorAll("[data-cache-presets] [data-ttl]").forEach((button) => {
      button.addEventListener("click", () => {
        ttl.value = button.dataset.ttl;
        ttl.dataset.suggested = "";
        renderPreview(form);
      });
    });
    form.dataset.previewBound = "1";
  }
}

function onSaved(data, form) {
  const name = form.dataset.name;
  toast.show(name ? `Кэш папки <b>${e(name)}</b> сохранён` : "Кэш бакета сохранён", { type: "ok" });

  if (name) {
    patchFolderCard(name, data.cache || {});
    return;
  }

  // Бакет: значения в <body> питают подсказки папок, обновим и их.
  const cache = data.cache || {};
  document.body.dataset.cacheMaxAge = cache.maxAge ?? "";
  document.body.dataset.cacheVisibility = cache.visibility ? cache.visibility.name : "";
  document.querySelectorAll('[data-action="bucket:cache"]').forEach((item) => {
    item.dataset.maxAge = document.body.dataset.cacheMaxAge;
    item.dataset.visibility = document.body.dataset.cacheVisibility;
  });
  if (document.body.dataset.page === "overview") setTimeout(() => location.reload(), 600);
}

export function init() {
  Modal.onOpen("modal-cache", prepare);
  forms.onDone("cache:saved", onSaved);
  actions.register("folder:cache", ({ el, dataset }) =>
    Modal.open("modal-cache", { trigger: el, context: { data: dataset, isFolder: true } }));
  actions.register("bucket:cache", ({ el, dataset }) =>
    Modal.open("modal-cache", { trigger: el, context: { data: dataset, isFolder: false } }));
}
