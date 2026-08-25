/* Обработка картинок — на карточке самой картинки.
   Настройки нужны не «вообще при загрузке», а конкретному файлу: у одного
   свой формат, другой трогать не надо. Поэтому панель живёт в очереди
   рядом с превью и появляется только там, где сервис умеет что-то сделать. */

import { enhanceSelects } from "../ui/select.js";
import { enhanceNumbers } from "../ui/number.js";

/** Форматы, которые сервис умеет пережимать (см. ImageProcessor::SUPPORTED). */
const IMAGE_MIMES = ["image/jpeg", "image/png", "image/gif", "image/webp", "image/avif"];

export const isImage = (file) => IMAGE_MIMES.includes(file.type);

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
  if (MIME_FORMAT[sourceMime] === target) return "Файл уже в этом формате — перекодировать нечего.";
  if (target === "JPEG" && sourceMime !== "image/jpeg") return "У jpeg нет прозрачности: прозрачные места зальются белым.";
  if (target === "PNG" && sourceMime === "image/jpeg") return "Фотография в png тяжелее исходника — сервис тогда оставит оригинал.";
  return null;
}

export function imageOptionsMarkup() {
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
            `<div class="row row--nowrap">
               <div class="field w-full">
                 <label class="field__label">Ширина до, px</label>
                 <input class="input" type="number" name="maxWidth" placeholder="не ограничивать" inputmode="numeric">
               </div>
               <div class="field w-full">
                 <label class="field__label">Высота до, px</label>
                 <input class="input" type="number" name="maxHeight" placeholder="не ограничивать" inputmode="numeric">
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

        <div class="row mt-2">
          <span class="text-sm text-muted grow" data-image-summary></span>
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
export function readImageOptions(box) {
  const value = (name) => box.querySelector(`[name=${name}]`).value;

  return {
    format: optionOn(box, "format") ? value("format") : "ORIGINAL",
    quality: optionOn(box, "quality") ? value("quality") : null,
    maxWidth: optionOn(box, "resize") ? value("maxWidth") || null : null,
    maxHeight: optionOn(box, "resize") ? value("maxHeight") || null : null,
  };
}

export function writeImageOptions(box, options) {
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
function describe(o) {
  const parts = [];
  if (o.format !== "ORIGINAL") parts.push("в " + o.format.toLowerCase());
  if (o.maxWidth || o.maxHeight) parts.push(`до ${o.maxWidth || "∞"}×${o.maxHeight || "∞"}`);
  if (o.quality) parts.push("качество " + o.quality);
  return parts;
}

/** @param {() => void} onApplyAll — раздать эти же настройки остальным картинкам. */
export function initImageOptions(box, sourceMime, onApplyAll) {
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
    const parts = describe(o);
    const target = o.format === "ORIGINAL" ? MIME_FORMAT[sourceMime] : o.format;

    // Пока качество не тронуто, ползунок стоит там, откуда сервис начнёт для
    // выбранного формата: у avif своя шкала, 82 у него — уже не «как webp».
    const preset = FORMAT_QUALITY[target] ?? 82;
    if (!optionOn(box, "quality")) quality.value = preset;
    box.querySelector("[data-quality-value]").textContent = quality.value;
    box.querySelector("[data-quality-hint]").textContent =
      `Меньше — легче файл и заметнее артефакты. Без этой настройки сервис берёт ${preset}.`;

    box.querySelector("[data-format-about]").textContent = o.format === "ORIGINAL" ? "" : FORMAT_ABOUT[o.format];

    const warn = box.querySelector("[data-format-warn]");
    const text = o.format === "ORIGINAL" ? null : formatWarning(o.format, sourceMime);
    warn.hidden = text === null;
    warn.textContent = text ?? "";

    box.classList.toggle("is-set", parts.length > 0);
    note.textContent = parts.length ? parts.join(" · ") : "как есть";
    out.textContent = parts.length ? "Квота считается по результату обработки." : "Файл ляжет байт в байт.";
  };

  box.addEventListener("input", update);
  box.addEventListener("change", update);
  update();
}
