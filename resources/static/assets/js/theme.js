/* Тема до первой отрисовки: подключается в <head> обычным скриптом, чтобы
   страница не мигала светлым. Кнопка переключения — в ui/theme.js. */
(function () {
  var saved = localStorage.getItem("theme");
  var dark = saved ? saved === "dark" : window.matchMedia("(prefers-color-scheme: dark)").matches;
  if (dark) document.documentElement.dataset.theme = "dark";
})();
