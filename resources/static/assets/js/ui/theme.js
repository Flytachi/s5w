/* Переключатель темы. Сама тема ставится до первой отрисовки в theme.js
   (обычный скрипт в <head>) — здесь только кнопка и её значок. */

const isDark = () => document.documentElement.dataset.theme === "dark";

function updateToggles() {
  document.querySelectorAll("[data-theme-toggle]").forEach((btn) => {
    btn.querySelector("use")?.setAttribute("href", isDark() ? "#i-sun" : "#i-moon");
    btn.setAttribute("aria-label", isDark() ? "Светлая тема" : "Тёмная тема");
    btn.setAttribute("aria-pressed", isDark() ? "true" : "false");
  });
  document.querySelector('meta[name="theme-color"]')?.setAttribute("content", isDark() ? "#162033" : "#3b5bdb");
}

export function init() {
  updateToggles();

  document.addEventListener("click", (e) => {
    if (!e.target.closest("[data-theme-toggle]")) return;
    if (isDark()) delete document.documentElement.dataset.theme;
    else document.documentElement.dataset.theme = "dark";
    localStorage.setItem("theme", isDark() ? "dark" : "light");
    updateToggles();
  });
}
