/* Папки: создание, правка, удаление, метки на карточке. */

import { Api } from "../api.js";
import { Render } from "../render.js";
import { bucketId, e, plural } from "../ui/dom.js";
import * as actions from "../ui/actions.js";
import * as forms from "../ui/forms.js";
import { Modal } from "../ui/modal.js";
import { addOption, refreshSelects } from "../ui/select.js";
import * as toast from "../ui/toast.js";
import { dropRow } from "../ui/counter.js";

/** Папка, созданная только что, должна сразу быть в списках выбора. */
export function addFolderOption(name) {
  document.querySelectorAll("[data-upload-folder], #modal-file [name=folder]").forEach((select) => addOption(select, name));
}

/** Та же форма создаёт и правит. */
function prepare(dialog, row) {
  const form = dialog.querySelector("form");
  const editing = row !== null && row !== undefined;

  forms.clearErrors(form);
  dialog.querySelector(".modal__title").textContent = editing ? "Изменить папку" : "Новая папка";
  form.querySelector('[type="submit"]').textContent = editing ? "Сохранить" : "Создать";
  form.dataset.api = editing
    ? `PUT /admin/buckets/{bucket}/folders/${encodeURIComponent(row.dataset.name)}`
    : "POST /admin/buckets/{bucket}/folders";
  form.dataset.done = editing ? "folder:updated" : "folder:created";
  form.dataset.name = editing ? row.dataset.name : "";

  form.querySelector('[name="name"]').value = editing ? row.dataset.name : "";
  form.querySelector('[name="public"]').checked = editing ? row.dataset.public === "1" : false;
  form.querySelector('[name="retention"]').value = editing ? row.dataset.retention : "0";
  refreshSelects(form);
}

function onCreated(folder) {
  addFolderOption(folder.name);
  const list = document.querySelector('[data-rows="folders"]');
  if (list) {
    list.appendChild(Render.folderCard(folder));
    list.querySelector("[data-folders-empty]")?.remove();
  }
  toast.show(`Папка <b>${e(folder.name)}</b> создана`, {
    type: "ok",
    detail: folder.public ? "публичная, файлы пойдут в /o" : "приватная, файлы только по токену",
  });
}

function onUpdated(folder) {
  toast.show(`Папка <b>${e(folder.name)}</b> сохранена`, { type: "ok" });
  setTimeout(() => location.reload(), 600);
}

async function remove({ el, row, name }) {
  const files = Number(el.dataset.files || row?.dataset.files || 0);
  const ok = await Modal.confirm({
    title: "Удалить папку?",
    text: files > 0
      ? `Вместе с папкой <b>${e(name)}</b> удалятся ${files} ${plural(files, "файл", "файла", "файлов")} внутри.`
      : `Папка <b>${e(name)}</b> пуста.`,
  });
  if (!ok) return;

  el.disabled = true;
  await Api.delete(`/admin/buckets/${bucketId()}/folders/${encodeURIComponent(name)}`);

  if (row) dropRow(row, "folders");
  toast.show(`Папка <b>${e(name)}</b> удалена`, {
    type: "ok",
    detail: files > 0 ? `вместе с ${files} ${plural(files, "файлом", "файлами", "файлами")}` : null,
  });
}

/** После смены политики кэша метка на карточке ставится сразу. */
export function patchFolderCard(name, cache) {
  const card = document.querySelector(`[data-row="folder"][data-name="${CSS.escape(name)}"]`);
  if (!card) return;

  card.dataset.maxAge = cache.maxAge ?? "";
  card.dataset.visibility = cache.visibility ? cache.visibility.name : "";

  card.querySelectorAll('[data-action="folder:cache"]').forEach((item) => {
    item.dataset.maxAge = card.dataset.maxAge;
    item.dataset.visibility = card.dataset.visibility;
  });

  const marks = Render.folderMarks({
    isPublic: card.dataset.public === "1",
    retention: card.dataset.retention,
    maxAge: card.dataset.maxAge,
    visibility: card.dataset.visibility,
  });
  card.querySelector(".fm__badges").innerHTML = Render.folderBadges(marks);
  card.querySelector(".fm__folder").title = Render.folderTitle(name, marks);
}

export function init() {
  Modal.onOpen("modal-folder", (dialog, context) => prepare(dialog, context));
  forms.onDone("folder:created", onCreated);
  forms.onDone("folder:updated", onUpdated);
  actions.register("folder:edit", ({ el, row }) => Modal.open("modal-folder", { trigger: el, context: row }));
  actions.register("folder:delete", remove);
}
