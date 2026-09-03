/* Бакеты: создание, правка, удаление, переключатель в сайдбаре. */

import { Api } from "../api.js";
import { Render } from "../render.js";
import { e } from "../ui/dom.js";
import * as actions from "../ui/actions.js";
import * as forms from "../ui/forms.js";
import { Modal } from "../ui/modal.js";
import { refreshSelects } from "../ui/select.js";
import * as toast from "../ui/toast.js";
import { bumpCounter, toggleEmpty } from "../ui/counter.js";

/**
 * Опрашивает бакет, пока обработчик не скажет «хватит».
 * Исчезнувший бакет (404) — тоже ответ: так заканчивается удаление.
 */
export async function watchStatus(id, onStatus, attempts = 12) {
  for (let i = 0; i < attempts; i++) {
    await new Promise((r) => setTimeout(r, 400 + i * 120));
    try {
      const res = await Api.get(`/admin/buckets/${id}`);
      if (onStatus(res.data.status.name)) return;
    } catch (err) {
      if (err.status === 404) {
        onStatus("GONE");
        return;
      }
    }
  }
}

/** Одна и та же форма создаёт и правит: меняются заголовок, метод и адрес. */
function prepare(dialog, data) {
  const form = dialog.querySelector("form");
  const editing = !!data;

  forms.clearErrors(form);
  dialog.querySelector(".modal__title").textContent = editing ? "Изменить бакет" : "Новый бакет";
  dialog.querySelector(".modal__text").hidden = editing;
  form.querySelector('[type="submit"]').textContent = editing ? "Сохранить" : "Создать";
  form.dataset.api = editing ? `PUT /admin/buckets/${data.id}` : "POST /admin/buckets";
  form.dataset.done = editing ? "bucket:updated" : "bucket:created";
  form.dataset.id = editing ? data.id : "";

  form.querySelector('[name="name"]').value = editing ? data.name : "";
  form.querySelector('[name="description"]').value = editing ? data.description : "";
  form.querySelector('[name="unit"]').value = "1048576";
  form.querySelector('[name="quota"]').value = editing ? Math.round(Number(data.quota) / 1048576) : 512;
  refreshSelects(form);
}

function onCreated(bucket) {
  const rows = document.querySelector('[data-rows="buckets"]');
  toast.show(`Бакет <b>${e(bucket.name)}</b> создаётся`, { type: "info", detail: "статус сменится сам" });

  if (!rows) {
    setTimeout(() => location.reload(), 900);
    return;
  }

  const row = Render.bucketRow(bucket);
  rows.prepend(row);
  toggleEmpty("buckets", false);
  bumpCounter("buckets-total", 1);
  bumpCounter("buckets-pending", 1);

  // Ответ пришёл 202: строка есть, каталога ещё нет. Спрашиваем статус, пока
  // provisioner не доложит ACTIVE — это его настоящая работа, не анимация.
  watchStatus(bucket.id, (status) => {
    row.querySelector('[data-cell="status"]').innerHTML = Render.statusCell(status);
    if (status === "ACTIVE") {
      bumpCounter("buckets-pending", -1);
      bumpCounter("buckets-active", 1);
      toast.show(`Бакет <b>${e(bucket.name)}</b> готов`, { type: "ok" });
      return true;
    }
    return false;
  });
}

function onUpdated(bucket) {
  const row = document.querySelector(`[data-row="bucket"][data-id="${CSS.escape(bucket.id)}"]`);
  if (row) {
    row.dataset.name = bucket.name;
    row.querySelector("[data-bucket-name]").textContent = bucket.name;
    row.querySelector(".fileline__meta").textContent = bucket.description;
    const [percent, state] = Render.quotaState(bucket.bytes.used, bucket.bytes.quota);
    const quota = row.querySelector(".quota");
    if (quota) {
      quota.className = "quota " + state;
      quota.querySelector(".quota__fill").style.width = percent + "%";
      quota.querySelector(".quota__meta").innerHTML =
        `<span><b>${Render.bytes(bucket.bytes.used)}</b> из ${Render.bytes(bucket.bytes.quota)}</span><span>${Math.round(percent)}%</span>`;
    }
    row.querySelectorAll('[data-action="bucket:edit"]').forEach((item) => {
      item.dataset.name = bucket.name;
      item.dataset.description = bucket.description;
      item.dataset.quota = bucket.bytes.quota;
    });
  } else {
    // Страница обзора: имя в шапке и полях меняется только перезагрузкой.
    setTimeout(() => location.reload(), 600);
  }
  toast.show(`Бакет <b>${e(bucket.name)}</b> сохранён`, { type: "ok" });
}

/**
 * Удаление необратимо и происходит фоном: строка сразу уходит в PENDING,
 * пропадает она только когда сервер перестанет её отдавать.
 */
async function remove({ row, el, id, name }) {
  const ok = await Modal.confirm({
    title: "Удалить бакет?",
    text: `Вместе с <b>${e(name)}</b> уйдут все файлы, папки, токены и ссылки — каскадом.
           Каталог со всем содержимым сносится фоном, отменить будет нечем.`,
  });
  if (!ok) return;

  el.disabled = true;
  await Api.delete(`/admin/buckets/${id}`);

  if (!row) {
    toast.show(`Бакет <b>${e(name)}</b> удаляется`, { type: "info" });
    setTimeout(() => (location.href = "/admin/ui/buckets"), 800);
    return;
  }

  const statusCell = row.querySelector('[data-cell="status"]');
  if (statusCell.textContent.includes("ACTIVE")) bumpCounter("buckets-active", -1);
  bumpCounter("buckets-pending", 1);

  row.classList.add("is-dimmed");
  statusCell.innerHTML = Render.statusCell("PENDING");
  toast.show(`Бакет <b>${e(name)}</b> удаляется`, { type: "info", detail: "удаляется в фоне" });

  watchStatus(id, (status) => {
    if (status !== "GONE") return false;
    row.remove();
    bumpCounter("buckets-pending", -1);
    bumpCounter("buckets-total", -1);
    if (document.querySelector('[data-rows="buckets"]')?.querySelectorAll("[data-row]").length === 0) toggleEmpty("buckets", true);
    toast.show(`Бакет <b>${e(name)}</b> удалён`, { type: "ok" });
    return true;
  });
}

/** Переключатель бакета в сайдбаре: раздел при смене сохраняется. */
function initSwitch() {
  const sel = document.querySelector("[data-bucket-switch]");
  if (!sel) return;

  sel.addEventListener("cselect:pick", (ev) => {
    const section = sel.dataset.section === "overview" ? "" : "/" + sel.dataset.section;
    location.href = "/admin/ui/buckets/" + ev.detail.value + section;
  });
}

export function init() {
  Modal.onOpen("modal-bucket", (dialog, context) => prepare(dialog, context));
  forms.onDone("bucket:created", onCreated);
  forms.onDone("bucket:updated", onUpdated);
  actions.register("bucket:edit", ({ el, dataset }) => Modal.open("modal-bucket", { trigger: el, context: dataset }));
  actions.register("bucket:delete", remove);
  initSwitch();
}
