/* ==========================================================================
   Oria Haven v4 -- area page extras ("The Local Rhythm",
   oria-v4/taxonomy-area.php). v4-category.js runs the hero, the Local
   Finder, the ribbon and the placing of The Local Rhythm after the sixth
   listing; app.js runs the results and the map. This file only:

     1. "Swap" on a reset-plan stop: the next place that fits that stop,
        from the candidates the server listed (all real listings here),
        announced politely for screen readers;
     2. "View map" in the hero and the Where panel: v4-category.js
        switches the results to the map; this brings them into view;
     3. a link to #plan-... opens that day plan's stops.
   ========================================================================== */
(function () {
  "use strict";

  var doc = document;
  var reduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var live = doc.querySelector("[data-xa-live]");

  /* ---- 1. swap a stop ---------------------------------------------------- */
  doc.addEventListener("click", function (e) {
    var btn = e.target.closest && e.target.closest("[data-xa-swap]");
    if (!btn) return;
    var stop = btn.closest("[data-xa-stop]");
    var list;
    try { list = JSON.parse(stop.getAttribute("data-xa-stop") || "[]"); } catch (err) { list = []; }
    if (list.length < 2) return;
    var i = ((parseInt(stop.getAttribute("data-xa-i"), 10) || 0) + 1) % list.length;
    var c = list[i];
    stop.setAttribute("data-xa-i", String(i));
    var name = stop.querySelector("[data-xa-name]");
    var cat = stop.querySelector("[data-xa-cat]");
    var blurb = stop.querySelector("[data-xa-blurb]");
    if (name) { name.textContent = c.name; name.setAttribute("href", c.url); }
    if (cat) cat.textContent = c.cat || "";
    if (blurb) { blurb.textContent = c.blurb || ""; blurb.hidden = !c.blurb; }
    if (live) live.textContent = "Swapped in " + c.name + ".";
  });

  /* ---- 2. the map, brought into view -------------------------------------- */
  doc.addEventListener("click", function (e) {
    var btn = e.target.closest && e.target.closest("[data-xa-map]");
    if (!btn) return;
    // After v4-category.js has switched the view (same click, later tick).
    window.setTimeout(function () {
      var target = doc.getElementById("browse");
      if (target) target.scrollIntoView({ behavior: reduced ? "auto" : "smooth", block: "start" });
    }, 60);
  });

  /* ---- 3. a shared plan link opens that plan ------------------------------ */
  function openPlan() {
    var id = window.location.hash.slice(1);
    if (id.indexOf("plan-") !== 0) return;
    var card = doc.getElementById(id);
    var more = card && card.querySelector("[data-oag-plan]");
    if (more) more.open = true;
  }
  openPlan();
  window.addEventListener("hashchange", openPlan);
})();
