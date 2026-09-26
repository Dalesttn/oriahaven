/* ==========================================================================
   Oria Haven v4 -- category page behaviour, "The Wellness Horizon"
   (oria-v4/oria-practice-v2.php, assets/css/v4-category.css).

   Works beside the parent's app.js, never instead of it. app.js still owns
   filtering, sorting, the pager, the map, Save and Compare. Everything the
   Discovery Dock chooses is a real [data-filter] input that app.js binds at
   its own start-up, so its state, counts, chips, "Clear all" and URL
   (svc=, region=, suburb=) are app.js's. This file only:

     1. measures the sticky chrome into CSS variables;
     2. opens the dock's panels -- an anchored, non-modal popover on a wide
        screen; a modal bottom sheet on a phone (focus kept inside, page
        behind it still, Escape / close / veil, focus handed back);
     3. turns a mood into its services (ticks those inputs), and derives
        the dock's labels from what is ticked -- so a mood has no URL of its
        own, and a reload or "Clear all" can never leave a label lying;
     4. shows the Discovery Ribbon once the dock has scrolled away
        (IntersectionObserver -- no per-pixel scroll work, fixed, no jump);
     5. after every app.js render (the "oria:dir-results" event): places
        the Oria Note after the sixth listing and Local intelligence after
        the page, recounts specialists / also offering, writes the live
        count into the dock and ribbon, adds a match reason to each card
        when a feeling or experience is chosen, loads the first pictures
        eagerly;
     6. phones: Filters · Map · Sort at the top of the results, the whole
        filter set in a bottom sheet with Clear all and Show N.

   Deferred, so it runs before DOMContentLoaded -- before app.js binds its
   inputs and draws its first render.
   ========================================================================== */
