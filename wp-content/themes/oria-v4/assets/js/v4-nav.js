/*
 * Footer columns: open on wider screens, folded on phones so the footer is
 * a short list of headings. Without this script they simply stay open.
 */
(function () {
  "use strict";
  var groups = document.querySelectorAll(".foot__group");
  if (!groups.length || !window.matchMedia) return;
  var narrow = window.matchMedia("(max-width: 48rem)");
  var apply = function () { groups.forEach(function (g) { g.open = !narrow.matches; }); };
  apply();
  if (narrow.addEventListener) narrow.addEventListener("change", apply);
})();
