/* Сутки в статистике нарезаются по поясу смотрящего, а в базе всё лежит по часам
   в UTC. Обычная навигация заголовков не шлёт, поэтому пояс кладём в куку —
   сервер читает её при отрисовке страницы. */

export function init() {
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!tz) return;
  if (document.cookie.split("; ").includes("tz=" + tz)) return;
  document.cookie =
    "tz=" + tz + "; path=/; max-age=31536000; samesite=strict" +
    (location.protocol === "https:" ? "; secure" : "");
}
