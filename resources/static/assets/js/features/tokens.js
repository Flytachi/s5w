/* Токены доступа: выпуск, ротация, включение, удаление, показ секрета. */

import { Api } from "../api.js";
import { Render } from "../render.js";
import { bucketId, e } from "../ui/dom.js";
import * as actions from "../ui/actions.js";
import * as forms from "../ui/forms.js";
import { Modal } from "../ui/modal.js";
import * as toast from "../ui/toast.js";
import { bumpCounter, dropRow, toggleEmpty } from "../ui/counter.js";
import { t } from "../i18n/index.js";

const DEFAULT_NOTE = () => t("modal.secret.note");

/** Секрет показывается один раз: токен после выпуска, ссылка после создания. */
export function showSecret(title, value, note, check) {
  const dialog = Modal.open("modal-secret");
  if (!dialog) return;

  dialog.querySelector("[data-secret-title]").textContent = title;
  dialog.querySelector("[data-secret-value]").textContent = value;
  dialog.querySelector("[data-secret-copy]").dataset.copy = value;
  dialog.querySelector("[data-secret-note]").innerHTML = note || DEFAULT_NOTE();

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
      ? t("token.checkFull")
      : t("token.checkBasic"),
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

  showSecret(t("token.issued"), data.token, null, tokenCheck(data.token, full));
}

async function rotate({ row, id, name }) {
  const ok = await Modal.confirm({
    title: t("token.rotateTitle"),
    text: t("token.rotateText", { name: e(name) }),
    confirmLabel: t("token.rotateConfirm"),
    tone: "brand",
  });
  if (!ok) return;

  const res = await Api.post(`/admin/buckets/${bucketId()}/tokens/${id}/rotate`);
  const token = res.data.accessToken;

  const tail = row?.querySelector(".fileline__meta");
  if (tail) tail.textContent = "s5w_…" + token.tail;

  showSecret(
    t("token.newValue"),
    res.data.token,
    t("token.newValueNote"),
    tokenCheck(res.data.token, token.access.name === "FULL"),
  );
}

async function toggle({ row, el, id }) {
  const next = row.dataset.status === "ACTIVE" ? 0 : 1;
  const res = await Api.patch(`/admin/buckets/${bucketId()}/tokens/${id}/status`, { status: next });

  row.dataset.status = res.data.status.name;
  row.querySelector('[data-cell="status"]').innerHTML = res.data.expired
    ? `<span class="tone tone--danger" data-i18n="token.state.expired">${t("token.state.expired")}</span>`
    : next === 1
      ? `<span class="tone tone--ok"><span class="status-dot status-dot--current"></span> <span data-i18n="token.state.active">${t("token.state.active")}</span></span>`
      : `<span class="tone tone--mute" data-i18n="token.state.off">${t("token.state.off")}</span>`;

  const label = el.querySelector("[data-toggle-label]");
  if (label) {
    label.dataset.i18n = next === 1 ? "token.disable" : "token.enable";
    label.textContent = t(label.dataset.i18n);
  }
  row.classList.toggle("is-dimmed", !(next === 1 && !res.data.expired));

  countToken(row, next === 1 ? 1 : -1);
  bumpCounter("tokens-inactive", next === 1 ? -1 : 1);

  toast.show(t(next === 1 ? "token.enabled" : "token.disabled"), { type: next === 1 ? "ok" : "warn" });
}

async function remove({ el, row, id, name }) {
  const ok = await Modal.confirm({
    title: t("token.deleteTitle"),
    text: t("token.deleteText"),
  });
  if (!ok) return;

  el.disabled = true;
  await Api.delete(`/admin/buckets/${bucketId()}/tokens/${id}`);

  if (row.dataset.status === "ACTIVE") countToken(row, -1);
  else bumpCounter("tokens-inactive", -1);

  dropRow(row, "tokens");
  toast.show(t("token.deleted", { name: "<b>" + e(name) + "</b>" }), { type: "ok" });
}

export function init() {
  forms.onDone("token:created", onCreated);
  actions.register("token:rotate", rotate);
  actions.register("token:toggle", toggle);
  actions.register("token:delete", remove);
}
