/* Формы: submit → Api → обновление на месте.

   `data-api="METHOD /path"` задаёт запрос, `data-done="kind"` — что сделать с
   ответом. `{bucket}` подставляется из <body>, `{slug}` и `{name}` — из
   dataset формы, куда их кладёт тот, кто форму открыл. */

import { Api } from "../api.js";
import { bucketId, e } from "./dom.js";
import { close as closeModal } from "./modal.js";
import { refreshSelects } from "./select.js";
import * as toast from "./toast.js";

const done = new Map(); // kind -> fn(data, form)

/** Обработчик успешного ответа формы с data-done="kind". */
export function onDone(kind, fn) {
  done.set(kind, fn);
}

/**
 * Значения формы под то, что ждут DTO на сервере.
 *
 * Пустое число и пустой выбор — это `null` (поля объявлены как `?int` и
 * `?Enum`), а пустая строка так и остаётся строкой: у текстовых полей вроде
 * `description` в DTO стоит дефолт `''`, и `null` там не примут.
 */
export function collect(form) {
  const out = {};

  form.querySelectorAll("input[name], select[name], textarea[name]").forEach((el) => {
    if (el.type === "radio" && !el.checked) return;
    if (el.type === "checkbox") { out[el.name] = el.checked; return; }

    // Пустое значение у переключателя — это «наследовать», то есть тот же null.
    const nullable = el.type === "number" || el.type === "radio" || el.tagName === "SELECT";
    out[el.name] = el.value === "" && nullable ? null : el.value;
  });

  // квота собирается из числа и единицы
  if (out.quota !== undefined) {
    out.quotaBytes = Number(out.quota) * Number(out.unit || 1048576);
    delete out.quota;
    delete out.unit;
  }

  return out;
}

/** Кнопка на время запроса: иконку и подпись не трогаем, крутилку рисует CSS. */
export function setBusy(button, busy) {
  if (!button) return;
  button.disabled = busy;
  button.classList.toggle("is-busy", busy);
  button.setAttribute("aria-busy", busy ? "true" : "false");
}

export function clearErrors(form) {
  form.querySelectorAll(".is-invalid").forEach((el) => el.classList.remove("is-invalid"));
  form.querySelectorAll(".field__error").forEach((el) => el.remove());
}

export function showFieldError(form, name, message) {
  const input = form.querySelector(`[name="${name}"]`);
  if (!input) return;

  input.classList.add("is-invalid");
  const error = document.createElement("span");
  error.className = "field__error";
  error.textContent = message;
  input.closest(".field")?.appendChild(error);
  input.focus();
}

async function submit(form) {
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
    refreshSelects(form);
    done.get(form.dataset.done)?.(res.data, form);
  } catch (err) {
    if (err.errors) {
      Object.keys(err.errors).forEach((name) => showFieldError(form, name, err.errors[name][0]));
      toast.show("Проверьте поля формы", { type: "warn" });
    } else if (err.status === 409 && form.querySelector('[name="name"]')) {
      // Сервер отвечает «имя занято» без разбора по полям — кладём к имени сами.
      showFieldError(form, "name", "такое имя уже занято");
    } else {
      toast.show(e(err.message || "Не получилось"), { type: "error" });
    }
  } finally {
    setBusy(button, false);
  }
}

export function init() {
  // Делегированно: формы могут появляться позже (подтверждения, карточки).
  document.addEventListener("submit", (e) => {
    const form = e.target.closest("form[data-api]");
    if (!form) return;
    e.preventDefault();
    submit(form);
  });
}
