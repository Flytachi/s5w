/* Временные ссылки: выпуск, отзыв, уборка. */

import { Api } from "../api.js";
import { bucketId, plural } from "../ui/dom.js";
import * as actions from "../ui/actions.js";
import * as forms from "../ui/forms.js";
import { Modal } from "../ui/modal.js";
import * as toast from "../ui/toast.js";
import { bumpCounter, setCounter } from "../ui/counter.js";
import { showSecret } from "./tokens.js";
import { currentDrawerRow, loadFileLinks } from "./files.js";

function onCreated(link, form) {
  // Карточка файла осталась открытой позади — обновим её список.
  const drawer = document.getElementById("drawer-file");
  const row = currentDrawerRow();
  if (drawer?.open && row) loadFileLinks(drawer, row.dataset.id);

  showSecret("Ссылка выпущена", link.url, "Показывается один раз — скопируйте сейчас.");
  toast.show(link.id === null ? "Ссылка выпущена, в списке её не будет" : "Ссылка добавлена в список", {
    type: "info",
  });
}

async function revoke({ el, row, id }) {
  const target = el.closest(".link-row") ?? row;
  const ok = await Modal.confirm({ title: "Отозвать ссылку?", text: "Адрес сразу начнёт отвечать 404.", confirmLabel: "Отозвать" });
  if (!ok) return;

  await Api.delete(`/admin/buckets/${bucketId()}/links/${id}`);

  // Ссылку отзывают из двух мест: строки таблицы в разделе и карточки файла.
  if (target?.classList.contains("link-row")) {
    target.classList.add("is-off");
    target.querySelector(".link-row__head .tone").outerHTML = '<span class="tone tone--danger">отозвана</span>';
    target.querySelector(".link-row__actions")?.remove();
  } else if (target) {
    const wasAlive = target.dataset.dead !== "1";
    target.dataset.dead = "1";
    target.classList.add("is-dimmed");
    target.querySelector('[data-cell="expiry"]').innerHTML = '<span class="tone tone--danger">отозвана</span>';
    target.querySelector("[data-actions]")?.replaceChildren();
    if (wasAlive) {
      bumpCounter("links-active", -1);
      bumpCounter("links-revoked", 1);
    }
  }

  toast.show("Ссылка отозвана", { type: "ok" });
}

/**
 * Уборка мёртвых строк. После неё меняются и счётчики, и постраничность,
 * поэтому проще перечитать страницу, чем чинить её по кусочкам.
 */
async function purge({ dataset }) {
  const state = dataset.state;
  const about = {
    revoked: {
      title: "Удалить отозванные ссылки?",
      text: "Строки исчезнут из списка. Сами адреса и так уже ничего не открывают.",
    },
    expired: {
      title: "Удалить истёкшие ссылки?",
      text: "Строки с вышедшим сроком исчезнут из списка. Живые ссылки останутся.",
    },
  }[state];

  if (!(await Modal.confirm({ ...about, confirmLabel: "Удалить" }))) return;

  const res = await Api.delete(`/admin/buckets/${bucketId()}/links/purge/${state}`);
  const removed = res.data.removed;

  toast.show(
    removed === 0 ? "Нечего убирать" : `Удалено ${removed} ${plural(removed, "ссылка", "ссылки", "ссылок")}`,
    { type: removed === 0 ? "info" : "ok" },
  );

  if (removed > 0) setTimeout(() => location.reload(), 700);
}

async function revokeAll() {
  const ok = await Modal.confirm({
    title: "Отозвать все ссылки?",
    text: "Перестанут работать все выданные ссылки бакета, включая те, которых нет в списке.",
    confirmLabel: "Отозвать все",
  });
  if (!ok) return;

  await Api.delete(`/admin/buckets/${bucketId()}/links`);

  let revoked = 0;
  document.querySelectorAll('[data-row="link"]').forEach((row) => {
    if (row.dataset.dead !== "1") revoked++;
    row.dataset.dead = "1";
    row.classList.add("is-dimmed");
    row.querySelector('[data-cell="expiry"]').innerHTML = '<span class="tone tone--danger">отозвана</span>';
    row.querySelector("[data-actions]")?.replaceChildren();
  });

  setCounter("links-active", 0);
  bumpCounter("links-revoked", revoked);
  document.querySelector('[data-action="links:revoke-all"]')?.remove();

  toast.show("Все ссылки погашены", { type: "ok", detail: "все выданные адреса закрыты" });
}

export function init() {
  forms.onDone("link:created", onCreated);
  actions.register("link:revoke", revoke);
  actions.register("links:purge", purge);
  actions.register("links:revoke-all", revokeAll);
}
