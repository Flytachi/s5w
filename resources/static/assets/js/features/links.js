/* Временные ссылки: выпуск, отзыв, уборка. */

import { Api } from "../api.js";
import { bucketId } from "../ui/dom.js";
import * as actions from "../ui/actions.js";
import * as forms from "../ui/forms.js";
import { Modal } from "../ui/modal.js";
import * as toast from "../ui/toast.js";
import { bumpCounter, setCounter } from "../ui/counter.js";
import { showSecret } from "./tokens.js";
import { currentDrawerRow, loadFileLinks } from "./files.js";
import { t, plural } from "../i18n/index.js";

function onCreated(link, form) {
  // Карточка файла осталась открытой позади — обновим её список.
  const drawer = document.getElementById("drawer-file");
  const row = currentDrawerRow();
  if (drawer?.open && row) loadFileLinks(drawer, row.dataset.id);

  showSecret(t("link.issued"), link.url, t("link.issuedNote"));
  toast.show(t(link.id === null ? "link.issuedUnlisted" : "link.issuedListed"), {
    type: "info",
  });
}

const deadBadge = () =>
  `<span class="tone tone--danger" data-i18n="link.dead.revoked">${t("link.dead.revoked")}</span>`;

async function revoke({ el, row, id }) {
  const target = el.closest(".link-row") ?? row;
  const ok = await Modal.confirm({
    title: t("link.revokeTitle"),
    text: t("link.revokeText"),
    confirmLabel: t("link.revoke"),
  });
  if (!ok) return;

  await Api.delete(`/admin/buckets/${bucketId()}/links/${id}`);

  // Ссылку отзывают из двух мест: строки таблицы в разделе и карточки файла.
  if (target?.classList.contains("link-row")) {
    target.classList.add("is-off");
    target.querySelector(".link-row__head .tone").outerHTML = deadBadge();
    target.querySelector(".link-row__actions")?.remove();
  } else if (target) {
    const wasAlive = target.dataset.dead !== "1";
    target.dataset.dead = "1";
    target.classList.add("is-dimmed");
    target.querySelector('[data-cell="expiry"]').innerHTML = deadBadge();
    target.querySelector("[data-actions]")?.replaceChildren();
    if (wasAlive) {
      bumpCounter("links-active", -1);
      bumpCounter("links-revoked", 1);
    }
  }

  toast.show(t("link.revoked"), { type: "ok" });
}

/**
 * Уборка мёртвых строк. После неё меняются и счётчики, и постраничность,
 * поэтому проще перечитать страницу, чем чинить её по кусочкам.
 */
async function purge({ dataset }) {
  const state = dataset.state;
  const about = {
    revoked: {
      title: t("link.purgeRevokedTitle"),
      text: t("link.purgeRevokedText"),
    },
    expired: {
      title: t("link.purgeExpiredTitle"),
      text: t("link.purgeExpiredText"),
    },
  }[state];

  if (!(await Modal.confirm({ ...about, confirmLabel: t("common.delete") }))) return;

  const res = await Api.delete(`/admin/buckets/${bucketId()}/links/purge/${state}`);
  const removed = res.data.removed;

  toast.show(
    removed === 0 ? t("link.nothingToPurge") : plural("link.purged", removed),
    { type: removed === 0 ? "info" : "ok" },
  );

  if (removed > 0) setTimeout(() => location.reload(), 700);
}

async function revokeAll() {
  const ok = await Modal.confirm({
    title: t("link.revokeAllTitle"),
    text: t("link.revokeAllText"),
    confirmLabel: t("links.revokeAll"),
  });
  if (!ok) return;

  await Api.delete(`/admin/buckets/${bucketId()}/links`);

  let revoked = 0;
  document.querySelectorAll('[data-row="link"]').forEach((row) => {
    if (row.dataset.dead !== "1") revoked++;
    row.dataset.dead = "1";
    row.classList.add("is-dimmed");
    row.querySelector('[data-cell="expiry"]').innerHTML = deadBadge();
    row.querySelector("[data-actions]")?.replaceChildren();
  });

  setCounter("links-active", 0);
  bumpCounter("links-revoked", revoked);
  document.querySelector('[data-action="links:revoke-all"]')?.remove();

  toast.show(t("link.allRevoked"), { type: "ok", detail: t("link.allRevokedNote") });
}

export function init() {
  forms.onDone("link:created", onCreated);
  actions.register("link:revoke", revoke);
  actions.register("links:purge", purge);
  actions.register("links:revoke-all", revokeAll);
}
