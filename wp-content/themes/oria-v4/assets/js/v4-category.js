/* ==========================================================================
   Oria Haven v4 -- category page behaviour (oria-v4/oria-practice-v2.php).

   Works beside the parent's app.js, never instead of it: app.js still owns
   filtering, sorting, the pager, the map, Save and Compare. This file only
   reads what app.js leaves behind (the "oria:dir-results" event it fires
   after every render, the #dirCount it writes, window.ORIA_DATA) and adds:

     1. sticky-chrome measurements as CSS variables, so the section menu,
        the toolbar and every anchor clear the site header;
     2. the Best Of block after the eighth listing, put back after each
        re-render (app.js rebuilds #dirResults with innerHTML);
     3. "Specialists in X -- n / Also offering X -- m", recounted per filter;
     4. the first row of card pictures loaded eagerly;
     5. phones: a compact Filters · Map · Sort · count row, with the
        filters behind a "Filters · n" disclosure;
     6. the pager's scroll-to-results corrected for the sticky toolbar.

   Deferred, so it runs before DOMContentLoaded -- i.e. before app.js's own
   init, which is what lets it catch the server-drawn Best Of block before
   the first render throws it away.
   ========================================================================== */
(function () {
  "use strict";

  var doc = document;
  function $(s, c) { return (c || doc).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); }

  var root = $("#dirResults");
  if (!root || root.getAttribute("data-mode") !== "category") return;

  var phone = window.matchMedia("(max-width: 50rem)");
  var reduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var docEl = doc.documentElement;

  /* ---- 1. sticky chrome ------------------------------------------------ */
  function headHeight() {
    var head = $(".site-head--solid") || $(".site-head");
    if (!head) return 0;
    var pos = getComputedStyle(head).position;
    return pos === "sticky" || pos === "fixed" ? Math.round(head.getBoundingClientRect().height) : 0;
  }
  function measure() {
    var spine = $(".spine");
    var bar = $("#dirFilters");
    docEl.style.setProperty("--xc-head-h", headHeight() + "px");
    if (spine) docEl.style.setProperty("--xc-spine-h", spine.offsetHeight + "px");
    if (bar) docEl.style.setProperty("--xc-bar-h", bar.offsetHeight + "px");
  }
  measure();
  window.addEventListener("resize", measure);
  window.addEventListener("load", measure);

  /* ---- 2. the Best Of block, after the eighth listing ------------------ */
  var bo = $("#xcBestOf");
  var boHome = null;
  if (bo) {
    boHome = doc.createComment("xc-bestof-home");
    bo.parentNode.insertBefore(boHome, bo);
  }
  var AFTER = 8;
  function onFirstPage() {
    var cur = $(".pager__num.is-current");
    return cur ? (parseInt(cur.getAttribute("data-page"), 10) || 1) === 1 : true;
  }
  function placeBestOf() {
    if (!bo || !boHome || !boHome.parentNode) return;
    var cards = $$("#dirResults > article.listing");
    /* Only mid-list on page one, and only with more listings to follow --
       otherwise it waits under the list, where it was drawn. */
    if (onFirstPage() && cards.length > AFTER) {
      if (cards[AFTER - 1].nextElementSibling !== bo) cards[AFTER - 1].insertAdjacentElement("afterend", bo);
      bo.classList.add("is-inline");
    } else {
      if (boHome.nextSibling !== bo) boHome.parentNode.insertBefore(bo, boHome.nextSibling);
      bo.classList.remove("is-inline");
    }
  }

  /* ---- 3. specialists / also offering ---------------------------------- */
  var split = $("#xcSplit");
  var FAMILY = (root.getAttribute("data-family") || "").split(" ").filter(Boolean);
  var catByUrl = null;
  function catOf(url) {
    if (!catByUrl) {
      catByUrl = {};
      var data = window.ORIA_DATA || window.ORIA_SEARCH_DATA || {};
      (data.listings || []).forEach(function (l) { if (l && l.url) catByUrl[l.url] = l.cat; });
    }
    return catByUrl[url];
  }
  function updateSplit(urls) {
    if (!split || !urls || !FAMILY.length) return;
    var spec = 0;
    urls.forEach(function (u) { if (FAMILY.indexOf(catOf(u)) > -1) spec++; });
    var other = urls.length - spec;
    split.hidden = !(spec && other);
    var a = $('[data-xc-split="spec"]', split), b = $('[data-xc-split="also"]', split);
    if (a) a.textContent = String(spec);
    if (b) b.textContent = String(other);
  }

  /* ---- 4. first row of pictures eager ---------------------------------- */
  function eagerFirstRow() {
    $$("#dirResults > article.listing img").forEach(function (img, i) {
      if (i < 2) img.loading = "eager";
    });
  }

  /* ---- 5. phones: Filters · Map · Sort · count ------------------------- */
  var bar = $("#dirFilters");
  var fBtn = null, fPanel = null, fCount = null, mirror = null;
  function activeFilters() {
    if (!bar) return 0;
    var lockKey = root.getAttribute("data-intent-key") || "";
    var lockVal = root.getAttribute("data-intent-value") || "";
    var n = 0;
    /* Every ticked box in the toolbar -- some live in sheets that app.js has
       lifted out to <body> while open, so look there too. */
    var seen = [];
    $$('[data-filter]:checked').forEach(function (i) {
      if (seen.indexOf(i) > -1) return;
      seen.push(i);
      if (i.getAttribute("data-filter") === lockKey && i.value === lockVal) return; // the page's own facet
      n++;
    });
    var q = $("#dirQ");
    if (q && q.value.trim()) n++;
    var picks = $("[data-best-toggle]");
    if (picks && picks.getAttribute("aria-pressed") === "true") n++;
    return n;
  }
  function paintFilterCount() {
    if (!fCount) return;
    var n = activeFilters();
    fCount.textContent = n ? " · " + n : "";
    fBtn.setAttribute("aria-label", n ? "Filters, " + n + " active" : "Filters");
  }
  function setOpen(open, focusBack) {
    if (!fBtn) return;
    bar.classList.toggle("xc-open", open);
    fBtn.setAttribute("aria-expanded", open ? "true" : "false");
    measure();
    if (open) {
      var first = $("input, summary, button", fPanel);
      if (first) first.focus({ preventScroll: true });
    } else if (focusBack) {
      fBtn.focus({ preventScroll: true });
    }
  }
  function buildPhoneBar() {
    if (!bar || fBtn) return;
    var search = $(".toolbar__search", bar);
    var filters = $(".toolbar__filters", bar);
    var near = $(".toolbar__near", bar);
    if (!filters) return;

    /* The three groups a phone tucks away, wrapped once. On a wide screen the
       wrapper is display:contents, so the desktop row is exactly as it was.
       Everything stays inside #dirFilters, where app.js looks for it. */
    fPanel = doc.createElement("div");
    fPanel.className = "xc-fpanel";
    fPanel.id = "xcFilterPanel";
    bar.insertBefore(fPanel, search || filters);
    [search, filters, near].forEach(function (el) { if (el) fPanel.appendChild(el); });

    var row = doc.createElement("div");
    row.className = "xc-tbrow";

    fBtn = doc.createElement("button");
    fBtn.type = "button";
    fBtn.className = "xc-tbbtn xc-tbbtn--filters";
    fBtn.setAttribute("aria-expanded", "false");
    fBtn.setAttribute("aria-controls", "xcFilterPanel");
    fBtn.appendChild(doc.createTextNode("Filters"));
    fCount = doc.createElement("span");
    fCount.className = "xc-tbbtn__n";
    fBtn.appendChild(fCount);
    fBtn.addEventListener("click", function () {
      setOpen(fBtn.getAttribute("aria-expanded") !== "true", false);
    });
    row.appendChild(fBtn);

    /* Map: the page's own List | Map switch, pressed from the bar. */
    var mapSwitch = $('.viewswitch [data-view="map"]');
    if (mapSwitch) {
      var mBtn = doc.createElement("button");
      mBtn.type = "button";
      mBtn.className = "xc-tbbtn xc-tbbtn--map";
      mBtn.textContent = "Map";
      mBtn.addEventListener("click", function () {
        var sw = $('.viewswitch [data-view="map"]');
        if (sw && !sw.closest("[hidden]")) sw.click();
      });
      row.appendChild(mBtn);
      doc.body.classList.add("xc-has-tbmap");
    }

    mirror = doc.createElement("span");
    mirror.className = "xc-tbcount";
    mirror.setAttribute("aria-hidden", "true"); // #dirCount is the live region
    row.appendChild(mirror);

    bar.insertBefore(row, bar.firstChild);
    bar.classList.add("xc-bar");
    paintFilterCount();
    measure();
  }
  function paintMirror() {
    var c = $("#dirCount");
    if (!mirror || !c) return;
    var m = c.textContent.match(/\d[\d,]*/);
    mirror.textContent = m ? m[0] + (m[0] === "1" ? " result" : " results") : "";
  }
  buildPhoneBar();

  if (bar) {
    // A phone closes the disclosure once a filter is chosen: app.js then
    // closes that filter's sheet and scrolls to the results, and the
    // results should not sit under an open panel.
    doc.addEventListener("change", function (e) {
      if (!phone.matches || !fBtn || !e.target.closest) return;
      if (e.target.closest("[data-filter]")) setOpen(false, false);
      window.setTimeout(paintFilterCount, 0);
    });
    doc.addEventListener("input", function (e) {
      if (e.target && e.target.id === "dirQ") paintFilterCount();
    });
    doc.addEventListener("keydown", function (e) {
      if (e.key !== "Escape" || !fBtn || fBtn.getAttribute("aria-expanded") !== "true") return;
      // An open filter sheet takes Escape first (app.js); this closes next.
      if (docEl.classList.contains("has-sheet")) return;
      setOpen(false, true);
    });
    if (phone.addEventListener) phone.addEventListener("change", function () { if (!phone.matches) setOpen(false, false); measure(); });
  }

  /* ---- 6. the pager lands clear of the sticky toolbar ------------------ */
  var pagerClick = false;
  doc.addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest(".pager [data-page]");
    pagerClick = !!(b && !b.disabled);
  }, true);
  doc.addEventListener("click", function () {
    if (!pagerClick) return;
    pagerClick = false;
    window.setTimeout(function () {
      var head = $("#results") || root;
      var barH = bar ? bar.getBoundingClientRect().height : 0;
      var top = head.getBoundingClientRect().top + window.pageYOffset - headHeight() - barH - 12;
      window.scrollTo({ top: Math.max(0, top), behavior: reduced ? "auto" : "smooth" });
    }, 0);
  });

  /* ---- after every render ---------------------------------------------- */
  function afterRender(e) {
    placeBestOf();
    updateSplit(e && e.detail && e.detail.urls);
    eagerFirstRow();
    paintMirror();
    paintFilterCount();
  }
  doc.addEventListener("oria:dir-results", afterRender);
  // The page as drawn, before (or without) app.js.
  placeBestOf();
})();
