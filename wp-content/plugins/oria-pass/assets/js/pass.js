/* Oria Pass — the sticky bar, and nothing else.
 *
 * Analytics deliberately live nowhere near this file. The theme's app.js
 * already binds data-oria-event with rules worth keeping: links and buttons
 * fire on CLICK, forms on first input, and only a non-interactive element
 * fires on render. An earlier draft of this file pushed its own view event
 * and its own click handler, which double-counted every one of them --
 * exactly the impressions-counted-as-clicks problem app.js was written to
 * fix. The markup carries the attributes; app.js does the rest.
 */
(function () {
  "use strict";

  var root = document.querySelector(".pass");
  if (!root) return;

  var bar = root.querySelector("[data-pass-sticky]");
  var hero = root.querySelector(".pass-hero__acts");
  if (!bar || !hero) return;

  /* Shown only once the hero's own button has scrolled away, so the two are
     never on screen together asking for the same thing. */
  if (!("IntersectionObserver" in window)) {
    bar.hidden = false;
    return;
  }

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      bar.hidden = en.isIntersecting;
    });
  });

  io.observe(hero);
})();
