/* Вход и выход. */

import { Api } from "../api.js";
import { setBusy } from "../ui/forms.js";

function initLogin() {
  const form = document.querySelector("[data-login-form]");
  if (!form) return;

  const box = form.querySelector("[data-login-error]");
  const peek = form.querySelector("[data-password-peek]");
  const password = form.querySelector('[name="password"]');

  peek?.addEventListener("click", () => {
    const shown = password.type === "text";
    password.type = shown ? "password" : "text";
    peek.querySelector("use").setAttribute("href", shown ? "#i-eye" : "#i-eye-off");
    peek.setAttribute("aria-label", shown ? "Показать пароль" : "Скрыть пароль");
    peek.setAttribute("aria-pressed", shown ? "false" : "true");
    password.focus();
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const button = form.querySelector('[type="submit"]');
    box.hidden = true;
    setBusy(button, true);

    try {
      await Api.post("/admin/auth/login", {
        login: form.querySelector('[name="login"]').value.trim(),
        password: password.value,
      });
      location.href = form.dataset.next || "/admin/ui";
    } catch (err) {
      form.querySelector("[data-login-message]").textContent =
        err.status === 429 ? "Слишком много попыток. Попробуйте позже." : err.message;
      box.hidden = false;
      password.value = "";
      password.focus();
    } finally {
      setBusy(button, false);
    }
  });
}

function initLogout() {
  document.querySelector("[data-logout]")?.addEventListener("click", async (e) => {
    e.preventDefault();
    try {
      await Api.post("/admin/auth/logout");
    } finally {
      location.href = "/admin/ui/login";
    }
  });
}

export function init() {
  initLogin();
  initLogout();
}