(function () {
  "use strict";

  var doc = document;
  var docEl = doc.documentElement;
  function $(s, c) { return (c || doc).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); }

  var root = $("#dirResults");
  // Category pages (data-mode="category") and the Explore hub (app.js's
  // directory mode): both carry the Horizon hero.
  if (!root || !$(".xc-hz")) return;
  var CAT = root.getAttribute("data-mode") === "category";

  var phone = window.matchMedia("(max-width: 50rem)");
  var reduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var DATA = window.ORIA_DATA || window.ORIA_SEARCH_DATA || {};
  var LOCK_KEY = root.getAttribute("data-intent-key") || "";
  var LOCK_VAL = root.getAttribute("data-intent-value") || "";
  var ribbon = $("#xcRibbon");
  var bar = $("#dirFilters");

  function fire(el, type) { el.dispatchEvent(new Event(type, { bubbles: true })); }
  function isLocked(input) {
    // Several values may be locked at once ("traditional-sauna,infrared-sauna").
    return input.getAttribute("data-filter") === LOCK_KEY && LOCK_VAL.split(",").indexOf(input.value) > -1;
  }

  /* ---- 1. sticky chrome ------------------------------------------------ */
  function headHeight() {
    var head = $(".site-head--solid") || $(".site-head");
    if (!head) return 0;
    var cs = getComputedStyle(head);
    // Where the header sits once stuck: its sticky offset plus its height
    // (not its position now -- at the top of the page a bar sits above it).
    return cs.position === "sticky" || cs.position === "fixed" ? Math.round((parseFloat(cs.top) || 0) + head.offsetHeight) : 0;
  }
  function ribbonHeight() { return ribbon && ribbon.classList.contains("is-on") ? ribbon.offsetHeight : 0; }
  function measure() {
    docEl.style.setProperty("--xc-head-h", headHeight() + "px");
    if (ribbon) docEl.style.setProperty("--xc-ribbon-h", ribbon.offsetHeight + "px");
    if (bar) docEl.style.setProperty("--xc-bar-h", bar.offsetHeight + "px");
  }
  measure();
  window.addEventListener("resize", measure);
  window.addEventListener("load", measure);

  function scrollToResults() {
    var head = $("#results") || root;
    var top = head.getBoundingClientRect().top + window.pageYOffset - headHeight() - (ribbon ? ribbon.offsetHeight : 0) - 12;
    window.scrollTo({ top: Math.max(0, top), behavior: reduced ? "auto" : "smooth" });
    head.setAttribute("tabindex", "-1");
    head.focus({ preventScroll: true });
  }

  /* ---- 2. panels: popover (wide) / bottom sheet (phone) ---------------- */
  var openPanel = null, openTrigger = null, veil = null;

  function focusables(el) {
    return $$('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', el)
      .filter(function (n) { return n.offsetParent !== null || n === doc.activeElement; });
  }
  function setExpanded(id, on) {
    $$('[data-xc-open="' + id + '"]').forEach(function (b) { b.setAttribute("aria-expanded", on ? "true" : "false"); });
  }
  function lock(on) {
    docEl.classList.toggle("xc-locked", on);
  }
  function position(panel, trigger) {
    panel.style.top = "";
    panel.style.left = "";
    var r = trigger.getBoundingClientRect();
    var vw = docEl.clientWidth;
    var w = panel.offsetWidth;
    var left = Math.min(Math.max(16, r.left), Math.max(16, vw - w - 16));
    if (trigger.closest(".xc-ribbon")) {
      panel.classList.add("is-fixed");
      panel.style.top = Math.round(r.bottom + 8) + "px";
      panel.style.left = Math.round(left) + "px";
    } else {
      panel.classList.remove("is-fixed");
      var host = (panel.offsetParent || panel.parentNode).getBoundingClientRect();
      panel.style.top = Math.round(r.bottom - host.top + 8) + "px";
      panel.style.left = Math.round(left - host.left) + "px";
    }
  }
  function openPop(panel, trigger) {
    if (openPanel && openPanel !== panel) closePop(false);
    closeFilterSheet(false);
    panel.hidden = false;
    openPanel = panel;
    openTrigger = trigger;
    setExpanded(panel.id, true);
    if (phone.matches) {
      panel.setAttribute("aria-modal", "true");
      panel.classList.remove("is-fixed");
      panel.style.top = panel.style.left = "";
      if (!veil) {
        veil = doc.createElement("div");
        veil.className = "xc-veil";
        veil.addEventListener("click", function () { closePop(true); });
      }
      panel.parentNode.insertBefore(veil, panel);
      lock(true);
    } else {
      panel.removeAttribute("aria-modal");
      position(panel, trigger);
    }
    var first = $('.xc-mood[aria-pressed="true"]', panel) || focusables($(".xc-pop__body", panel) || panel)[0] || $(".xc-pop__x", panel);
    if (first) first.focus({ preventScroll: true });
    if (!phone.matches && panel.getBoundingClientRect().bottom > window.innerHeight) {
      panel.scrollIntoView({ block: "nearest", behavior: reduced ? "auto" : "smooth" });
    }
  }
  function closePop(returnFocus) {
    if (!openPanel) return;
    var panel = openPanel, trigger = openTrigger;
    panel.hidden = true;
    panel.removeAttribute("aria-modal");
    setExpanded(panel.id, false);
    if (veil && veil.parentNode) veil.parentNode.removeChild(veil);
    lock(false);
    openPanel = null;
    openTrigger = null;
    if (returnFocus && trigger && doc.body.contains(trigger) && trigger.offsetParent !== null) {
      trigger.focus({ preventScroll: true });
    }
  }

  doc.addEventListener("click", function (e) {
    var t = e.target;
    if (!t.closest) return;
    var opener = t.closest("[data-xc-open]");
    if (opener) {
      var panel = doc.getElementById(opener.getAttribute("data-xc-open"));
      if (!panel) return;
      e.preventDefault();
      if (openPanel === panel) closePop(true);
      else openPop(panel, opener);
      return;
    }
    if (t.closest("[data-xc-close]")) { closePop(true); return; }
    // A click outside a wide-screen popover closes it (the sheet has a veil).
    if (openPanel && !phone.matches && !openPanel.contains(t)) closePop(false);
  });
  doc.addEventListener("keydown", function (e) {
    // An open filter sheet from app.js takes Escape first.
    if (docEl.classList.contains("has-sheet")) return;
    if (e.key === "Escape") {
      if (openPanel) { e.preventDefault(); closePop(true); return; }
      if (fOpen) { e.preventDefault(); closeFilterSheet(true); return; }
    }
    if (e.key !== "Tab") return;
    // Modal sheets keep focus inside; the wide-screen popover does not trap.
    var trap = (openPanel && phone.matches) ? openPanel : (fOpen ? fPanel : null);
    if (!trap) return;
    var f = focusables(trap);
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
    else if (!trap.contains(doc.activeElement)) { e.preventDefault(); first.focus(); }
  });
  // Tabbing out of a wide-screen popover closes it, quietly.
  doc.addEventListener("focusin", function (e) {
    if (!openPanel || phone.matches) return;
    if (openPanel.contains(e.target) || e.target === openTrigger) return;
    if (e.target.closest && e.target.closest("[data-xc-open]")) return;
    closePop(false);
  });
  function onBreakpoint() {
    closePop(false);
    closeFilterSheet(false);
    measure();
  }
  if (phone.addEventListener) phone.addEventListener("change", onBreakpoint);
  else if (phone.addListener) phone.addListener(onBreakpoint);
  window.addEventListener("resize", function () {
    if (openPanel && !phone.matches && openTrigger) position(openPanel, openTrigger);
  });

  // "Show N places": close, then take the visitor to the results.
  doc.addEventListener("click", function (e) {
    var go = e.target.closest && e.target.closest("[data-xc-show]");
    if (!go) return;
    e.preventDefault();
    closePop(false);
    closeFilterSheet(false);
    scrollToResults();
  });

  /* ---- 3. moods, experiences, location ---------------------------------- */
  /* What the dock filters by: services (svc=) on a category page,
     categories (cat=) on the hub -- whichever its lists carry. */
  var KIND = (function () {
    var i = $("[data-xc-exp-list] input[data-filter]") || $("#xcWays input[data-filter]");
    return i ? i.getAttribute("data-filter") : "svc";
  })();
  var expBoxes = $$('[data-xc-exp-list] input[data-filter="' + KIND + '"]');
  var moodBtns = $$(".xc-mood");
  var activeMood = null;
  function moodItems(btn) { return (btn.getAttribute("data-items") || "").split(",").filter(Boolean); }
  function svcBoxes() {
    // One input per service: the Experience list when there is one, else
    // the moods' own lists.
    if (expBoxes.length) return expBoxes;
    var seen = {}, out = [];
    $$('#xcWays input[data-filter="' + KIND + '"]').forEach(function (b) { if (!seen[b.value]) { seen[b.value] = 1; out.push(b); } });
    return out;
  }
  function ticked() {
    return svcBoxes().filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
  }
  /* Tick exactly `want` among the dock's services. Each change goes through
     app.js's own handler (state, syncInputs, render), one at a time. */
  function setSvc(want) {
    svcBoxes().forEach(function (b) {
      var on = want.indexOf(b.value) > -1;
      if (b.checked !== on) { b.checked = on; fire(b, "change"); }
    });
  }
  moodBtns.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var slug = btn.getAttribute("data-xc-mood");
      if (activeMood === slug) {
        activeMood = null;
        setSvc([]);
      } else {
        activeMood = slug;
        setSvc(moodItems(btn));
        if (window.dataLayer && window.dataLayer.push) {
          window.dataLayer.push({ event: "category_mood_select", mood: slug, page_category: root.getAttribute("data-cat") || "" });
        }
      }
      paintDock();
    });
  });
  function deriveMood() {
    var on = ticked().sort();
    if (!on.length) { activeMood = null; return; }
    if (activeMood) {
      var cur = moodBtns.filter(function (b) { return b.getAttribute("data-xc-mood") === activeMood; })[0];
      if (cur && on.every(function (s) { return moodItems(cur).indexOf(s) > -1; })) return;
    }
    activeMood = null;
    moodBtns.forEach(function (b) {
      if (!activeMood && moodItems(b).sort().join(",") === on.join(",")) activeMood = b.getAttribute("data-xc-mood");
    });
  }

  // Popular shortcuts: real links (crawlable, and they work without
  // scripting); with scripting they toggle their service in place.
  $$(".xc-pchip[data-xc-svc]").forEach(function (a) {
    var box = expBoxes.filter(function (b) { return b.value === a.getAttribute("data-xc-svc"); })[0];
    if (!box) return;
    a.setAttribute("role", "button");
    a.setAttribute("aria-pressed", box.checked ? "true" : "false");
    a.addEventListener("click", function (e) {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button > 0) return;
      e.preventDefault();
      box.checked = !box.checked;
      fire(box, "change");
    });
    a.addEventListener("keydown", function (e) {
      if (e.key === " ") { e.preventDefault(); a.click(); }
    });
  });

  // Clear, inside a panel: only that panel's choices.
  doc.addEventListener("click", function (e) {
    var c = e.target.closest && e.target.closest("[data-xc-clear]");
    if (!c) return;
    var panel = c.closest(".xc-pop");
    if (!panel) return;
    if (panel.id === "xcWays") { activeMood = null; setSvc([]); }
    else {
      $$("input[data-filter]", panel).forEach(function (b) {
        if (b.checked && !isLocked(b)) { b.checked = false; fire(b, "change"); }
      });
    }
    paintDock();
  });

  // "Sort by distance from me": the toolbar's own location button.
  doc.addEventListener("click", function (e) {
    var n = e.target.closest && e.target.closest("[data-xc-near]");
    if (!n) return;
    var near = $("[data-near]");
    closePop(false);
    if (near) near.click();
    scrollToResults();
  });
  if (!$("[data-near]") || !("geolocation" in navigator)) {
    $$("[data-xc-near]").forEach(function (b) { b.hidden = true; });
  }

  function labelOf(input) {
    var l = input.closest("label");
    var s = l && $(".xc-check__label", l);
    return s ? s.textContent.trim() : input.value;
  }
  function expSummary() {
    var on = svcBoxes().filter(function (b) { return b.checked; });
    if (!on.length) return "";
    return on.length === 1 ? labelOf(on[0]) : labelOf(on[0]) + " + " + (on.length - 1) + " more";
  }
  // The labels as the server drew them ("All experiences" / "All categories").
  function initial(key, fallback) { var v = $('[data-xc-val="' + key + '"]'); return v ? v.textContent.trim() : fallback; }
  var expDefault = initial("exp", "All experiences");
  var ribbonDefault = initial("ribbon", expDefault);
  var moodDefault = initial("mood", "Any feeling");
  var locDefault = (function () { var v = $('[data-xc-val="loc"]'); return v ? v.textContent.trim() : ""; })();
  function locSummary() {
    var seen = {}, on = [];
    $$('#xcLoc input[data-filter]').forEach(function (b) {
      if (b.checked && !seen[b.value]) { seen[b.value] = 1; on.push(b); }
    });
    if (!on.length) return locDefault;
    return on.length === 1 ? labelOf(on[0]) : on.length + " areas";
  }
  function moodName() {
    var b = moodBtns.filter(function (x) { return x.getAttribute("data-xc-mood") === activeMood; })[0];
    return b ? b.getAttribute("data-xc-mood-name") : "";
  }
  function liveCount() {
    var c = $("#dirCount");
    var m = c ? c.textContent.match(/\d[\d,]*/) : null;
    return m ? m[0] : null;
  }
  function setVal(key, text) { $$('[data-xc-val="' + key + '"]').forEach(function (v) { v.textContent = text; }); }

  /* The chip row follows the dock: a feeling offers its categories, one
     category offers what is inside it, anything else the popular four.
     Every set is server-drawn; this only chooses which one shows. */
  var ctxSets = $$("[data-xc-ctx]");
  function paintContext() {
    if (!ctxSets.length) return;
    var on = ticked();
    var want = activeMood && on.length > 1 ? "mood:" + activeMood : (on.length === 1 ? "cat:" + on[0] : "default");
    if (!ctxSets.some(function (s) { return s.getAttribute("data-xc-ctx") === want; })) want = "default";
    ctxSets.forEach(function (s) { s.hidden = s.getAttribute("data-xc-ctx") !== want; });
  }
  // A feeling's category chip narrows the dock to that one category in place
  // (its link still works for a new tab, or without scripting).
  doc.addEventListener("click", function (e) {
    var a = e.target.closest && e.target.closest("a[data-xc-only]");
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button > 0) return;
    var slug = a.getAttribute("data-xc-only");
    if (!svcBoxes().some(function (b) { return b.value === slug; })) return;
    e.preventDefault();
    setSvc([slug]);
    paintDock();
  });

  function paintDock() {
    paintContext();
    moodBtns.forEach(function (b) { b.setAttribute("aria-pressed", b.getAttribute("data-xc-mood") === activeMood ? "true" : "false"); });
    $$("[data-xc-mood-detail]").forEach(function (d) { d.hidden = d.getAttribute("data-xc-mood-detail") !== activeMood; });
    var mood = moodName(), exp = expSummary();
    if (moodBtns.length) setVal("mood", mood || moodDefault);
    setVal("exp", exp || expDefault);
    setVal("loc", locSummary());
    setVal("ribbon", mood || exp || ribbonDefault);
    $$(".xc-pchip[data-xc-svc][role=button]").forEach(function (a) {
      var box = expBoxes.filter(function (b) { return b.value === a.getAttribute("data-xc-svc"); })[0];
      if (box) a.setAttribute("aria-pressed", box.checked ? "true" : "false");
    });
    var n = liveCount();
    if (n !== null) $$("[data-xc-count]").forEach(function (b) { b.textContent = n; });
    paintFilterCount();
  }

  /* ---- 4. the Discovery Ribbon ------------------------------------------ */
  var dock = $("#xcDock");
  var end = $(".xc-browse-end");
  /* On while the dock is above the header line and the results are not yet
     behind it. The observers only say "something crossed"; the answer is
     read from the two positions then, so a jump straight from the top of
     the page to its foot (which crosses nothing observable) still lands
     right. */
  function paintRibbon() {
    if (!ribbon || !dock) return;
    var line = headHeight();
    var on = dock.getBoundingClientRect().bottom <= line && (!end || end.getBoundingClientRect().top > line);
    ribbon.classList.toggle("is-on", on);
    if (!on && openTrigger && openTrigger.closest(".xc-ribbon")) closePop(false);
  }
  if (ribbon && dock && "IntersectionObserver" in window) {
    doc.body.classList.add("xc-has-ribbon");
    var io = new IntersectionObserver(paintRibbon, { rootMargin: "-" + headHeight() + "px 0px 0px 0px" });
    io.observe(dock);
    io.observe($("#browse") || dock);
    if (end) io.observe(end);
  } else if (ribbon) {
    ribbon.hidden = true;
  }
  // Ribbon: Filters and Map.
  doc.addEventListener("click", function (e) {
    var t = e.target.closest && e.target.closest("[data-xc-filters], [data-xc-map]");
    if (!t) return;
    closePop(false);
    if (t.hasAttribute("data-xc-map")) {
      var browse = $("#browse");
      var want = browse && browse.classList.contains("is-map") ? "list" : "map";
      var sw = $('.viewswitch [data-view="' + want + '"]');
      t.focus({ preventScroll: true });
      if (sw) sw.click();
      return;
    }
    if (phone.matches && fPanel) { openFilterSheet(t); return; }
    // Wide screens: the filter row is at the top of the results.
    var y = bar ? bar.getBoundingClientRect().top + window.pageYOffset - headHeight() - (ribbon ? ribbon.offsetHeight : 0) - 12 : 0;
    window.scrollTo({ top: Math.max(0, y), behavior: reduced ? "auto" : "smooth" });
    var first = bar && focusables(bar)[0];
    if (first) first.focus({ preventScroll: true });
  });

  /* ---- 5. after every render ------------------------------------------- */
  var homes = {};
  ["xcNote", "xcLocal"].forEach(function (id) {
    var el = doc.getElementById(id);
    if (!el) return;
    homes[id] = { el: el, mark: doc.createComment(id + "-home") };
    el.parentNode.insertBefore(homes[id].mark, el);
  });
  function onFirstPage() {
    var cur = $(".pager__num.is-current");
    return cur ? (parseInt(cur.getAttribute("data-page"), 10) || 1) === 1 : true;
  }
  function putHome(h) {
    if (h.mark.parentNode && h.mark.nextSibling !== h.el) h.mark.parentNode.insertBefore(h.el, h.mark.nextSibling);
    h.el.classList.remove("is-inline");
  }
  function placeInline() {
    var cards = $$("#dirResults > article.listing:not(.xc-twin)");
    var first = onFirstPage();
    // The Oria Note after the sixth listing, with more to follow.
    if (homes.xcNote) {
      if (first && cards.length > 6) {
        if (cards[5].nextElementSibling !== homes.xcNote.el) cards[5].insertAdjacentElement("afterend", homes.xcNote.el);
        homes.xcNote.el.classList.add("is-inline");
      } else putHome(homes.xcNote);
    }
    // Local intelligence after the page's listings.
    if (homes.xcLocal) {
      if (first && cards.length > 6) {
        // After the first run of ten (the hub's list grows with "load more").
        var last = cards[Math.min(cards.length, 10) - 1];
        if (last.nextElementSibling !== homes.xcLocal.el) last.insertAdjacentElement("afterend", homes.xcLocal.el);
        homes.xcLocal.el.classList.add("is-inline");
      } else putHome(homes.xcLocal);
    }
  }

  var split = $("#xcSplit");
  var FAMILY = (root.getAttribute("data-family") || "").split(" ").filter(Boolean);
  var byUrl = null;
  function listingFor(url) {
    if (!byUrl) {
      byUrl = {};
      (DATA.listings || []).forEach(function (l) { if (l && l.url) byUrl[l.url] = l; });
    }
    return byUrl[url];
  }
  function updateSplit(urls) {
    if (!split || !urls || !FAMILY.length) return;
    var spec = 0;
    urls.forEach(function (u) { var l = listingFor(u); if (l && FAMILY.indexOf(l.cat) > -1) spec++; });
    var other = urls.length - spec;
    split.hidden = !(spec && other);
    var a = $('[data-xc-split="spec"]', split), b = $('[data-xc-split="also"]', split);
    if (a) a.textContent = String(spec);
    if (b) b.textContent = String(other);
  }

  var svcName = {};
  (KIND === "cat" ? (DATA.categories || []) : (Array.isArray(DATA.services) ? DATA.services : [])).forEach(function (s) { if (s && s.id) svcName[s.id] = s.name; });
  function activeSvc() {
    var seen = {}, out = [];
    $$('input[data-filter="' + KIND + '"]:checked').forEach(function (b) {
      if (seen[b.value] || isLocked(b)) return;
      seen[b.value] = 1;
      out.push(b.value);
    });
    return out;
  }
  /* "Matches Recover: Traditional sauna, Ice bath" -- only the services this
     listing really carries among those chosen. */
  function paintMatches() {
    var on = activeSvc();
    var mood = moodName();
    $$("#dirResults > article.listing").forEach(function (art) {
      var old = $(".xc-match", art);
      if (old) old.parentNode.removeChild(old);
      art.classList.remove("xc-has-match");
      if (!on.length) return;
      var a = $(".listing__name a", art);
      var l = a ? listingFor(a.getAttribute("href")) : null;
      if (!l) return;
      var own = KIND === "cat" ? [l.cat].concat(l.also || []) : (l.svc || []);
      var hits = own.filter(function (s) { return on.indexOf(s) > -1; });
      if (!hits.length) return;
      var p = doc.createElement("p");
      p.className = "xc-match";
      var names = hits.filter(function (s, i) { return hits.indexOf(s) === i; }).map(function (s) { return svcName[s] || s; }).join(", ");
      if (mood) {
        p.appendChild(doc.createTextNode("Matches "));
        var b = doc.createElement("b");
        b.textContent = mood;
        p.appendChild(b);
        p.appendChild(doc.createTextNode(": " + names));
      } else {
        p.appendChild(doc.createTextNode((KIND === "cat" ? "In " : "Offers ") + names));
      }
      var desc = $(".listing__desc", art);
      if (desc) desc.parentNode.insertBefore(p, desc);
      art.classList.add("xc-has-match");
    });
  }

  // "View place" (brief 8.3) on the cards' one strong action.
  function relabel() {
    $$("#browse article.listing a.btn--dark").forEach(function (a) {
      var n = a.firstChild;
      if (n && n.nodeType === 3 && /View profile/.test(n.nodeValue)) n.nodeValue = n.nodeValue.replace("View profile", "View place");
    });
  }
  /* The first row of results, eagerly -- but only where the first row is
     actually near the top. On a category page the results start high and
     this wins a little perceived speed; on the Explore hub they start
     around 1500px down, behind a hero, a filter bar and a feelings row,
     and promoting them just pulled two 1600px photographs off the network
     ahead of things the visitor could actually see. */
  function eagerFirstRow() {
    var reach = window.innerHeight * 1.2;
    $$("#dirResults > article.listing img").forEach(function (img, i) {
      if (i > 1) return;
      var box = img.getBoundingClientRect();
      /* A hidden card measures 0x0 at top 0, which reads as "at the very
         top of the page" and passed a proximity test on its own. */
      if (box.height > 0 && box.top < reach) img.loading = "eager";
    });
  }

  /* The hub's one Featured card. On a category page app.js keeps it out of
     the list itself (#featBand); the hub's directory mode does not, so the
     card's twin is hidden here while the card shows -- on the page as it
     arrived, before any filter or re-sort, exactly as the category band. */
  var band = !CAT ? $("#featBand") : null;
  var bandLink = band ? $(".listing__name a", band) : null;
  var bandUrl = bandLink ? bandLink.getAttribute("href") : "";
  function paintBand() {
    if (!band) return;
    var sort = $("#dirSort");
    var on = activeFilters() === 0 && (!sort || sort.value === "relevance");
    band.hidden = !on;
    $$("#dirResults > article.listing").forEach(function (art) {
      var a = $(".listing__name a", art);
      art.classList.toggle("xc-twin", on && !!a && a.getAttribute("href") === bandUrl);
    });
  }

  function afterRender(e) {
    deriveMood();
    paintBand();
    placeInline();
    updateSplit(e && e.detail && e.detail.urls);
    paintMatches();
    relabel();
    eagerFirstRow();
    paintDock();
  }
  doc.addEventListener("oria:dir-results", afterRender);
  placeInline();
  relabel();

  /* ---- 6. phones: Filters · Map · Sort, and the filter sheet ------------ */
  var fBtn = null, fPanel = null, fCount = null, fVeil = null, fOpen = false, fTrigger = null;
  function activeFilters() {
    var seen = {}, n = 0;
    $$("[data-filter]:checked").forEach(function (i) {
      var k = i.getAttribute("data-filter") + ":" + i.value;
      if (seen[k] || isLocked(i)) return;
      seen[k] = 1;
      n++;
    });
    var q = $("#dirQ");
    if (q && q.value.trim()) n++;
    var picks = $("[data-best-toggle]");
    if (picks && picks.getAttribute("aria-pressed") === "true") n++;
    return n;
  }
  function paintFilterCount() {
    var n = activeFilters();
    var txt = n ? " · " + n : "";
    if (fCount) fCount.textContent = txt;
    if (fBtn) fBtn.setAttribute("aria-label", n ? "Filters, " + n + " active" : "Filters");
    $$("[data-xc-fcount]").forEach(function (s) { s.textContent = txt; });
    $$("[data-xc-filters]").forEach(function (b) { b.setAttribute("aria-label", n ? "Filters, " + n + " active" : "Filters"); });
  }
  function openFilterSheet(trigger) {
    if (!fPanel) return;
    closePop(false);
    fOpen = true;
    fTrigger = trigger || fBtn;
    bar.classList.add("xc-open");
    if (fBtn) fBtn.setAttribute("aria-expanded", "true");
    fPanel.setAttribute("role", "dialog");
    fPanel.setAttribute("aria-modal", "true");
    fPanel.setAttribute("aria-label", "Filters");
    if (!fVeil) {
      fVeil = doc.createElement("div");
      fVeil.className = "xc-fveil";
      fVeil.addEventListener("click", function () { closeFilterSheet(true); });
    }
    bar.insertBefore(fVeil, fPanel);
    lock(true);
    var first = focusables(fPanel)[0];
    if (first) first.focus({ preventScroll: true });
  }
  function closeFilterSheet(returnFocus) {
    if (!fOpen) return;
    fOpen = false;
    bar.classList.remove("xc-open");
    if (fBtn) fBtn.setAttribute("aria-expanded", "false");
    fPanel.removeAttribute("role");
    fPanel.removeAttribute("aria-modal");
    fPanel.removeAttribute("aria-label");
    if (fVeil && fVeil.parentNode) fVeil.parentNode.removeChild(fVeil);
    lock(false);
    if (returnFocus && fTrigger && fTrigger.offsetParent !== null) fTrigger.focus({ preventScroll: true });
    fTrigger = null;
  }
  function clearAll() {
    $$("[data-filter]:checked").forEach(function (i) {
      if (i.checked && !isLocked(i)) { i.checked = false; fire(i, "change"); }
    });
    $$("[data-goodfor-opt]:checked").forEach(function (i) { i.checked = false; fire(i, "change"); });
    var q = $("#dirQ");
    if (q && q.value) { q.value = ""; fire(q, "input"); }
    var picks = $("[data-best-toggle]");
    if (picks && picks.getAttribute("aria-pressed") === "true") picks.click();
    activeMood = null;
    paintDock();
  }
  function buildPhoneBar() {
    if (!bar) return;
    var search = $(".toolbar__search", bar);
    var filters = $(".toolbar__filters", bar);
    var near = $(".toolbar__near", bar);
    if (!filters) return;

    /* Search, the filter pills and "Use my location", wrapped once. On a
       wide screen the wrapper is display:contents, so the desktop row is as
       it was; everything stays inside #dirFilters, where app.js looks. */
    fPanel = doc.createElement("div");
    fPanel.className = "xc-fpanel";
    fPanel.id = "xcFilterPanel";
    bar.insertBefore(fPanel, search || filters);
    var head = doc.createElement("div");
    head.className = "xc-fhead";
    head.innerHTML = '<b>Filters</b><button type="button" class="xc-pop__x" aria-label="Close filters">&times;</button>';
    head.lastChild.addEventListener("click", function () { closeFilterSheet(true); });
    fPanel.appendChild(head);
    [search, filters, near].forEach(function (el) { if (el) fPanel.appendChild(el); });
    var foot = doc.createElement("div");
    foot.className = "xc-ffoot";
    foot.innerHTML = '<button type="button" class="xc-pop__clear">Clear all</button>' +
      '<a class="btn xc-btn-primary" href="#results" data-xc-show>Show <b data-xc-count></b> results</a>';
    foot.firstChild.addEventListener("click", clearAll);
    fPanel.appendChild(foot);

    var row = doc.createElement("div");
    row.className = "xc-tbrow";
    fBtn = doc.createElement("button");
    fBtn.type = "button";
    fBtn.className = "xc-tbbtn xc-tbbtn--filters";
    fBtn.setAttribute("aria-expanded", "false");
    fBtn.setAttribute("aria-controls", "xcFilterPanel");
    fBtn.appendChild(doc.createTextNode("Filters"));
    fCount = doc.createElement("span");
    fBtn.appendChild(fCount);
    fBtn.addEventListener("click", function () { if (fOpen) closeFilterSheet(true); else openFilterSheet(fBtn); });
    row.appendChild(fBtn);
    if ($('.viewswitch [data-view="map"]')) {
      var mBtn = doc.createElement("button");
      mBtn.type = "button";
      mBtn.className = "xc-tbbtn";
      mBtn.setAttribute("data-xc-map", "");
      mBtn.textContent = "Map";
      row.appendChild(mBtn);
    }
    bar.insertBefore(row, bar.firstChild);
    bar.classList.add("xc-bar");
    paintFilterCount();
  }
  buildPhoneBar();

  doc.addEventListener("input", function (e) {
    if (e.target && e.target.id === "dirQ") paintFilterCount();
  });

  /* ---- the pager lands clear of the header and ribbon ------------------- */
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
      var top = head.getBoundingClientRect().top + window.pageYOffset - headHeight() - (ribbon ? ribbon.offsetHeight : 0) - 12;
      window.scrollTo({ top: Math.max(0, top), behavior: reduced ? "auto" : "smooth" });
    }, 0);
  });
})();
