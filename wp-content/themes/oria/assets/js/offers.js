/* /offers/ and /offers/{activity}/ -- in-place filtering, the small-screen
   filter sheet, and Compare. The page already works without this file: the
   form is a plain GET and the server hides and sorts the cards. This only
   does the same thing without a reload, using the same data-* values the
   server reads (OffersHub\matches / sorted), so the two cannot disagree.

   History: filters are written with replaceState, never pushState, so Back
   leaves the page instead of stepping through every select change, and a
   visitor coming Back from an offer lands on the filtered URL, which the
   server renders filtered.

   Events (dataLayer, as app.js's pushEvent does): offers_filter,
   offers_sort, offers_compare_open. Offer clicks are already counted by
   app.js through data-oria-track="offer". */
(function () {
  "use strict";

  var root = document.querySelector("[data-ofx]");
  if (!root) return;

  var form = root.querySelector("[data-ofx-form]");
  var grid = root.querySelector("[data-ofx-grid]");
  var sheet = root.querySelector("[data-ofx-sheet]");
  var openBtn = root.querySelector("[data-ofx-open]");
  var closeBtn = root.querySelector("[data-ofx-close]");
  var sortSel = root.querySelector("[data-ofx-sort]");
  var countEl = root.querySelector("[data-ofx-count]");
  var badge = root.querySelector("[data-ofx-badge]");
  var chips = root.querySelector("[data-ofx-chips]");
  var picks = root.querySelector("[data-ofx-picks]");
  var empty = root.querySelector("[data-ofx-empty]");
  var emptyText = root.querySelector("[data-ofx-empty-text]");
  var applyBtn = root.querySelector("[data-ofx-apply]");
  var place = root.getAttribute("data-place") || "hub";
  var KEYS = ["area", "budget", "who", "format"];
  var MOBILE = window.matchMedia("(max-width: 759px)");

  if (!form || !grid) return;
  root.classList.add("is-js");

  var cards = Array.prototype.slice.call(grid.querySelectorAll("[data-ofx-card]"));
  var signup = grid.querySelector("[data-ofx-signup]");
  var total = cards.length;
  var data = {};
  try { data = JSON.parse((document.getElementById("ofx-data") || {}).textContent || "{}"); } catch (e) { data = {}; }

  function push(name, params) {
    window.dataLayer = window.dataLayer || [];
    var o = { event: name, page_context: place };
    for (var k in params) if (Object.prototype.hasOwnProperty.call(params, k)) o[k] = params[k];
    window.dataLayer.push(o);
  }

  function field(k) { return form.querySelector('[name="' + k + '"]'); }

  /* What the controls say now. */
  function read() {
    var s = {};
    KEYS.forEach(function (k) { var el = field(k); s[k] = el ? el.value : ""; });
    s.sort = sortSel ? sortSel.value : "recommended";
    return s;
  }
  function write(s) {
    KEYS.forEach(function (k) { var el = field(k); if (el) el.value = s[k] || ""; });
  }
  var applied = read();

  function matches(c, s) {
    return (!s.area || c.getAttribute("data-area") === s.area) &&
      (!s.budget || c.getAttribute("data-budget") === s.budget) &&
      (!s.who || c.getAttribute("data-who") === s.who) &&
      (!s.format || c.getAttribute("data-format") === s.format);
  }
  function countFor(s) {
    return cards.filter(function (c) { return matches(c, s); }).length;
  }
  function num(v) { return parseInt(String(v || "").replace(/-/g, ""), 10) || 0; }
  function sortKey(c, sort) {
    var rank = parseInt(c.getAttribute("data-rank"), 10) || 0;
    if (sort === "price") {
      var a = c.getAttribute("data-amount");
      return [a === "" ? 1 : 0, a === "" ? 0 : parseFloat(a), rank];
    }
    if (sort === "checked") return [-num(c.getAttribute("data-checked")), rank];
    if (sort === "ending") {
      var u = c.getAttribute("data-until");
      return [u ? 0 : 1, num(u), rank];
    }
    return [rank];
  }
  function cmp(a, b) {
    for (var i = 0; i < a.length; i++) { if (a[i] !== b[i]) return a[i] < b[i] ? -1 : 1; }
    return 0;
  }
  function labelOf(k, v) {
    var el = field(k);
    if (!el) return v;
    var o = el.querySelector('option[value="' + v + '"]');
    var name = el.labels && el.labels[0] ? el.labels[0].textContent.trim() : k;
    return name + ": " + (o ? o.textContent.trim() : v);
  }

  function url(s) {
    var u = new URL(form.getAttribute("action"), location.href);
    KEYS.forEach(function (k) { if (s[k]) u.searchParams.set(k, s[k]); });
    if (s.sort && s.sort !== "recommended") u.searchParams.set("sort", s.sort);
    return u.toString();
  }

  function render(s) {
    var order = cards.slice().sort(function (a, b) { return cmp(sortKey(a, s.sort), sortKey(b, s.sort)); });
    var shown = 0;
    order.forEach(function (c) {
      var ok = matches(c, s);
      c.hidden = !ok;
      grid.appendChild(c);
      if (ok) {
        shown++;
        if (shown === 6 && signup) grid.appendChild(signup);
      }
    });
    if (signup && shown < 6) grid.appendChild(signup);
    if (signup) signup.hidden = shown === 0;

    var active = KEYS.filter(function (k) { return s[k]; });
    countEl.textContent = shown === total ? total + (total === 1 ? " offer" : " offers") : shown + " of " + total + " offers";
    if (badge) { badge.textContent = String(active.length); badge.hidden = !active.length; }
    if (picks) picks.hidden = active.length > 0;
    if (empty) {
      empty.hidden = shown > 0;
      if (emptyText) emptyText.textContent = active.length ? "You’re filtering by " + active.map(function (k) { return labelOf(k, s[k]); }).join(", ") + "." : "";
    }
    if (chips) {
      var clear = chips.querySelector("[data-ofx-clear]");
      var clearLi = clear ? clear.parentNode : null;
      chips.innerHTML = "";
      active.forEach(function (k) {
        var li = document.createElement("li");
        var b = document.createElement("button");
        b.type = "button";
        b.className = "ofx-chip";
        b.setAttribute("data-ofx-chip", k);
        b.innerHTML = '<span></span><span class="sr-only"> (remove)</span><span aria-hidden="true" class="ofx-chip__x">×</span>';
        b.firstChild.textContent = labelOf(k, s[k]);
        li.appendChild(b);
        chips.appendChild(li);
      });
      if (clearLi) chips.appendChild(clearLi);
      chips.hidden = !active.length;
    }
    try { history.replaceState(history.state, "", url(s)); } catch (e) { /* file:// or sandboxed */ }
    return shown;
  }

  function apply(s, changed) {
    var shown = render(s);
    if (changed) {
      if (changed === "sort") push("offers_sort", { sort: s.sort, results_count: shown });
      else push("offers_filter", { filter_name: changed, filter_value: s[changed] || "any", results_count: shown });
    }
    applied = s;
  }

  /* ---- desktop: every change applies at once; the sheet waits for Apply. */
  form.addEventListener("change", function (e) {
    var k = e.target && e.target.name;
    if (!k || k === "sort") return;
    if (root.classList.contains("is-sheet")) {
      var n = countFor(read());
      applyBtn.textContent = n === 1 ? "Show 1 offer" : "Show " + n + " offers";
      return;
    }
    apply(read(), k);
  });
  if (sortSel) {
    sortSel.addEventListener("change", function () {
      var s = Object.assign({}, applied, { sort: sortSel.value });
      apply(s, "sort");
    });
  }
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var s = read();
    var changed = KEYS.filter(function (k) { return s[k] !== applied[k]; })[0];
    apply(s, changed || null);
    closeSheet(true);
  });
  root.addEventListener("click", function (e) {
    var chip = e.target.closest && e.target.closest("[data-ofx-chip]");
    var clear = e.target.closest && e.target.closest("[data-ofx-clear]");
    var reset = e.target.closest && e.target.closest("[data-ofx-reset]");
    if (chip) {
      e.preventDefault();
      var k = chip.getAttribute("data-ofx-chip");
      var s = Object.assign({}, applied);
      s[k] = "";
      write(s);
      apply(s, k);
      // Focus stays in the chip row (the next chip, or Clear all); with none left, on the filter control.
      var next = chips && !chips.hidden ? chips.querySelector("button, a") : null;
      (next || openBtn && !openBtn.hidden && openBtn || field(k) || sortSel).focus();
    } else if (clear) {
      e.preventDefault();
      var c = { area: "", budget: "", who: "", format: "", sort: applied.sort };
      write(c);
      apply(c, "clear");
    } else if (reset) {
      e.preventDefault();
      write({});
      if (root.classList.contains("is-sheet")) {
        applyBtn.textContent = "Show " + total + " offers";
      } else {
        apply(Object.assign({}, applied, { area: "", budget: "", who: "", format: "" }), "reset");
      }
    }
  });

  /* ---- small screens: the same form, in a modal bottom sheet. Native
     <dialog> gives the focus trap, Escape and the inert background; this
     adds the scroll lock and puts focus back on the trigger. */
  /* Clean-up runs here, synchronously, not only on the dialog's close event
     (which is queued, and may arrive late in a throttled tab). */
  function afterSheet() {
    document.documentElement.classList.remove("ofx-lock");
    if (applyBtn) applyBtn.textContent = "Apply filters";
    if (root.classList.contains("is-sheet") && openBtn) openBtn.focus();
  }
  function closeSheet(keep) {
    if (!sheet || !root.classList.contains("is-sheet") || !sheet.open) return;
    if (!keep) write(applied);
    sheet.close();
    afterSheet();
  }
  function mode() {
    var mobile = MOBILE.matches && sheet && typeof sheet.showModal === "function";
    root.classList.toggle("is-sheet", !!mobile);
    if (openBtn) openBtn.hidden = !mobile;
    if (closeBtn) closeBtn.hidden = !mobile;
    if (!sheet) return;
    if (mobile) {
      if (sheet.open && !sheet.matches(":modal")) sheet.removeAttribute("open");
    } else {
      if (sheet.open && sheet.matches(":modal")) sheet.close();
      sheet.setAttribute("open", "");
    }
  }
  if (sheet) {
    if (openBtn) {
      openBtn.addEventListener("click", function () {
        sheet.removeAttribute("open");
        sheet.showModal();
        document.documentElement.classList.add("ofx-lock");
        applyBtn.textContent = "Show " + countFor(read()) + " offers";
      });
    }
    if (closeBtn) closeBtn.addEventListener("click", function () { closeSheet(false); });
    sheet.addEventListener("cancel", function (e) { e.preventDefault(); closeSheet(false); });
    sheet.addEventListener("close", function () {
      if (document.documentElement.classList.contains("ofx-lock")) afterSheet();
    });
    sheet.addEventListener("click", function (e) { if (e.target === sheet) closeSheet(false); });
  }
  mode();
  if (MOBILE.addEventListener) MOBILE.addEventListener("change", mode);

  /* ---- Compare: up to three, no account. */
  var tray = root.querySelector("[data-ofx-tray]");
  var trayN = root.querySelector("[data-ofx-tray-n]");
  var dlg = root.querySelector("[data-ofx-cmp]");
  var body = root.querySelector("[data-ofx-cmp-body]");
  var boxes = Array.prototype.slice.call(root.querySelectorAll("[data-ofx-compare]"));
  var MAX = 3;

  if (tray && dlg && typeof dlg.showModal === "function" && boxes.length > 1) {
    root.querySelectorAll("[data-ofx-cmp-label]").forEach(function (l) { l.hidden = false; });
  }
  function picked() { return boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; }); }
  function syncTray() {
    var p = picked();
    boxes.forEach(function (b) {
      b.disabled = !b.checked && p.length >= MAX;
      b.parentNode.title = b.disabled ? "You can compare up to three offers" : "";
    });
    tray.hidden = p.length === 0;
    root.classList.toggle("has-tray", p.length > 0);
    trayN.textContent = p.length === 1 ? "1 offer selected" : p.length + " offers selected";
    var go = tray.querySelector("[data-ofx-cmp-open]");
    go.disabled = p.length < 2;
    go.textContent = p.length < 2 ? "Pick one more" : "Compare " + p.length;
  }
  boxes.forEach(function (b) { b.addEventListener("change", syncTray); });
  function esc(s) { var d = document.createElement("div"); d.textContent = s == null ? "" : String(s); return d.innerHTML; }
  function unknown(v) { return v ? esc(v) : "Not specified — check with the business"; }
  if (tray) {
    tray.querySelector("[data-ofx-cmp-clear]").addEventListener("click", function () {
      boxes.forEach(function (b) { b.checked = false; });
      syncTray();
    });
    tray.querySelector("[data-ofx-cmp-open]").addEventListener("click", function () {
      var p = picked();
      var html = '<div class="ofx-cmp__grid" style="--n:' + p.length + '">';
      p.forEach(function (id) {
        var d = data[id] || {};
        var price = d.price ? esc(d.price) : "";
        html += '<section class="ofx-cmp__col" aria-label="' + esc(d.name) + '">' +
          '<h3><a href="#offer-' + esc(id) + '" data-ofx-jump>' + esc(d.name) + "</a></h3>" +
          "<p>" + esc(d.title) + "</p><dl>" +
          "<div><dt>Upfront price</dt><dd>" + (price ? "<span class=\"ofx-cmp__price\">" + price + "</span>" + (d.fixed ? "" : "<br>Price varies with what you book") : "Not published — check with the business") + (d.basis ? "<br>" + esc(d.basis) : "") + "</dd></div>" +
          "<div><dt>Suburb</dt><dd>" + unknown(d.suburb) + "</dd></div>" +
          "<div><dt>Included</dt><dd>" + unknown((d.includes || []).join(", ")) + "</dd></div>" +
          "<div><dt>Who can buy</dt><dd>" + unknown(d.who) + "</dd></div>" +
          "<div><dt>Ends</dt><dd>" + (d.until ? esc(d.until) : "No end date stated") + "</dd></div>" +
          "<div><dt>Afterwards</dt><dd>" + unknown(d.renewal) + "</dd></div>" +
          "</dl></section>";
      });
      body.innerHTML = html + "</div>";
      dlg.showModal();
      document.documentElement.classList.add("ofx-lock");
      push("offers_compare_open", { compare_count: p.length });
    });
    function closeCmp(returnFocus) {
      if (dlg.open) dlg.close();
      document.documentElement.classList.remove("ofx-lock");
      var go = tray.querySelector("[data-ofx-cmp-open]");
      if (returnFocus && go && !tray.hidden) go.focus();
    }
    dlg.addEventListener("cancel", function (e) { e.preventDefault(); closeCmp(true); });
    dlg.addEventListener("close", function () { document.documentElement.classList.remove("ofx-lock"); });
    root.querySelector("[data-ofx-cmp-close]").addEventListener("click", function () { closeCmp(true); });
    dlg.addEventListener("click", function (e) {
      if (e.target === dlg) closeCmp(true);
      // A business name jumps to its card: focus follows the link, not the tray.
      var jump = e.target.closest && e.target.closest("[data-ofx-jump]");
      if (jump) closeCmp(false);
    });
    syncTray();
  }
})();
