/*
 * v4 front page: the feeling chips.
 *
 * A chip swaps the hero picture and writes its sentence into the Ask Oria
 * box, ready to send or edit. Nothing is sent until the visitor submits.
 * Without this script the page still works: the box is a plain form.
 */
(function () {
  "use strict";
  var hero = document.querySelector("[data-xh-hero]");
  if (!hero) return;
  var chips = hero.querySelectorAll("[data-feel]");
  var pics = hero.querySelectorAll("[data-feel-pic]");
  var input = document.getElementById("xh-q");

  function pick(chip) {
    var feel = chip.getAttribute("data-feel");
    chips.forEach(function (c) { c.setAttribute("aria-pressed", c === chip ? "true" : "false"); });
    pics.forEach(function (p) {
      var on = p.getAttribute("data-feel-pic") === feel;
      if (on && p.loading === "lazy") p.loading = "eager";
      p.classList.toggle("is-on", on);
    });
    if (input) {
      input.value = chip.getAttribute("data-feel-say") || "";
      input.focus({ preventScroll: true });
    }
  }

  chips.forEach(function (chip) {
    chip.addEventListener("click", function () { pick(chip); });
  });
})();
