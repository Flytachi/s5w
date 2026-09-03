/* Токены доступа: выпуск, ротация, включение, удаление, показ секрета. */

import { Api } from "../api.js";
import { Render } from "../render.js";
import { bucketId, e } from "../ui/dom.js";
import * as actions from "../ui/actions.js";
import * as forms from "../ui/forms.js";
import { Modal } from "../ui/modal.js";
import * as toast from "../ui/toast.js";
import { bumpCounter, dropRow, toggleEmpty } from "../ui/counter.js";

const DEFAULT_NOTE = "Значение показывается <b>один раз</b>. Скопируйте сейчас.";

/** Секрет показывается один раз: токен после выпуска, ссылка после создания. */
export function showSecret(title, value, note, check) {
  const dialog = Modal.open("modal-secret");
  if (!dialog) return;

  dialog.querySelector("[data-secret-title]").textContent = title;
  dialog.querySelector("[data-secret-value]").textContent = value;
  dialog.querySelector("[data-secret-copy]").dataset.copy = value;
  dialog.querySelector("[data-secret-note]").innerHTML = note || DEFAULT_NOTE;

  const box = dialog.querySelector("[data-secret-check]");
  box.hidden = !check;
  if (check) {
    box.querySelector("[data-secret-curl]").textContent = check.curl;
    box.querySelector("[data-secret-curl-copy]").dataset.copy = check.curl;
    box.querySelector("[data-secret-check-hint]").textContent = check.hint;
  }
}

function tokenCheck(secret, full) {
  return {
    curl: `curl -H "Authorization: Bearer ${secret}" ${location.origin}/v1/check`,
    hint: full
      ? "Ответит бакетом, видом токена и остатком места. Этому токену открыт весь /v1."
      : "Ответит бакетом, видом токена и остатком места. Этот токен умеет только /p/<slug>.",
  };
}

function countToken(row, delta) {
  if (row.dataset.expired === "1") {
    bumpCounter("tokens-expired", delta);
    return;
  }
  bumpCounter("tokens-active", delta);
  if (row.dataset.access === "FULL") bumpCounter("tokens-full", delta);
}

function onCreated(data) {
  const token = data.accessToken;
  const full = token.access.name === "FULL";

  const rows = document.querySelector('[data-rows="tokens"]');
  if (rows) {
    rows.prepend(Render.tokenRow(token));
    toggleEmpty("tokens", false);
  }

  bumpCounter("tokens-active", 1);
  if (full) bumpCounter("tokens-full", 1);

  showSecret("Токен выпущен", data.token, null, tokenCheck(data.token, full));
}

async function rotate({ row, id, name }) {
  const ok = await Modal.confirm({
    title: "Сменить значение токена?",
    text: `Старое значение перестанет работать сразу. Название <b>${e(name)}</b>,
           вид доступа и срок останутся прежними.`,
    confirmLabel: "Сменить",
    tone: "brand",
  });
  if (!ok) return;

  const res = await Api.post(`/admin/buckets/${bucketId()}/tokens/${id}/rotate`);
  const token = res.data.accessToken;

  const tail = row?.querySelector(".fileline__meta");
  if (tail) tail.textContent = "s5w_…" + token.tail;

  showSecret(
    "Новое значение токена",
    res.data.token,
    "Старый уже недействителен. Значение показывается один раз.",
    tokenCheck(res.data.token, token.access.name === "FULL"),
  );
}

async function toggle({ row, el, id }) {
  const next = row.dataset.status === "ACTIVE" ? 0 : 1;
  const res = await Api.patch(`/admin/buckets/${bucketId()}/tokens/${id}/status`, { status: next });

  row.dataset.status = res.data.status.name;
  row.querySelector('[data-cell="status"]').innerHTML = res.data.expired
    ? '<span class="tone tone--danger">просрочен</span>'
    : next === 1
      ? '<span class="tone tone--ok"><span class="status-dot status-dot--current"></span> активен</span>'
      : '<span class="tone tone--mute">выключен</span>';

  const label = el.querySelector("[data-toggle-label]");
  if (label) label.textContent = next === 1 ? "Выключить" : "Включить";
  row.classList.toggle("is-dimmed", !(next === 1 && !res.data.expired));

  countToken(row, next === 1 ? 1 : -1);
  bumpCounter("tokens-inactive", next === 1 ? -1 : 1);

  toast.show(next === 1 ? "Токен включён" : "Токен выключен", { type: next === 1 ? "ok" : "warn" });
}

async function remove({ el, row, id, name }) {
  const ok = await Modal.confirm({
    title: "Удалить токен?",
    text: `Клиент с этим токеном сразу начнёт получать 401. Восстановить нельзя —
           можно только выпустить новый.`,
  });
  if (!ok) return;

  el.disabled = true;
  await Api.delete(`/admin/buckets/${bucketId()}/tokens/${id}`);

  if (row.dataset.status === "ACTIVE") countToken(row, -1);
  else bumpCounter("tokens-inactive", -1);

  dropRow(row, "tokens");
  toast.show(`Токен <b>${e(name)}</b> удалён`, { type: "ok" });
}

export function init() {
  forms.onDone("token:created", onCreated);
  actions.register("token:rotate", rotate);
  actions.register("token:toggle", toggle);
  actions.register("token:delete", remove);
}
