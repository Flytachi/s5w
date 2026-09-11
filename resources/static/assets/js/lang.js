(function () {
  var saved = null;
  try {
    saved = localStorage.getItem("lang");
  } catch (e) {}
  document.documentElement.lang = saved === "ru" ? "ru" : "en";
})();
