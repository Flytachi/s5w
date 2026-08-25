/* Числовому полю — свои стрелки: браузерные не поддаются оформлению. */

export function enhanceNumbers(root = document) {
  root.querySelectorAll('input.input[type="number"]:not([data-plain])').forEach((input) => {
    if (input.closest(".number")) return;

    const box = document.createElement("div");
    box.className = "number";
    input.parentNode.insertBefore(box, input);
    box.appendChild(input);

    box.insertAdjacentHTML(
      "beforeend",
      `<div class="number__spin">
         <button type="button" data-step="up" tabindex="-1" aria-label="Больше">
           <svg class="icon"><use href="#i-chevron-down" style="transform:rotate(180deg);transform-origin:center"/></svg>
         </button>
         <button type="button" data-step="down" tabindex="-1" aria-label="Меньше">
           <svg class="icon"><use href="#i-chevron-down"/></svg>
         </button>
       </div>`,
    );

    box.querySelectorAll("[data-step]").forEach((btn) => {
      btn.addEventListener("click", () => {
        if (input.disabled) return;
        input[btn.dataset.step === "up" ? "stepUp" : "stepDown"]();
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.dispatchEvent(new Event("change", { bubbles: true }));
      });
    });
  });
}
