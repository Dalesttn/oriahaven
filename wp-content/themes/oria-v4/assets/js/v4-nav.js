/*
 * v4 header pop-overs (search, For practitioners): <details> does the
 * opening; this closes them on Escape or a click elsewhere, and puts the
 * cursor in the search box when it opens. Without it they still work.
 */
(function () {
  "use strict";
  var pops = document.querySelectorAll(".xnav-pop");
  if (!pops.length) return;
  function closeAll(except) {
    pops.forEach(function (d) { if (d !== except) d.open = false; });
  }
  pops.forEach(function (d) {
    d.addEventListener("toggle", function () {
      if (!d.open) return;
      closeAll(d);
      var input = d.querySelector("input");
      if (input) input.focus();
    });
  });
  document.addEventListener("click", function (e) {
    pops.forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    pops.forEach(function (d) {
      if (d.open) { d.open = false; d.querySelector("summary").focus(); }
    });
  });
})();

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
