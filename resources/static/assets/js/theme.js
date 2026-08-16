/* Growth Admin Template — dark/light theme.
   Include in <head> so the theme applies before first paint (no flash). */

(function () {
  const saved = localStorage.getItem("theme");
  const dark = saved ? saved === "dark" : window.matchMedia("(prefers-color-scheme: dark)").matches;
  if (dark) document.documentElement.dataset.theme = "dark";
})();

document.addEventListener("DOMContentLoaded", () => {
  const isDark = () => document.documentElement.dataset.theme === "dark";

  const updateToggles = () => {
    document.querySelectorAll("[data-theme-toggle] use").forEach((u) => {
      u.setAttribute("href", isDark() ? "#i-sun" : "#i-moon");
    });
  };
  updateToggles();

  document.addEventListener("click", (e) => {
    if (!e.target.closest("[data-theme-toggle]")) return;
    if (isDark()) delete document.documentElement.dataset.theme;
    else document.documentElement.dataset.theme = "dark";
    localStorage.setItem("theme", isDark() ? "dark" : "light");
    updateToggles();
    // charts and other theme-aware widgets re-render on this event
    document.dispatchEvent(new CustomEvent("themechange"));
  });
});
