/* ==========================================================================
   Oria Haven — behaviour
   No framework, no build step. Every module is opt-in: it looks for its
   hook in the DOM and does nothing if the page doesn't use it.
   ========================================================================== */
(function () {
  "use strict";

  /* The directory engine gets the full set; everywhere else carries the
     slim index, which now holds the region field the map needs. Falling
     back keeps the map and search working on both. */
  var DATA = window.ORIA_DATA || window.ORIA_SEARCH_DATA ||
    { listings: [], categories: [], regions: [], specialties: [] };
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var ICON = {
    pin: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 14.5s5-4.2 5-8a5 5 0 1 0-10 0c0 3.8 5 8 5 8Z"/><circle cx="8" cy="6.4" r="1.9"/></svg>',
    star: '<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>',
    arrow: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg>',
    x: '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M2.5 2.5l7 7M9.5 2.5l-7 7"/></svg>',
    scales: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v12M3 5h10M4.5 5 2.5 9.5h4zM11.5 5 9.5 9.5h4z"/></svg>',
    tick: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 6.5 12 13 4.5"/></svg>',
    heart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 8.6a4.9 4.9 0 0 0-8.8-3A4.9 4.9 0 0 0 3.2 8.6c0 4.9 8.8 10.2 8.8 10.2s8.8-5.3 8.8-10.2Z"/></svg>'
  };

  /* ------------------------------------------------------------------ *
     Compare selection.

     A shortlist someone builds while browsing, so it cannot live in the
     DOM: the directory re-renders every card on each filter change and
     throws the old ones away, and people move between categories before
     they have picked their three. It lives in localStorage, and the DOM
     is redrawn from it.

     Declared up here because card() reads it while rendering, long before
     initCompareTray() runs.
     ------------------------------------------------------------------ */
  var Compare = (function () {
    var KEY = "oria:compare";
    var MAX = 4;
    var MIN = 2;
    var listeners = [];

    function read() {
      // Private browsing and full quotas both throw on access, not just on
      // write, so every touch is guarded. A visitor with storage disabled
      // gets a shortlist that works until the page unloads.
      try {
        var raw = window.localStorage.getItem(KEY);
        var arr = raw ? JSON.parse(raw) : [];
        return Object.prototype.toString.call(arr) === "[object Array]" ? arr.slice(0, MAX) : [];
      } catch (e) {
        return mem.slice(0, MAX);
      }
    }

    var mem = [];

    function write(arr) {
      mem = arr.slice(0, MAX);
      try {
        window.localStorage.setItem(KEY, JSON.stringify(mem));
      } catch (e) { /* memory-only for this page, which is enough */ }
      listeners.forEach(function (fn) { fn(mem); });
    }

    return {
      MAX: MAX,
      MIN: MIN,
      all: read,
      has: function (slug) { return !!slug && read().indexOf(slug) > -1; },
      /* Listing permalinks are /listing/{slug}/, so the slug is the last
         path segment. Derived rather than added to the directory payload,
         which already ships 331 rows to every visitor. */
      slugOf: function (url) {
        if (!url) return "";
        return String(url).split("?")[0].split("#")[0].replace(/\/+$/, "").split("/").pop() || "";
      },
      toggle: function (slug) {
        if (!slug) return { ok: false, full: false };
        var arr = read();
        var i = arr.indexOf(slug);
        if (i > -1) {
          arr.splice(i, 1);
          write(arr);
          return { ok: true, full: false, on: false };
        }
        if (arr.length >= MAX) return { ok: false, full: true, on: false };
        arr.push(slug);
        write(arr);
        return { ok: true, full: false, on: true };
      },
      clear: function () { write([]); },
      onChange: function (fn) { listeners.push(fn); },
      url: function () {
        var arr = read();
        return arr.length >= MIN ? "/compare/?places=" + arr.map(encodeURIComponent).join(",") : "";
      }
    };
  })();

  function $(s, c) { return (c || document).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (m) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m];
    });
  }

  /* --- Navigation ---------------------------------------------------- */
  function initNav() {
    var drawer = $("#drawer");
    if (!drawer) return;
    var openers = $$("[data-drawer-open]");
    var closers = $$("[data-drawer-close]");
    var last = null;

    function open() {
      last = document.activeElement;
      drawer.classList.add("is-open");
      drawer.removeAttribute("hidden");
      document.body.style.overflow = "hidden";
      var f = drawer.querySelector("a, button");
      if (f) f.focus();
    }
    function close() {
      drawer.classList.remove("is-open");
      document.body.style.overflow = "";
      window.setTimeout(function () { drawer.setAttribute("hidden", ""); }, reduced ? 0 : 340);
      if (last) last.focus();
    }
    openers.forEach(function (b) { b.addEventListener("click", open); });
    closers.forEach(function (b) { b.addEventListener("click", close); });
    drawer.addEventListener("click", function (e) {
      if (e.target.tagName === "A") close();
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && drawer.classList.contains("is-open")) close();
    });
  }

  /* --- Accordions ---------------------------------------------------- */
  function initAccordions() {
    $$(".acc").forEach(function (acc) {
      var items = $$(".acc__item", acc);
      items.forEach(function (item) {
        var btn = $(".acc__btn", item);
        var panel = $(".acc__panel", item);
        if (!btn || !panel) return;
        btn.setAttribute("aria-expanded", item.classList.contains("is-open") ? "true" : "false");
        btn.addEventListener("click", function () {
          var willOpen = !item.classList.contains("is-open");
          if (acc.dataset.single !== "false") {
            items.forEach(function (o) {
              o.classList.remove("is-open");
              var ob = $(".acc__btn", o);
              if (ob) ob.setAttribute("aria-expanded", "false");
            });
          }
          item.classList.toggle("is-open", willOpen);
          btn.setAttribute("aria-expanded", willOpen ? "true" : "false");
        });
      });
    });
  }

  /* --- Scroll reveal ------------------------------------------------- */
  /* Scroll position drives this rather than IntersectionObserver: the
     animation is a nicety, but content being visible is not, and a plain
     rect check can't be defeated by an observer that never fires. */
  function initReveal() {
    var els = $$(".reveal");
    if (!els.length) return;
    if (reduced) {
      els.forEach(function (el) { el.classList.add("is-in"); });
      return;
    }

    var pending = els.slice();
    var throttled = false;

    function check() {
      throttled = false;
      var h = window.innerHeight || document.documentElement.clientHeight;
      pending = pending.filter(function (el) {
        if (el.getBoundingClientRect().top < h * 0.92) {
          el.classList.add("is-in");
          return false;
        }
        return true;
      });
      if (!pending.length) {
        window.removeEventListener("scroll", queue);
        window.removeEventListener("resize", queue);
      }
    }
    /* Throttled with a timer rather than requestAnimationFrame: rAF is
       suspended in background tabs, and a suspended reveal means a blank
       page when the tab comes forward. */
    function queue() {
      if (throttled) return;
      throttled = true;
      window.setTimeout(check, 60);
    }

    window.addEventListener("scroll", queue, { passive: true });
    window.addEventListener("resize", queue);
    window.addEventListener("load", check);
    check();

    // Last resort: nothing on this page stays hidden, whatever happens.
    window.setTimeout(function () {
      els.forEach(function (el) { el.classList.add("is-in"); });
    }, 2500);
  }

  /* --- The Stillness Map --------------------------------------------- */
  /* Regions are real groupings of Perth suburbs; the dot size and the
     count both read from the listing data, so the map is a view of the
     directory rather than an illustration of it. */
  function initMap() {
    var map = $("#stillmap");
    if (!map) return;

    var counts = {};
    DATA.listings.forEach(function (l) { counts[l.region] = (counts[l.region] || 0) + 1; });

    $$(".region", map).forEach(function (g) {
      var id = g.dataset.region;
      var c = counts[id] || 0;
      var label = $(".region__count", g);
      if (label) label.textContent = c + (c === 1 ? " place" : " places");
      var halo = $(".region__halo", g);
      if (halo) halo.setAttribute("r", String(16 + Math.min(c, 8) * 3.4));
    });

    var panel = $("#mapPanel");
    var regionMeta = {};
    DATA.regions.forEach(function (r) { regionMeta[r.id] = r; });

    function show(id) {
      $$(".region", map).forEach(function (g) { g.classList.toggle("is-active", g.dataset.region === id); });
      if (!panel) return;
      var r = regionMeta[id];
      if (!r) return;
      var here = DATA.listings.filter(function (l) { return l.region === id; });
      var cats = {};
      here.forEach(function (l) { cats[l.cat] = (cats[l.cat] || 0) + 1; });
      var catNames = {};
      DATA.categories.forEach(function (c) { catNames[c.id] = c.name; });
      var rows = Object.keys(cats).sort(function (a, b) { return cats[b] - cats[a]; }).slice(0, 4);

      panel.innerHTML =
        '<span class="micro">' + esc(r.name) + "</span>" +
        '<h3 class="h2" style="margin-top:.75rem">' + here.length + " place" + (here.length === 1 ? "" : "s") + " to discover</h3>" +
        '<p class="lede" style="margin-top:1rem">' + esc(r.suburbs.slice(0, 5).join(" · ")) + "</p>" +
        '<div class="stillmap__list">' +
        rows.map(function (k) {
          return '<a class="stillmap__row" href="/directory/?cat=' + encodeURIComponent(k) + "&region=" + encodeURIComponent(id) + '">' +
            "<b>" + esc(catNames[k] || k) + "</b><span>" + cats[k] + " listed &nbsp;&rarr;</span></a>";
        }).join("") +
        "</div>" +
        '<a class="btn btn--ghost-on-deep" style="margin-top:1.75rem" href="/directory/?region=' + encodeURIComponent(id) + '">' +
        "Browse " + esc(r.name) + '<span class="btn__dot">' + ICON.arrow + "</span></a>";
    }

    $$(".region", map).forEach(function (g) {
      g.addEventListener("mouseenter", function () { show(g.dataset.region); });
      g.addEventListener("focus", function () { show(g.dataset.region); });
      g.addEventListener("click", function () {
        window.location.href = "/directory/?region=" + encodeURIComponent(g.dataset.region);
      });
      g.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          window.location.href = "/directory/?region=" + encodeURIComponent(g.dataset.region);
        }
      });
    });

    show(map.dataset.default || "central");
  }

  /* --- Modern select --------------------------------------------------- */
  /* Native <select> popups are OS-drawn and clash with the design. A
     select marked data-nice gets a styled listbox in the brand language;
     the native control stays in the DOM (visually hidden) as the source
     of truth, so forms and scripts keep reading its value untouched. */
  function initNiceSelects() {
    $$("select[data-nice]").forEach(function (sel) {
      var wrap = document.createElement("span");
      wrap.className = "nsel";
      sel.parentNode.insertBefore(wrap, sel);
      wrap.appendChild(sel);
      sel.classList.add("nsel__native");
      sel.setAttribute("aria-hidden", "true");
      sel.tabIndex = -1;

      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "nsel__btn";
      btn.id = (sel.id || "nsel") + "Btn";
      btn.setAttribute("aria-haspopup", "listbox");
      btn.setAttribute("aria-expanded", "false");
      btn.innerHTML = "<span></span>";
      wrap.appendChild(btn);

      // The field label pointed at the select; point it at the button.
      var label = sel.id ? document.querySelector('label[for="' + sel.id + '"]') : null;
      if (label) label.htmlFor = btn.id;

      var list = document.createElement("div");
      list.className = "nsel__list";
      list.setAttribute("role", "listbox");
      list.hidden = true;
      wrap.appendChild(list);

      var opts = [];
      Array.prototype.forEach.call(sel.options, function (o, i) {
        var el = document.createElement("div");
        el.className = "nsel__opt";
        el.id = btn.id + "-opt-" + i;
        el.setAttribute("role", "option");
        el.textContent = o.textContent;
        el.dataset.value = o.value;
        list.appendChild(el);
        opts.push(el);
      });

      var active = -1;

      function label_for_value() {
        var o = sel.options[sel.selectedIndex];
        return o ? o.textContent : "";
      }
      function paint() {
        btn.firstChild.textContent = label_for_value();
        opts.forEach(function (el) {
          el.setAttribute("aria-selected", el.dataset.value === sel.value ? "true" : "false");
        });
      }
      function open() {
        list.hidden = false;
        wrap.classList.add("is-open");
        btn.setAttribute("aria-expanded", "true");
        highlight(Math.max(0, sel.selectedIndex));
      }
      function close() {
        list.hidden = true;
        wrap.classList.remove("is-open");
        btn.setAttribute("aria-expanded", "false");
        btn.removeAttribute("aria-activedescendant");
        // Clear the highlight too, or reopening leaves ghosts behind.
        if (active > -1 && opts[active]) opts[active].classList.remove("is-active");
        active = -1;
      }
      function highlight(i) {
        if (active > -1 && opts[active]) opts[active].classList.remove("is-active");
        active = (i + opts.length) % opts.length;
        opts[active].classList.add("is-active");
        btn.setAttribute("aria-activedescendant", opts[active].id);
        opts[active].scrollIntoView({ block: "nearest" });
      }
      function choose(i) {
        sel.value = opts[i].dataset.value;
        sel.dispatchEvent(new Event("change", { bubbles: true }));
        paint();
        close();
        btn.focus();
      }

      btn.addEventListener("click", function () { list.hidden ? open() : close(); });
      btn.addEventListener("keydown", function (e) {
        if (e.key === "ArrowDown" || e.key === "ArrowUp") {
          e.preventDefault();
          if (list.hidden) { open(); } else { highlight(active + (e.key === "ArrowDown" ? 1 : -1)); }
        } else if ((e.key === "Enter" || e.key === " ") && !list.hidden) {
          e.preventDefault();
          if (active > -1) choose(active);
        } else if (e.key === "Escape" && !list.hidden) {
          close();
        } else if (e.key === "Home" && !list.hidden) { e.preventDefault(); highlight(0); }
        else if (e.key === "End" && !list.hidden) { e.preventDefault(); highlight(opts.length - 1); }
      });
      list.addEventListener("mousedown", function (e) {
        var el = e.target.closest(".nsel__opt");
        if (el) { e.preventDefault(); choose(opts.indexOf(el)); }
      });
      document.addEventListener("click", function (e) {
        if (!list.hidden && !wrap.contains(e.target)) close();
      });

      paint();
    });
  }

  /* --- Site search ----------------------------------------------------- */
  /* One search box, everything behind it. ORIA_DATA is already on the page
     for the map and the directory, so suggestions are built locally and
     appear as fast as you can type — no request per keystroke.

     What a query is matched against, in the order results are grouped:
     specialties (the precise modality — "Cryotherapy"), practice
     categories, individual practices by name, and suburbs. Everyday
     wording that isn't in any of those names ("ice bath", "reformer")
     is mapped onto specialties by ORIA_SEARCH.synonyms. */
  function searchIndex() {
    // Directory-ish pages carry the full set; everywhere else gets the
    // slim index, which holds the same fields search actually reads.
    var D = window.ORIA_DATA || window.ORIA_SEARCH_DATA;
    if (!D) return null;
    var counts = {};
    (D.listings || []).forEach(function (l) {
      (l.spec || []).forEach(function (s) { counts[s] = (counts[s] || 0) + 1; });
      counts["cat:" + l.cat] = (counts["cat:" + l.cat] || 0) + 1;
      counts["sub:" + (l.suburb || "").toLowerCase()] =
        (counts["sub:" + (l.suburb || "").toLowerCase()] || 0) + 1;
    });
    return { D: D, counts: counts };
  }

  /* Specialty slugs an everyday phrase should also look for. */
  function synonymSlugs(q) {
    var map = (window.ORIA_SEARCH || {}).synonyms || {};
    var hits = [];
    Object.keys(map).forEach(function (alias) {
      // Very short aliases ("pt", "aa") only on an exact query, or they
      // would fire inside unrelated words.
      var match = alias.length <= 3 ? q === alias : q.indexOf(alias) > -1 || alias.indexOf(q) === 0;
      if (match) hits = hits.concat(map[alias]);
    });
    return hits;
  }

  /* PHP encodes an array as a JSON object the moment its keys stop being
     sequential — one term filtered out server-side and "specialties"
     arrives as {"0":…,"2":…} instead of a list, and every .forEach on it
     throws, taking the whole init chain (filters included) down with it.
     This happened on production. Accept both shapes, always. */
  function asList(v) {
    if (Array.isArray(v)) return v;
    if (v && typeof v === "object") return Object.keys(v).map(function (k) { return v[k]; });
    return [];
  }

  function searchSuggest(raw) {
    var idx = searchIndex();
    var q = raw.trim().toLowerCase();
    if (!idx || q.length < 2) return [];
    var D = idx.D, counts = idx.counts, syn = synonymSlugs(q), out = [];

    asList(D.specialties).forEach(function (s) {
      var hit = s.name.toLowerCase().indexOf(q) > -1 || syn.indexOf(s.id) > -1;
      if (hit && counts[s.id]) {
        out.push({ kind: "Specialty", label: s.name, sub: counts[s.id] + " places", url: s.url,
                   rank: s.name.toLowerCase().indexOf(q) === 0 ? 0 : 1 });
      }
    });
    (D.categories || []).forEach(function (c) {
      if (c.name.toLowerCase().indexOf(q) > -1) {
        out.push({ kind: "Category", label: c.name, sub: (counts["cat:" + c.id] || 0) + " places",
                   url: c.url, rank: 2 });
      }
    });
    (D.listings || []).forEach(function (l) {
      if (l.name.toLowerCase().indexOf(q) > -1) {
        out.push({ kind: "Practice", label: l.name, sub: l.suburb, url: l.url, rank: 3 });
      }
    });
    (D.regions || []).forEach(function (r) {
      (r.suburbs || []).forEach(function (name) {
        if (name.toLowerCase().indexOf(q) === 0) {
          out.push({ kind: "Suburb", label: name,
                     sub: (counts["sub:" + name.toLowerCase()] || 0) + " places",
                     url: (window.ORIA_SEARCH || {}).directory + "?q=" + encodeURIComponent(name),
                     rank: 4 });
        }
      });
    });

    out.sort(function (a, b) { return a.rank - b.rank || a.label.localeCompare(b.label); });
    return out.slice(0, 8);
  }

  /* Tell the site owner what someone looked for and didn't find. */
  function reportMiss(q) {
    var cfg = window.ORIA_SEARCH;
    if (!cfg || !cfg.miss || q.length < 2) return;
    var headers = { "Content-Type": "application/json" };
    if (cfg.nonce) headers["X-WP-Nonce"] = cfg.nonce;
    fetch(cfg.miss, {
      method: "POST", headers: headers, credentials: "same-origin",
      body: JSON.stringify({ q: q })
    }).catch(function () { /* never let analytics break a search */ });
  }

  function initSiteSearch() {
    $$("[data-oria-search]").forEach(function (input) {
      var panel = input.parentNode.querySelector("[data-oria-search-panel]");
      if (!panel) return;
      var items = [], active = -1;

      function close() {
        panel.hidden = true;
        panel.innerHTML = "";
        items = [];
        if (active > -1) active = -1;
        input.setAttribute("aria-expanded", "false");
      }

      function go(i) {
        if (items[i] && items[i].url) window.location.href = items[i].url;
      }

      function paint() {
        items = searchSuggest(input.value);
        if (!items.length) { close(); return; }
        panel.innerHTML = items.map(function (r, i) {
          return '<span class="osearch__opt" role="option" id="' + input.id + '-o' + i +
            '" data-i="' + i + '" aria-selected="false">' +
            '<b>' + esc(r.label) + "</b>" +
            '<em>' + esc(r.kind) + (r.sub ? " · " + esc(r.sub) : "") + "</em></span>";
        }).join("");
        panel.hidden = false;
        panel.setAttribute("role", "listbox");
        input.setAttribute("aria-expanded", "true");
        active = -1;
      }

      function highlight(next) {
        var opts = panel.querySelectorAll(".osearch__opt");
        if (!opts.length) return;
        if (active > -1 && opts[active]) {
          opts[active].classList.remove("is-active");
          opts[active].setAttribute("aria-selected", "false");
        }
        active = (next + opts.length) % opts.length;
        opts[active].classList.add("is-active");
        opts[active].setAttribute("aria-selected", "true");
        input.setAttribute("aria-activedescendant", opts[active].id);
        opts[active].scrollIntoView({ block: "nearest" });
      }

      input.addEventListener("input", paint);
      input.addEventListener("focus", function () { if (input.value.trim().length > 1) paint(); });
      input.addEventListener("keydown", function (e) {
        if (e.key === "ArrowDown" && panel.hidden) { paint(); return; }
        if (panel.hidden) return;
        if (e.key === "ArrowDown") { e.preventDefault(); highlight(active + 1); }
        else if (e.key === "ArrowUp") { e.preventDefault(); highlight(active - 1); }
        else if (e.key === "Enter" && active > -1) { e.preventDefault(); go(active); }
        else if (e.key === "Escape") { close(); }
      });
      panel.addEventListener("mousedown", function (e) {
        var el = e.target.closest(".osearch__opt");
        if (!el) return;
        e.preventDefault();
        go(Number(el.dataset.i));
      });
      document.addEventListener("click", function (e) {
        if (!panel.hidden && !input.parentNode.contains(e.target)) close();
      });
    });
  }

  /* --- Home search --------------------------------------------------- */
  function initHomeSearch() {
    var form = $("#heroSearch");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var term = $("#heroCat").value.trim();
      var where = $("#heroWhere").value.trim();

      // A typed term that exactly matches a suggestion goes straight to
      // that page; anything else becomes a directory text search.
      var hits = searchSuggest(term);
      if (term && hits.length && hits[0].label.toLowerCase() === term.toLowerCase() && !where) {
        window.location.href = hits[0].url;
        return;
      }
      if (term && !hits.length) reportMiss(term.toLowerCase());

      // Both boxes fold into one query; the directory matches every word
      // separately, so "cryotherapy" + "Cottesloe" narrows rather than
      // looking for that phrase verbatim.
      var q = [term, where].filter(Boolean).join(" ");
      var base = (window.ORIA_SEARCH || {}).directory || "/directory/";
      window.location.href = base + (q ? "?q=" + encodeURIComponent(q) : "");
    });
  }

  /* --- Directory ------------------------------------------------------ */
  /* A filtered view reached by URL (?spec=, ?suburb=, ?region=, ?svc=,
     ?aud=, ?price=, ?format=, ?q=) is about the listings, not the
     introduction above them: on the redesigned pages (the ones with a
     #browse floor) start the visitor at the listings. A hash in the URL
     wins — the person asked for a specific section. */
  /* A "Done" button inside a filter sheet (shown on small screens) closes
     it — the same as tapping outside, for people who never would. */
  /* On small screens a long category grid shows its first eight; the
     button beneath reveals the rest. Desktop shows everything (CSS). */
  function initIntentGridMore() {
    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-intentgrid-more]");
      if (!btn) return;
      var grid = btn.previousElementSibling;
      if (!grid || !grid.classList.contains("intentgrid")) return;
      grid.classList.add("is-expanded");
      btn.setAttribute("aria-expanded", "true");
      btn.hidden = true;
    });
  }

  /* The facts strip counts up from zero on load — listings, suburbs,
     claimed, the typical price — the numeric part only, so "from $28" and
     "1,234" keep their dressing. Eases out over ~1.2s, starting once the
     spine has dropped in. Off under reduced motion, and never on a
     non-numeric value. */
  function initCountUp() {
    var els = $$(".facts dd");
    if (!els.length) return;
    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    els.forEach(function (el, i) {
      var text = el.textContent;
      var m = text.match(/(\d[\d,]*)/);
      if (!m) return;
      var target = parseInt(m[1].replace(/,/g, ""), 10);
      if (!isFinite(target) || target <= 0 || reduce) return;
      var grouped = m[1].indexOf(",") !== -1;
      var before = text.slice(0, m.index), after = text.slice(m.index + m[1].length);
      var fmt = function (n) { return grouped ? n.toLocaleString("en-AU") : String(n); };
      el.setAttribute("aria-label", text.trim());
      el.textContent = before + fmt(0) + after;
      var dur = 1200, start = null, done = false;
      var finish = function () { if (done) return; done = true; el.textContent = text; };
      var step = function (ts) {
        if (done) return;
        if (start === null) start = ts;
        var t = Math.min(1, (ts - start) / dur);
        var eased = 1 - Math.pow(1 - t, 3);
        el.textContent = before + fmt(Math.round(target * eased)) + after;
        if (t < 1) requestAnimationFrame(step); else finish();
      };
      window.setTimeout(function () {
        // A background tab gets no animation frames: show the number and
        // move on rather than leaving a zero on screen.
        if (document.hidden) { finish(); return; }
        requestAnimationFrame(step);
        window.setTimeout(finish, dur + 400); // safety net if frames stall
      }, 450 + i * 120);
    });
  }

  /* The star rating input.
     The <select> in the markup is the real control and works on its own.
     This draws a star widget beside it and keeps the two in step: two
     identical rows of five stars, grey under gold, with the gold row
     clipped to a percentage width. A rating of 3.5 is a clip at 70%, so
     halves need no special case and nothing has to line up by hand.
     Pointer position decides the value, which is what makes dragging
     across the stars feel right. */
  function initStarRate() {
    var STAR = '<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>';

    $$("[data-starrate]").forEach(function (host) {
      var select = host.querySelector("select");
      if (!select || host.classList.contains("starrate--live")) return;

      // Every value the select offers, lowest first, so a pointer position
      // can be turned into one of them.
      var steps = Array.prototype.slice.call(select.options)
        .map(function (o) { return parseFloat(o.value); })
        .filter(function (v) { return !isNaN(v); })
        .sort(function (a, b) { return a - b; });
      if (!steps.length) return;

      var max = steps[steps.length - 1];
      var row = '<span class="starrate__row">' + new Array(Math.round(max) + 1).join(STAR) + "</span>";

      var widget = document.createElement("span");
      widget.className = "starrate__widget";
      widget.setAttribute("aria-hidden", "true");
      widget.innerHTML = '<span class="starrate__base">' + row + "</span>" +
                         '<span class="starrate__fill"><span class="starrate__row">' +
                         new Array(Math.round(max) + 1).join(STAR) + "</span></span>";

      var readout = document.createElement("span");
      readout.className = "starrate__value";
      readout.setAttribute("aria-hidden", "true");

      select.insertAdjacentElement("afterend", widget);
      widget.insertAdjacentElement("afterend", readout);
      host.classList.add("starrate--live");

      var fill = widget.querySelector(".starrate__fill");

      var paint = function (value) {
        fill.style.width = value > 0 ? ( value / max ) * 100 + "%" : "0";
        readout.textContent = value > 0 ? value.toFixed(1) : "";
      };

      // Where the pointer is, snapped to the nearest offered value.
      var valueAt = function (clientX) {
        var box = widget.getBoundingClientRect();
        if (!box.width) return steps[0];
        var ratio = (clientX - box.left) / box.width;
        var raw = ratio * max;
        for (var i = 0; i < steps.length; i++) {
          if (raw <= steps[i] + 0.0001) return steps[i];
        }
        return max;
      };

      var chosen = function () { return parseFloat(select.value) || 0; };

      widget.addEventListener("mousemove", function (e) { paint(valueAt(e.clientX)); });
      widget.addEventListener("mouseleave", function () { paint(chosen()); });

      widget.addEventListener("click", function (e) {
        select.value = String(valueAt(e.clientX));
        select.dispatchEvent(new Event("change", { bubbles: true }));
        paint(chosen());
      });

      // Touch: no hover, so follow the finger and commit on release.
      widget.addEventListener("touchmove", function (e) {
        if (e.touches[0]) paint(valueAt(e.touches[0].clientX));
      }, { passive: true });

      // The select remains the source of truth: changing it by keyboard,
      // or the browser restoring a value, repaints the stars.
      select.addEventListener("change", function () { paint(chosen()); });
      select.addEventListener("focus", function () { widget.classList.add("is-focused"); });
      select.addEventListener("blur", function () { widget.classList.remove("is-focused"); });

      paint(chosen());
    });
  }

  function initPopoverDone() {
    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-popover-close]");
      if (!btn) return;
      var d = ownerOf(btn);
      if (d) { d.open = false; d.querySelector("summary") && d.querySelector("summary").focus(); }
    });
  }

  /* Phones: a facet popover is a bottom sheet, and a sheet needs three
     things a dropdown does not — something dimmed behind it, a page that
     stays still underneath, and an escape that is not a small Done button.
     Without them the sheet covers the toolbar it came from (it is 78vh tall
     and the toolbar sits a screen and a half down the page), which reads as
     the filters having vanished.

     Choosing an option then closes the sheet and drops the visitor at the
     results, because seeing what the filter did is the reason they opened
     it. That costs ticking two boxes in one visit; the active-filter chips
     above the count make the second tap obvious, which on a phone is the
     better trade. Desktop keeps the popover open — there it sits beside the
     results rather than on top of them. */
  function initFilterSheet() {
    var sheets = $$("[data-popover]");
    if (!sheets.length) return;

    var phone = window.matchMedia("(max-width: 50rem)");
    var veil = null;

    /* Each details keeps a handle on its own panel, because once the panel
       has been moved to the body it can no longer be found by looking
       inside the details. */
    sheets.forEach(function (d) {
      var panel = d.querySelector(".popover__panel");
      if (!panel) return;
      d.oriaPanel = panel;
      panel.oriaOwner = d;
    });

    function anyOpen() {
      return sheets.some(function (d) { return d.open; });
    }

    function closeAll() {
      sheets.forEach(function (d) { d.open = false; });
    }

    /* WebKit will not reliably paint a position:fixed element that sits
       inside a scroll container, and .toolbar__filters is one (overflow-x
       for the scrolling pill row). On iOS the sheet was being clipped away
       to nothing while Chrome showed it fine.

       Rather than hunt each offending ancestor property in turn — the
       toolbar's stacking context already cost one round of this — the panel
       is lifted out to the body for as long as it is open. Nothing above it
       can then clip it, contain it, or out-stack it. It goes back into the
       details on close, so the closed state stays exactly what the markup
       says it is. */
    function portal(d, out) {
      var panel = d.oriaPanel;
      if (!panel) return;
      if (out) {
        if (panel.parentNode !== document.body) {
          panel.classList.add("is-sheet");
          document.body.appendChild(panel);
        }
      } else if (panel.parentNode === document.body) {
        panel.classList.remove("is-sheet");
        d.appendChild(panel);
      }
    }

    function sync() {
      var on = phone.matches && anyOpen();

      /* dress() as well as portal(): crossing the breakpoint with a filter
         already open — rotating a phone to landscape does it — has to
         produce a complete sheet, and dress() otherwise only runs on the
         toggle that is now in the past. */
      sheets.forEach(function (d) {
        if (phone.matches && d.open) dress(d);
        portal(d, phone.matches && d.open);
      });

      if (on) {
        if (!veil) {
          veil = document.createElement("div");
          veil.className = "sheetveil";
          veil.addEventListener("click", closeAll);
        }
        if (!veil.parentNode) document.body.appendChild(veil);
        document.documentElement.classList.add("has-sheet");
      } else {
        document.documentElement.classList.remove("has-sheet");
        if (veil && veil.parentNode) veil.parentNode.removeChild(veil);
      }
    }

    /* One sheet closing as another opens fires two toggles in a row, so the
       veil is decided once both have landed rather than per event. */
    var queued = false;
    function later() {
      if (queued) return;
      queued = true;
      window.setTimeout(function () { queued = false; sync(); }, 0);
    }

    /* A sheet opens at the bottom of the screen, a long way from the pill
       that was tapped — on the directory the toolbar sits some 1500px down
       the page, so the two are never on screen together. Without a title it
       is not obvious what has appeared, or that anything has. Built here
       rather than in the markup so the desktop dropdown, which is anchored
       under its own labelled button, keeps the shape it already had. */
    function dress(d) {
      var panel = d.oriaPanel;
      if (!panel || panel.querySelector(".sheet__head")) return;
      var summary = d.querySelector("summary");
      var name = "";
      if (summary) {
        name = Array.prototype.filter
          .call(summary.childNodes, function (n) { return 3 === n.nodeType; })
          .map(function (n) { return n.textContent; })
          .join(" ")
          .trim();
      }
      var head = document.createElement("div");
      head.className = "sheet__head";
      var title = document.createElement("span");
      title.className = "sheet__title";
      title.textContent = name;
      var shut = document.createElement("button");
      shut.type = "button";
      shut.className = "sheet__x";
      shut.setAttribute("aria-label", "Close");
      shut.innerHTML = "&times;";
      shut.addEventListener("click", closeAll);
      head.appendChild(title);
      head.appendChild(shut);
      panel.insertBefore(head, panel.firstChild);
    }

    sheets.forEach(function (d) {
      d.addEventListener("toggle", function () {
        if (d.open && phone.matches) { dress(d); portal(d, true); }
        later();
      });
    });
    if (phone.addEventListener) phone.addEventListener("change", sync);
    else if (phone.addListener) phone.addListener(sync);

    document.addEventListener("keydown", function (e) {
      if ("Escape" === e.key && anyOpen()) closeAll();
    });

    /* Delegated, so it runs after the per-input handler that re-renders the
       listings on the same change event. */
    document.addEventListener("change", function (e) {
      if (!phone.matches || !e.target.closest) return;
      var input = e.target.closest("[data-filter]");
      if (!input) return;
      var d = ownerOf(input);
      if (!d || !d.open) return;
      d.open = false;
      goToResults();
    });
  }

  /* Which details a node belongs to, whether it is still inside that details
     or has been lifted out to the body as an open sheet. */
  function ownerOf(node) {
    if (!node || !node.closest) return null;
    var d = node.closest("[data-popover]");
    if (d) return d;
    var panel = node.closest(".popover__panel");
    return panel && panel.oriaOwner ? panel.oriaOwner : null;
  }

  /* Put the count and the first listings on screen, clear of the sticky
     spine. Used after a filter sheet closes and on arrival with a filtered
     URL. */
  /* How much sticky chrome covers the top of the viewport. On inner pages
     the solid site header sticks at z-60 and is TALLER than the spine, which
     sticks at top:0 underneath it — so the header's height is the number
     that matters, and measuring the spine put the pinned toolbar (and any
     scroll target) partly behind the nav. */
  function chromeTop() {
    var head = $(".site-head--solid");
    if (head && "sticky" === getComputedStyle(head).position) {
      return Math.round(head.getBoundingClientRect().height);
    }
    var spine = $(".spine");
    return spine ? Math.round(spine.getBoundingClientRect().height) : 0;
  }

  function goToResults(instant) {
    var target = $(".dir__count") || $("#dirResults") || $("#browse");
    if (!target) return;
    var offset = chromeTop() + 12;
    /* On phones the toolbar pins below the spine, so it will be sitting
       over the count by the time this scroll lands there. Already stuck:
       its height is the collapsed one, use it as is. Not stuck yet: it
       will be by then, so use what it collapses to — the filter row plus
       the bar's padding. */
    var bar = $(".toolbar");
    if (bar && window.matchMedia("(max-width: 50rem)").matches) {
      if (bar.classList.contains("is-stuck")) {
        offset += bar.offsetHeight;
      } else {
        var row = bar.querySelector(".toolbar__filters");
        offset += ( row ? row.offsetHeight : 0 ) + 26;
      }
    }
    var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({ top: Math.max(0, top), behavior: instant ? "auto" : "smooth" });
  }

  /* On phones the toolbar follows the reader down the results: once its
     natural position scrolls past the spine it pins beneath it (the CSS
     does the pinning; this adds .is-stuck), dropped to just the filter
     row. The margin swap keeps the page the same length as the bar
     collapses, so the cards do not jump 116px mid-scroll. */
  function initStickyToolbar() {
    var bar = $(".toolbar");
    if (!bar) return;
    var phone = window.matchMedia("(max-width: 50rem)");

    function stickTop() {
      return chromeTop() + 8; // a breath of space under the nav
    }
    function setTop() {
      document.documentElement.style.setProperty("--oria-stick-top", stickTop() + "px");
    }
    setTop();
    window.addEventListener("resize", setTop);

    /* Marks where the toolbar's top edge naturally sits. Its position does
       not move when the bar collapses — which is what stops the collapse
       unsticking the bar it was triggered by, growing it, and flapping. */
    var mark = document.createElement("div");
    mark.setAttribute("aria-hidden", "true");
    mark.style.cssText = "height:1px;margin-bottom:-1px;";
    bar.parentNode.insertBefore(mark, bar);

    var stuck = false;
    function tick() {
      var want = phone.matches && mark.getBoundingClientRect().top < stickTop();
      if (want === stuck) return;
      stuck = want;
      if (want) {
        var full = bar.offsetHeight;
        bar.classList.add("is-stuck");
        bar.style.marginBottom = Math.max(0, full - bar.offsetHeight) + "px";
      } else {
        bar.classList.remove("is-stuck");
        bar.style.marginBottom = "";
      }
    }
    window.addEventListener("scroll", tick, { passive: true });
    if (phone.addEventListener) phone.addEventListener("change", tick);
    else if (phone.addListener) phone.addListener(tick);
    tick();
  }

  function scrollToFilteredResults() {
    var browse = $("#browse");
    if (!browse || !$("#dirResults") || window.location.hash) return;
    var params = new URLSearchParams(window.location.search);
    var keys = ["spec", "suburb", "region", "svc", "aud", "price", "format", "q", "cat"];
    var filtered = keys.some(function (k) { return !!params.get(k); });
    if (!filtered) return;
    if ("scrollRestoration" in history) history.scrollRestoration = "manual";
    window.setTimeout(function () {
      var spine = $(".spine");
      var offset = (spine ? spine.getBoundingClientRect().height : 0) + 12;
      var top = browse.getBoundingClientRect().top + window.pageYOffset - offset;
      window.scrollTo({ top: Math.max(0, top), behavior: "auto" });
    }, 60);
  }

  /* "What are you after?" chips — presets over the specialty filters.
     A chip checks its mapped [data-filter="spec"] boxes and lets the
     existing engine run; it claims nothing of its own. State is derived,
     not stored: after ANY filter change a chip is lit only while every
     one of its (present) specialties is still ticked, so hand-editing
     the filters can never leave a chip lying. */
  /* Wants the visitor has explicitly chosen (chip row or popover), keyed
     by slug. Kept separate from the derived all-boxes-ticked state so a
     visitor can prune specialties inside a want without the want itself
     vanishing — it lets go only when its last specialty does, or when
     they dismiss it themselves. */
  var GFSel = {};

  /* Bridge between the map and the results list: the popup's link asks the
     directory engine to page a listing's card into view and spotlight it. */
  var DirAPI = {};

  /* --- Category map ---------------------------------------------------- */
  /* A real, interactive map of every listing on a category page — Leaflet
     over CARTO's light basemap, both self-hosted/keyless. Hover names the
     place; click opens a card with the link. Leaflet is only enqueued on
     the pages that render a map, so window.L is the feature test. */
  function initCatMap() {
    var host = $("[data-catmap]");
    var dataEl = $("[data-catmap-data]");
    if (!host || !dataEl || !window.L) return;
    var places;
    try { places = JSON.parse(dataEl.textContent || "[]"); } catch (e) { places = []; }
    places = places.filter(function (p) { return p.la && p.lo; });
    if (!places.length) { host.hidden = true; return; }

    var map = L.map(host, {
      scrollWheelZoom: false,   // the page scroll must survive passing over the map
      attributionControl: true,
      zoomControl: true
    });
    /* OSM standard tiles: keyless, attribution required. CARTO's light
       basemap looked better but now watermarks without an API key. */
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var group = [];
    places.forEach(function (p) {
      var mk = L.circleMarker([p.la, p.lo], {
        radius: 8,
        color: "#fff",
        weight: 2,
        fillColor: "#0E3B38",
        fillOpacity: 0.95
      }).addTo(map);
      mk.bindTooltip(p.n + (p.s ? " · " + p.s : ""), { direction: "top", offset: [0, -8] });
      mk.bindPopup(
        '<div class="catmap__pop">' +
        (p.i ? '<img class="catmap__pop-img" src="' + esc(p.i) + '" alt="" loading="lazy">' : "") +
        "<b>" + esc(p.n) + "</b>" +
        (p.s ? "<span>" + esc(p.s) + "</span>" : "") +
        '<a href="' + esc(p.u) + '" data-catmap-view>View profile &rarr;</a></div>',
        { minWidth: 200 }
      );
      mk._oriaSub = (p.s || "").toLowerCase();
      mk.on("mouseover", function () { mk.setStyle({ fillColor: "#C9A24B" }); });
      mk.on("mouseout", function () { mk.setStyle({ fillColor: mk._oriaHeld ? "#C9A24B" : "#0E3B38" }); });
      group.push(mk);
    });

    var bounds = L.featureGroup(group).getBounds();
    map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });

    /* Cards' pin buttons jump here: zoom to the marker, open its card,
       and bring the map into view. Hidden by CSS unless this ran. */
    var byUrl = {};
    group.forEach(function (mk, i) { byUrl[places[i].u] = mk; });
    document.body.classList.add("has-catmap");
    DirAPI.focusOnMap = function (url) {
      var mk = byUrl[url];
      if (!mk) return false;
      var top = host.getBoundingClientRect().top + window.pageYOffset - chromeTop() - 16;
      window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
      map.setView(mk.getLatLng(), Math.max(map.getZoom(), 14));
      mk.openPopup();
      return true;
    };

    /* "View profile" in a popup goes to the card in the list below —
       scrolled to and spotlit gold — rather than straight off the page:
       the card holds the rating, price and blurb the decision needs. The
       href stays the real profile URL, so new-tab and no-JS still land
       there, and so does anyone the filters have hidden the card from. */
    host.addEventListener("click", function (e) {
      var link = e.target.closest && e.target.closest("[data-catmap-view]");
      if (!link || !DirAPI.revealCard) return;
      var card = DirAPI.revealCard(link.getAttribute("href"));
      if (!card) return; // filtered out of the list: follow the link
      e.preventDefault();
      map.closePopup();
      $$(".listing.is-spotlit, article.is-spotlit").forEach(function (el) { el.classList.remove("is-spotlit"); });
      card.classList.add("is-spotlit");
      var top = card.getBoundingClientRect().top + window.pageYOffset - chromeTop() - 24;
      window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
      setTimeout(function () { card.classList.remove("is-spotlit"); }, 6000);
    });

    /* The suburb pills under the map drive it: first click zooms to that
       suburb's pins and lights them gold; a second click on the now-active
       pill follows its link to the area page. The href stays real the whole
       time, so crawlers, middle-clicks and no-JS visitors lose nothing. */
    var pills = $$(".nearyou--map .pill");
    /* While a suburb is active, its drill-down page gets an explicit door:
       a link that appears under the pills, so the pill itself can stay a
       pure zoom toggle. */
    var openLink = document.createElement("a");
    openLink.className = "nearyou__open";
    openLink.hidden = true;
    var pillWrap = pills.length ? pills[0].closest(".nearyou--map") : null;
    if (pillWrap) pillWrap.appendChild(openLink);

    function releasePins() {
      group.forEach(function (mk) { mk._oriaHeld = false; mk.setStyle({ fillColor: "#0E3B38" }); });
    }
    function resetMap() {
      pills.forEach(function (o) { o.classList.remove("is-here"); });
      releasePins();
      openLink.hidden = true;
      map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });
    }
    pills.forEach(function (pill) {
      var name = (pill.getAttribute("data-suburb") || "").toLowerCase();
      pill.addEventListener("click", function (e) {
        var mks = group.filter(function (mk) { return mk._oriaSub === name; });
        if (!mks.length) return; // nothing to zoom to: behave as a plain link
        e.preventDefault();
        if (pill.classList.contains("is-here")) { resetMap(); return; } // declick: back to all of Perth
        pills.forEach(function (o) { o.classList.remove("is-here"); });
        pill.classList.add("is-here");
        releasePins();
        mks.forEach(function (mk) { mk._oriaHeld = true; mk.setStyle({ fillColor: "#C9A24B" }); });
        map.fitBounds(L.featureGroup(mks).getBounds(), { padding: [46, 46], maxZoom: 15 });
        openLink.href = pill.getAttribute("href");
        openLink.textContent = "Open the " + (pill.getAttribute("data-suburb") || "area") + " page →";
        openLink.hidden = false;
      });
    });
    /* Zooming back out by hand reads as "never mind" — same as a declick. */
    map.on("zoomend", function () {
      if (map.getZoom() <= map.getBoundsZoom(bounds, false)) {
        pills.forEach(function (o) { o.classList.remove("is-here"); });
        releasePins();
        openLink.hidden = true;
      }
    });
  }

  /* Card corner actions — delegated, because the engine redraws cards on
     every filter change and per-card listeners would be lost each time. */
  function initCardQuickActions() {
    document.addEventListener("click", function (e) {
      var save = e.target.closest && e.target.closest("[data-card-save]");
      if (save) {
        var id = String(save.dataset.cardSave);
        var ids = savedIds();
        var at = ids.indexOf(id);
        if (at > -1) { ids.splice(at, 1); } else { ids.push(id); }
        if (!writeSaved(ids)) {
          save.setAttribute("title", "Saving needs site data enabled in your browser.");
          return;
        }
        $$('[data-card-save="' + id + '"]').forEach(function (b) {
          b.setAttribute("aria-pressed", at > -1 ? "false" : "true");
        });
        pushEvent(at > -1 ? "listing_unsave" : "listing_save", { listing_id: id });
        return;
      }
      var pin = e.target.closest && e.target.closest("[data-card-pin]");
      if (pin && DirAPI.focusOnMap) DirAPI.focusOnMap(pin.dataset.cardPin);
    });
  }

  function initGoodFor() {

    if (!$("#dirResults")) return;
    var chips = $$("[data-goodfor-chip]");
    var opts = $$("[data-goodfor-opt]");
    if (!chips.length && !opts.length) return;

    function boxFor(slug) {
      return document.querySelector('[data-filter="spec"][value="' + slug + '"]');
    }
    function specsOf(el) {
      var list;
      try { list = JSON.parse(el.getAttribute("data-specs") || "[]"); } catch (e) { list = []; }
      return list.map(boxFor).filter(Boolean);
    }
    function isOn(el) {
      var boxes = specsOf(el);
      return boxes.length > 0 && boxes.every(function (b) { return b.checked; });
    }
    function anyOn(el) {
      return specsOf(el).some(function (b) { return b.checked; });
    }
    function slugOf(el) { return el.getAttribute("data-slug") || ""; }
    function lit(el) {
      // Chosen: stays while any of its specialties survives. Not chosen:
      // lights only when the visitor has ticked the complete set by hand.
      return GFSel[slugOf(el)] ? anyOn(el) : isOn(el);
    }
    function sync() {
      // A chosen want with none of its specialties left has been fully
      // pruned — let it go rather than filter by nothing.
      chips.concat(opts).forEach(function (el) {
        var k = slugOf(el);
        if (GFSel[k] && !anyOn(el)) delete GFSel[k];
      });
      chips.forEach(function (chip) {
        var on = lit(chip);
        chip.classList.toggle("is-on", on);
        chip.setAttribute("aria-pressed", on ? "true" : "false");
      });
      opts.forEach(function (opt) { opt.checked = lit(opt); });
    }
    function setBoxes(boxes, checked) {
      boxes.forEach(function (b) {
        if (b.checked !== checked) {
          b.checked = checked;
          b.dispatchEvent(new Event("change", { bubbles: true }));
        }
      });
    }

    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        var mine = specsOf(chip);
        var wasOn = chip.classList.contains("is-on");
        // One want at a time in the row: clear every chip-managed spec,
        // then apply this chip's set (or nothing, if it was the lit one).
        GFSel = {};
        chips.forEach(function (other) { setBoxes(specsOf(other), false); });
        if (!wasOn) {
          GFSel[slugOf(chip)] = 1;
          setBoxes(mine, true);
        }
        sync();
        // Choosing a want answers "show me" — take the visitor to the
        // answer rather than leaving them looking at the chip row.
        if (!wasOn && typeof goToResults === "function") goToResults(false);
      });
    });

    /* The popover options multi-select: ticking a want checks its
       specialties; un-ticking releases only the specialties no other
       ticked want still needs, so overlapping wants (Relax and Indulge
       both carry massage) never fight each other. */
    opts.forEach(function (opt) {
      opt.addEventListener("change", function () {
        var mine = specsOf(opt);
        if (opt.checked) {
          GFSel[slugOf(opt)] = 1;
          setBoxes(mine, true);
        } else {
          delete GFSel[slugOf(opt)];
          var keep = {};
          opts.forEach(function (other) {
            if (other !== opt && isOn(other)) {
              specsOf(other).forEach(function (b) { keep[b.value] = 1; });
            }
          });
          setBoxes(mine.filter(function (b) { return !keep[b.value]; }), false);
        }
        sync();
      });
    });

    // Keep every derived state honest when filters change by any route.
    document.addEventListener("change", function (e) {
      if (e.target && e.target.closest && e.target.closest('[data-filter="spec"]')) sync();
    });
    sync();
  }

  function initDirectory() {
    var root = $("#dirResults");
    if (!root) return;

    var catNames = {}, regionNames = {}, suburbRegion = {}, specNames = {},
        svcNames = {}, audNames = {};
    DATA.categories.forEach(function (c) { catNames[c.id] = c.name; });
    asList(DATA.specialties).forEach(function (s) { specNames[s.id] = s.name; });
    asList(DATA.services).forEach(function (s) { svcNames[s.id] = s.name; });
    asList(DATA.audiences).forEach(function (a) { audNames[a.id] = a.name; });
    DATA.regions.forEach(function (r) {
      regionNames[r.id] = r.name;
      r.suburbs.forEach(function (s) { suburbRegion[s.toLowerCase()] = r.id; });
    });

    var PER_PAGE = 10;
    var state = { cats: [], regions: [], suburbs: [], spec: [], svc: [], aud: [], price: [], format: [], rating: 0, q: "", sort: "relevance", page: 1 };

    /* The want-tags a card leads with, derived from the listing's own
       specialties against DATA.goodfor — the most-overlapping wants win.
       Nothing is stored per listing, so retuning goodfor.json retunes
       every card at once. */
    var GF = DATA.goodfor || [];
    function gfTags(l) {
      /* Specialties AND services: allied professions (podiatry, orthotics)
         live in the service vocabulary, not the specialty one, and a want
         set may name either kind of slug. */
      var specs = (l.spec || []).concat(l.svc || []);
      if (!GF.length || !specs.length) return [];
      var have = {};
      specs.forEach(function (s) { have[s] = 1; });
      return GF
        .map(function (g, i) {
          var hits = (g.specs || []).filter(function (s) { return have[s]; }).length;
          return { g: g, hits: hits, i: i };
        })
        .filter(function (x) { return x.hits > 0; })
        .sort(function (a, b) { return b.hits - a.hits || a.i - b.i; })
        .slice(0, 3)
        .map(function (x) { return x.g; });
    }
    /* The wants currently in force: every one whose known specialties are
       all in the active spec filters. Powers the coloured chip in the
       filter row and the one-line description after the count. */
    function activeWants() {
      return GF.filter(function (g) {
        /* "Known" means it has a checkbox on THIS page — the same rule the
           chip row lights by, so the two can never disagree. */
        var known = (g.specs || []).filter(function (x) {
          return document.querySelector('[data-filter="spec"][value="' + x + '"]');
        });
        if (!known.length) return false;
        var on = known.filter(function (x) { return state.spec.indexOf(x) > -1; });
        // A chosen want survives pruning down to its last specialty; an
        // unchosen one appears only when the full set is ticked by hand.
        return GFSel[g.slug] ? on.length > 0 : on.length === known.length;
      });
    }

    // Read the URL so category tiles, map regions and footer links all land
    // on a pre-filtered view — the same URLs the WordPress build will use.
    var params = new URLSearchParams(window.location.search);
    if (params.get("cat")) state.cats = params.get("cat").split(",");
    if (params.get("region")) state.regions = params.get("region").split(",");
    if (params.get("suburb")) state.suburbs = params.get("suburb").split(",");
    if (params.get("q")) state.q = params.get("q");
    if (params.get("spec")) state.spec = params.get("spec").split(",");
    // Intent rows on a category page link here. svc and aud are canonical
    // taxonomy slugs, so the filtered view holds exactly the listings the row
    // counted server-side. A fuzzy q= search would show a different number
    // from the one printed beside the link, which is worse than no link.
    if (params.get("svc")) state.svc = params.get("svc").split(",");
    if (params.get("aud")) state.aud = params.get("aud").split(",");
    if (params.get("price")) state.price = params.get("price").split(",");
    if (params.get("format")) state.format = params.get("format").split(",");
    if (params.get("pg")) state.page = Math.max(1, parseInt(params.get("pg"), 10) || 1);

    // Category and suburb landing pages lock one facet: the page IS the
    // filter, so it never appears as a removable chip and never hits the URL.
    var locked = {
      cat: root.dataset.cat || "",
      region: root.dataset.region || "",
      spec: root.dataset.spec || "",
      suburb: root.dataset.suburb || "",
      // An intent page locks one more facet (svc / aud / spec / format /
      // price) the same way. Key and value come from the registry via the
      // template, so the server-rendered set and this view agree exactly.
      intentKey: root.dataset.intentKey || "",
      intentValue: root.dataset.intentValue || ""
    };
    if (locked.cat) state.cats = [locked.cat];
    if (locked.region) state.regions = [locked.region];
    if (locked.spec) state.spec = [locked.spec];
    function isLockedIntent(k, v) { return !!locked.intentKey && k === locked.intentKey && v === locked.intentValue; }
    if (locked.intentKey && state[locked.intentKey] !== undefined) {
      if (locked.intentKey === "spec") { locked.spec = locked.intentValue; }
      state[locked.intentKey] = [locked.intentValue];
    }

    function matches(l) {
      if (state.cats.length && state.cats.indexOf(l.cat) === -1 &&
          !(l.also || []).some(function (a) { return state.cats.indexOf(a) > -1; })) return false;
      if (state.regions.length && state.regions.indexOf(l.region) === -1) return false;
      if (locked.suburb && l.suburb !== locked.suburb) return false;
      if (state.suburbs.length && state.suburbs.indexOf(l.suburb) === -1) return false;
      if (state.spec.length && !(l.spec || []).some(function (s) { return state.spec.indexOf(s) > -1; })) return false;
      if (state.svc.length && !(l.svc || []).some(function (s) { return state.svc.indexOf(s) > -1; })) return false;
      if (state.aud.length && !(l.aud || []).some(function (a) { return state.aud.indexOf(a) > -1; })) return false;
      if (state.price.length && state.price.indexOf(l.priceBand) === -1) return false;
      if (state.format.length) {
        var ok = state.format.some(function (f) { return l.format === f || l.format === "both"; });
        if (!ok) return false;
      }
      if (state.rating && l.rating < state.rating) return false;
      if (state.q) {
        // Specialty names are in the haystack too, so "cryotherapy" finds
        // the places tagged with it rather than only those that happen to
        // say the word in their blurb. Everyday wording is expanded via
        // the synonym map ("ice bath" also looks for cold-plunge).
        var hay = [
          l.name, l.suburb, l.blurb, catNames[l.cat], regionNames[l.region],
          (l.services || []).join(" "),
          (l.spec || []).map(function (s) { return specNames[s] || s; }).join(" "),
          (l.spec || []).join(" ")
        ].join(" ").toLowerCase();

        // Every word must appear somewhere, so extra words narrow the list
        // instead of demanding one exact phrase.
        var words = state.q.toLowerCase().split(/\s+/).filter(Boolean);
        var ok = words.every(function (w) {
          if (hay.indexOf(w) > -1) return true;
          return synonymSlugs(w).some(function (slug) { return (l.spec || []).indexOf(slug) > -1; });
        });
        if (!ok) return false;
      }
      return true;
    }

    var rank = { featured: 0, claimed: 1, unclaimed: 2 };
    function sortFn(a, b) {
      switch (state.sort) {
        case "rating": return b.rating - a.rating || b.reviews - a.reviews;
        case "reviews": return b.reviews - a.reviews;
        case "price": return a.priceFrom - b.priceFrom;
        case "name": return a.name.localeCompare(b.name);
        default: return (rank[a.status] - rank[b.status]) || (b.rating - a.rating);
      }
    }

    function card(l) {
      /* Featured and Claimed only — the same rule as listing-card.php, and
         it has to be stated in both places because this function re-renders
         every card the server already drew. An "Unclaimed" badge sat on 307
         of 314 listings, which is a label that tells a reader nothing and
         reads as a mark against a practice that usually does not yet know
         the listing exists. The disclosure it stood for stays on the listing
         page, where somebody can act on it. */
      var statusBadge = l.status === "featured"
        ? '<span class="badge badge--featured"><span class="badge-dot"></span>Featured</span>'
        : l.status === "claimed"
          ? '<span class="badge badge--claimed"><span class="badge-dot"></span>Claimed</span>'
          : '';

      return '<article class="listing' + (l.status === "featured" ? " listing--featured" : "") + '">' +
        '<div class="listing__media">' +
          /* Same shape as listing_alt() in functions.php. The category is
             a slug here rather than a display name, so this says the
             practice and the suburb and leaves the category out. */
          (l.image ? '<img src="' + esc(l.image) + '" alt="' + esc(l.name + (l.suburb ? " in " + l.suburb : "")) + '" loading="lazy"' +
            (l.image_fb && l.image_fb !== l.image
              ? " onerror=\"this.onerror=null;this.src='" + esc(l.image_fb) + "'\""
              : "") + '>' : "") +
          (statusBadge ? '<div class="listing__flag">' + statusBadge + "</div>" : "") +
          /* Top-right of the image: save to the device shortlist, and — on
             pages that carry the map — jump the map to this practice. */
          '<div class="listing__quick">' +
            '<button class="qact" type="button" data-card-save="' + esc(String(l.id)) +
              '" aria-pressed="' + (savedIds().indexOf(String(l.id)) > -1 ? "true" : "false") +
              '" aria-label="Save ' + esc(l.name) + '" title="Save">' + ICON.heart + "</button>" +
            '<button class="qact qact--pin" type="button" data-card-pin="' + esc(l.url) +
              '" aria-label="Show ' + esc(l.name) + ' on the map" title="Show on map">' + ICON.pin + "</button>" +
          "</div>" +
        "</div>" +
        '<div class="listing__body">' +
          /* Want-tags lead the card (same derivation as the chip row);
             the top-level category pill is the fallback for listings whose
             specialties map to no want — see the matching block in
             template-parts/listing-card.php. */
          (function () {
            var tags = gfTags(l);
            if (tags.length) {
              return '<div class="listing__cats">' +
                tags.map(function (g) {
                  return '<span class="pill pill--gf" style="--gf:' + esc(g.color) + '">' + esc(g.label) + "</span>";
                }).join("") +
                "</div>";
            }
            return (l.catTop || []).length
              ? '<div class="listing__cats">' +
                (l.catTop || []).map(function (c) {
                  return '<span class="pill pill--cat pill--cat-' + esc(c) + '">' +
                    esc(catNames[c] || c) + "</span>";
                }).join("") +
                "</div>"
              : "";
          })() +
          '<div class="listing__head">' +
            "<div>" +
              '<h3 class="listing__name"><a href="' + esc(l.url || '#') + '">' + esc(l.name) + "</a></h3>" +
              '<p class="listing__where">' + ICON.pin + esc(l.suburb) + " · " + esc(regionNames[l.region] || "") + "</p>" +
            "</div>" +
            (l.rating > 0
              ? '<span class="rating">' + ICON.star + l.rating.toFixed(1) +
                (l.reviews > 0
                  ? '<span class="rating__count">(' + l.reviews +
                    (l.rating_src === "google" ? " · Google" : "") + ")</span>"
                  : "") + "</span>"
              : "") +
          "</div>" +
          '<p class="listing__desc">' + esc(l.blurb) + "</p>" +
          '<div class="listing__tags">' +
            (l.format !== "in-person" ? '<span class="pill">Online available</span>' : "") +
            (l.offer ? '<span class="pill" style="background:var(--gold-soft);border-color:transparent;color:#7A5A12;font-weight:700">Special offer</span>' : "") +
            (l.next ? '<span class="pill">Next: ' + esc(l.next) + "</span>" : "") +
          "</div>" +
          '<div class="listing__foot">' +
            '<span class="listing__price">' +
              (l.priceFrom > 0 ? "$" + l.priceFrom + ' <span>/ session</span>' : "&nbsp;") +
            "</span>" +
            /* Compare toggle, mirroring listing-card.php. The pressed state
               is read from the store rather than carried in the DOM, because
               this function throws the DOM away on every filter change and a
               selection has to survive that. */
            '<span class="listing__acts">' +
              (function () {
                var slug = Compare.slugOf(l.url);
                var on = Compare.has(slug);
                return '<button class="cmpbtn" type="button" data-compare-toggle data-slug="' +
                  esc(slug) + '" aria-pressed="' + (on ? "true" : "false") + '">' +
                  ICON.scales + "<span data-compare-word>" +
                  (on ? "Selected" : "Compare") + "</span></button>";
              })() +
              '<a class="btn btn--sm btn--dark" href="' + esc(l.url || '#') + '">View profile<span class="btn__dot">' + ICON.arrow + "</span></a>" +
            "</span>" +
          "</div>" +
        "</div>" +
      "</article>";
    }

    /* The "showing" marker on the intent rows. Server-rendered from the
       query string on first paint; kept honest here as filters change. */
    var landed = false;

    function syncActiveRow() {
      $$(".intents__table tbody tr").forEach(function (tr) {
        var a = tr.querySelector("th a");
        if (!a) return;
        // Strip the fragment first. Row links end in #dirResults, and
        // without this the parsed value is "meditation#dirResults" and the
        // row never matches the filter it just applied.
        var qs = ((a.getAttribute("href") || "").split("?")[1] || "").split("#")[0];
        var q = new URLSearchParams(qs);
        var on = false;
        ["svc", "aud", "price", "format", "suburb"].forEach(function (k) {
          var want = q.get(k);
          if (want && (state[k] || []).indexOf(want) > -1) on = true;
        });
        tr.classList.toggle("is-active", on);
        if (on) { tr.setAttribute("aria-current", "true"); } else { tr.removeAttribute("aria-current"); }
        var tag = tr.querySelector(".intents__now");
        if (tag) tag.hidden = !on;
      });
    }

    function chips() {
      var box = $("#dirChips");
      if (!box) return;
      /* Three groups, in the order a visitor assembles them: what they're
         after, what kind of thing it is, and where. Anything left over
         (price, format, rating, search) trails behind. */
      var kind = [], area = [], rest = [];
      state.cats.forEach(function (c) { if (c !== locked.cat) kind.push(["cat", c, catNames[c] || c]); });
      state.spec.forEach(function (s) { if (s !== locked.spec) kind.push(["spec", s, specNames[s] || s]); });
      // Arrived from an intent row. Without these the list is filtered with
      // nothing on screen saying why and no way to undo it.
      state.svc.forEach(function (s) { if (!isLockedIntent("svc", s)) kind.push(["svc", s, svcNames[s] || s]); });
      state.aud.forEach(function (a) { if (!isLockedIntent("aud", a)) kind.push(["aud", a, audNames[a] || a]); });
      state.regions.forEach(function (r) { if (r !== locked.region) area.push(["region", r, regionNames[r] || r]); });
      state.suburbs.forEach(function (s) { area.push(["suburb", s, s]); });
      state.price.forEach(function (p) { if (!isLockedIntent("price", p)) rest.push(["price", p, p === "Free" ? "Free" : p]); });
      state.format.forEach(function (f) { if (!isLockedIntent("format", f)) rest.push(["format", f, f === "online" ? "Online" : "In person"]); });
      if (state.rating) rest.push(["rating", String(state.rating), state.rating + "+ rating"]);
      if (state.q) rest.push(["q", state.q, '"' + state.q + '"']);
      var out = kind.concat(area, rest);

      function chipHtml(c, cls) {
        return '<span class="chip' + (cls ? " " + cls : "") + '">' + esc(c[2]) +
          '<button type="button" data-clear-kind="' + c[0] + '" data-clear-val="' + esc(c[1]) +
          '" aria-label="Remove filter ' + esc(c[2]) + '">' + ICON.x + "</button></span>";
      }

      var wants = activeWants();
      box.innerHTML = wants.map(function (g) {
        return '<span class="chip chip--gf" style="--gf:' + esc(g.color) + '">' + esc(g.label) +
          '<button type="button" data-clear-want="' + esc(g.slug) +
          '" aria-label="Remove ' + esc(g.label) + '">' + ICON.x + "</button></span>";
      }).join("") +
      kind.map(function (c) { return chipHtml(c, ""); }).join("") +
      area.map(function (c) { return chipHtml(c, "chip--area"); }).join("") +
      rest.map(function (c) { return chipHtml(c, ""); }).join("") +
      (out.length + wants.length > 1
        ? '<button type="button" class="pill" id="clearAll">Clear all</button>' : "");

      /* Removing a want releases all its specialties at once — the same
         boxes the chip row manages, so both stay in step via their events. */
      $$("[data-clear-want]", box).forEach(function (b) {
        b.addEventListener("click", function () {
          var g = null;
          GF.forEach(function (x) { if (x.slug === b.dataset.clearWant) g = x; });
          if (!g) return;
          delete GFSel[g.slug];
          (g.specs || []).forEach(function (slug) {
            var boxEl = document.querySelector('[data-filter="spec"][value="' + slug + '"]');
            if (boxEl && boxEl.checked) { boxEl.checked = false; boxEl.dispatchEvent(new Event("change", { bubbles: true })); }
          });
        });
      });

      $$("[data-clear-kind]", box).forEach(function (b) {
        b.addEventListener("click", function () {
          var k = b.dataset.clearKind, v = b.dataset.clearVal;
          if (k === "q") { state.q = ""; var si = $("#dirQ"); if (si) si.value = ""; }
          else if (k === "rating") { state.rating = 0; }
          else {
            var key = k === "cat" ? "cats" : k === "region" ? "regions" : k;
            state[key] = state[key].filter(function (x) { return x !== v; });
          }
          syncInputs();
          state.page = 1;
          render();
        });
      });
      var all = $("#clearAll");
      if (all) all.addEventListener("click", function () {
        GFSel = {};
        state.cats = locked.cat ? [locked.cat] : [];
        state.regions = locked.region ? [locked.region] : [];
        state.spec = locked.spec ? [locked.spec] : [];
        state.svc = []; state.aud = []; state.suburbs = [];
        state.price = []; state.format = []; state.rating = 0; state.q = "";
        // Clearing never unlocks the page's own facet.
        if (locked.intentKey && state[locked.intentKey] !== undefined) state[locked.intentKey] = [locked.intentValue];
        var si = $("#dirQ"); if (si) si.value = "";
        syncInputs();
        state.page = 1;
        render();
      });
    }

    function syncInputs() {
      $$("[data-filter]").forEach(function (input) {
        var kind = input.dataset.filter, val = input.value;
        if (kind === "rating") {
          // rating is a single number, not a set — check it first
          input.checked = Number(val) === state.rating;
        } else if (input.type === "checkbox") {
          var key = kind === "cat" ? "cats" : kind === "region" ? "regions" : kind === "suburb" ? "suburbs" : kind;
          input.checked = state[key].indexOf(val) > -1;
        }
      });
    }

    /* The list grows in place instead of turning over a page at a time.
       state.page now counts how many pages have been LOADED rather than
       which one you are looking at, so ?pg= still restores your position
       when you come back from a listing — with the whole run re-rendered,
       not just the tenth page of it.

       The button is the mechanism rather than a fallback: an observer
       clicks it when it scrolls into view. That keeps the list reachable by
       keyboard, announceable to a screen reader, and working on anything
       without IntersectionObserver. Server-rendered pagination and rel=next
       are untouched, so crawlers still get real paginated URLs.

       Lives directly under the results grid, created once, so the three
       directory templates need no markup of their own. */
    var moreBox = document.createElement("div");
    moreBox.className = "loadmore";
    moreBox.id = "dirMore";
    root.parentNode.insertBefore(moreBox, root.nextSibling);

    var moreBtn = document.createElement("button");
    moreBtn.type = "button";
    moreBtn.className = "loadmore__btn";

    var moreNote = document.createElement("p");
    moreNote.className = "loadmore__note";
    moreNote.setAttribute("role", "status");
    moreNote.setAttribute("aria-live", "polite");

    /* Three dots while the next run is on its way. The listings are already
       in memory, so appending them is instantaneous — which reads as the
       page twitching rather than as more listings arriving. The pause is
       there to be seen: it gives the dots long enough to register, and it
       paces a fast scroll into distinct loads instead of one long blur. */
    var moreDots = document.createElement("div");
    moreDots.className = "loadmore__dots";
    moreDots.hidden = true;
    moreDots.setAttribute("aria-hidden", "true");
    moreDots.innerHTML = "<span></span><span></span><span></span>";

    moreBox.appendChild(moreBtn);
    moreBox.appendChild(moreDots);
    moreBox.appendChild(moreNote);

    var PAUSE = 1200;
    var pending = false;
    var timer = null;

    /* @param {boolean} typed  Was this a real click, rather than the scroll? */
    function loadNext(typed) {
      if (pending || moreBtn.hidden) return;
      pending = true;
      moreBtn.hidden = true;
      moreDots.hidden = false;
      moreNote.textContent = "Loading more listings…";

      timer = window.setTimeout(function () {
        pending = false;
        state.page += 1;
        render(); // more() puts the button back and clears the dots
        /* Focus the button again after it moves down the page, but only for
           a real click — doing it on a scroll-triggered load would snatch
           focus away from someone who never asked for it. */
        if (typed && !moreBtn.hidden) moreBtn.focus();
      }, PAUSE);
    }

    moreBtn.addEventListener("click", function () { loadNext(true); });

    /* Auto-load when the button comes into view. rootMargin starts it a
       screen early so the join is invisible at a normal scroll speed. The
       pending flag is the whole guard: cards whose images have not been
       measured yet are short, so without it the button can still be inside
       the margin when the next run lands and fire again immediately — the
       whole directory arriving in one frame instead of on scroll. */
    var watcher = null;
    if (window.IntersectionObserver) {
      watcher = new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting) loadNext(false);
      }, { rootMargin: "600px 0px" });
      watcher.observe(moreBtn);
    }

    function more(found, pages) {
      /* A filter changed while a load was in flight: the list has already
         been rebuilt back to page one, so the waiting timer would add a
         second page to a result set that never asked for it. Cancel it.
         When this runs from that timer's own render, pending is already
         false and there is nothing to cancel. */
      if (pending) {
        window.clearTimeout(timer);
        pending = false;
      }
      moreDots.hidden = true;

      var loaded = Math.min(state.page * PER_PAGE, found.length);

      if (found.length <= PER_PAGE) {
        moreBtn.hidden = true;
        moreNote.textContent = "";
        return;
      }

      if (state.page >= pages) {
        moreBtn.hidden = true;
        moreNote.textContent = "That's all " + found.length + " listings.";
        return;
      }

      moreBtn.hidden = false;
      moreBtn.textContent = "Show more listings";
      moreNote.textContent = "Showing " + loaded + " of " + found.length + ".";
    }

    /* What the grid currently holds, so a load-more can append the new run
       rather than rebuild three hundred cards to add ten. Any change to the
       filters or the sort changes the signature and forces a rebuild. */
    var drawn = { key: null, count: 0 };

    /* What people look for here, and whether the directory could answer.

       Search and filtering are entirely client-side, so none of this
       reaches analytics on its own — GA4's built-in site search only reads
       URL parameters, and this search never reloads the page.

       The valuable row is the one with results_count 0: a term somebody
       typed that the directory could not answer is demand it cannot serve
       yet, which is a recruitment list rather than a bug report. */
    var lastCount = 0;

    /* Page the card for this URL into the list and hand it back, or null
       when the current filters exclude that listing. Used by the map. */
    DirAPI.revealCard = function (url) {
      var found = DATA.listings.filter(matches).sort(sortFn);
      var idx = -1;
      found.forEach(function (l, i) { if (idx === -1 && l.url === url) idx = i; });
      if (idx === -1) return null;
      if (idx >= state.page * PER_PAGE) {
        state.page = Math.ceil((idx + 1) / PER_PAGE);
        render();
      }
      var card = null;
      $$(".listing__name a", root).forEach(function (a) {
        if (!card && a.getAttribute("href") === url) card = a.closest("article");
      });
      return card;
    };

    function render() {
      var found = DATA.listings.filter(matches).sort(sortFn);
      lastCount = found.length;
      var pages = Math.max(1, Math.ceil(found.length / PER_PAGE));
      if (state.page > pages) state.page = pages;
      var shown = found.slice(0, state.page * PER_PAGE);

      var key = JSON.stringify([state.cats, state.regions, state.spec, state.svc, state.aud,
                                state.suburbs, state.price, state.format, state.rating,
                                state.q, state.sort]);

      if (!shown.length) {
        root.innerHTML = '<div class="dir__empty"><h3 class="h3">Nothing matches those filters yet</h3>' +
          '<p class="muted" style="margin-top:.5rem">Try widening the area, or clear a filter to see more.</p></div>';
        drawn = { key: null, count: 0 };
      } else if (key === drawn.key && shown.length > drawn.count) {
        root.insertAdjacentHTML("beforeend", shown.slice(drawn.count).map(card).join(""));
        drawn.count = shown.length;
      } else {
        root.innerHTML = shown.map(card).join("");
        drawn = { key: key, count: shown.length };
      }

      var count = $("#dirCount");
      if (count) {
        /* Name the narrowest place that's locked. A suburb page locks both
           its suburb and its region, and naming the region there produced
           "4 of 130 listings in Perth Central" under a Mount Lawley
           heading — which read as though the suburb held 130 practices. */
        var place = locked.suburb ||
          (state.regions.length === 1 ? regionNames[state.regions[0]] : "");
        /* The total, plainly. How far down the run you are is the load-more
           note's job now — a range like "1–10 of 314" described a page, and
           there are no longer any pages to describe. */
        count.innerHTML = "<b>" + found.length + "</b> " +
          (found.length === 1 ? "listing" : "listings") +
          (place ? " in " + esc(place) : "");
        /* When a want is driving the filters, say what it means — the same
           line the chip's tooltip carries, typed out in the site's
           typewriter voice the first time each phrase appears. Renders
           run constantly (every filter change), so the animation keys on
           the phrase itself: same phrase, no re-typing. */
        var aw = activeWants().filter(function (g) { return g.line; }).slice(0, 2);
        if (aw.length) {
          var phrase = aw.map(function (g) { return g.line; }).join(" · ");
          var lineEl = document.createElement("span");
          lineEl.className = "dir__countline";
          count.appendChild(document.createTextNode(" "));
          count.appendChild(lineEl);
          var still = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
          var full = "— " + phrase;
          /* Activating a want ticks several checkboxes, each of which
             re-renders — so typing progress lives on the count element and
             each new render RESUMES the animation instead of restarting
             it (or worse, skipping straight to the end). */
          if (count.dataset.typedLine !== phrase) {
            count.dataset.typedLine = phrase;
            count.dataset.typedN = "0";
          }
          var i = parseInt(count.dataset.typedN || "0", 10);
          if (still || i >= full.length) {
            lineEl.textContent = full;
          } else {
            lineEl.setAttribute("aria-label", full);
            var caret = document.createElement("span");
            caret.className = "typewrite__caret";
            caret.setAttribute("aria-hidden", "true");
            var out = document.createElement("span");
            out.textContent = full.slice(0, i);
            lineEl.appendChild(out);
            lineEl.appendChild(caret);
            var step = function () {
              if (!lineEl.isConnected) return; // a newer render carries on from typedN
              out.textContent = full.slice(0, ++i);
              count.dataset.typedN = String(i);
              if (i < full.length) {
                setTimeout(step, 24 + Math.random() * 30 + (full[i - 1] === " " ? 30 : 0));
              } else {
                setTimeout(function () { caret.remove(); }, 1200);
              }
            };
            step();
          }
        } else {
          delete count.dataset.typedLine;
        }
      }

      /* With the filters hidden in a sheet, the button has to say how many
         are on — otherwise it's the only control whose state you can't see.
         The sheet's own button reports what you'd be going back to. */
      var badge = $("#filterCount");
      if (badge) {
        var on = state.cats.length + state.regions.length + state.spec.length +
                 state.svc.length + state.aud.length + state.suburbs.length +
                 state.price.length + state.format.length + (state.rating ? 1 : 0);
        badge.textContent = on ? String(on) : "";
      }
      if (done) {
        done.textContent = found.length === 1
          ? "Show 1 practice"
          : "Show " + found.length + " practices";
      }

      chips();
      more(found, pages);

      // Mark the intent row the current filter corresponds to, so the
      // table keeps saying where you are as filters change.
      syncActiveRow();

      /* Arrived from an intent row? The href carries #dirResults, so the
         browser has already jumped — but the chips render above the results
         after that jump and push them down, leaving the first card under the
         header. Re-anchor once, after the first paint, and never again: a
         second scroll while somebody is reading would be its own bug. */
      if (!landed) {
        landed = true;
        var cameFrom = new URLSearchParams(window.location.search);
        var viaIntent = ["svc", "aud", "price", "format"].some(function (k) { return cameFrom.has(k); });
        if (viaIntent && window.location.hash === "#dirResults") {
          requestAnimationFrame(function () {
            var box = $("#dirResults");
            if (box) box.scrollIntoView({ block: "start" });
          });
        }
      }

      // Keep the URL shareable and indexable-looking as filters change.
      //
      // A locked page keeps its clean URL — the page IS the category — but a
      // filter that arrived from an intent row still has to be reflected.
      // Without this, clearing the chip left ?svc= in the address bar and
      // the row still saying "showing" for a filter no longer applied.
      if (locked.cat || locked.region || locked.spec || locked.suburb) {
        var lp = new URLSearchParams(window.location.search);
        var moved = false;
        ["svc", "aud", "price", "format", "suburb"].forEach(function (k) {
          // The locked intent value is the page, not a parameter.
          var cur = (state[k === "suburb" ? "suburbs" : k] || []).filter(function (v) { return !isLockedIntent(k, v); }).join(",");
          if (cur) {
            if (lp.get(k) !== cur) { lp.set(k, cur); moved = true; }
          } else if (lp.has(k)) {
            lp.delete(k); moved = true;
          }
        });
        /* How far down the run you have loaded, so returning from a listing
           puts you back where you were. A locked page keeps its clean URL
           otherwise, but without this the whole point of growing the list
           is lost the moment anyone clicks through to a practice. */
        if (state.page > 1) {
          if (lp.get("pg") !== String(state.page)) { lp.set("pg", String(state.page)); moved = true; }
        } else if (lp.has("pg")) {
          lp.delete("pg"); moved = true;
        }
        if (moved) {
          var lqs = lp.toString();
          history.replaceState(null, "", lqs ? "?" + lqs : window.location.pathname);
        }
        return;
      }
      var p = new URLSearchParams();
      if (state.cats.length) p.set("cat", state.cats.join(","));
      if (state.regions.length) p.set("region", state.regions.join(","));
      if (state.spec.length) p.set("spec", state.spec.join(","));
      if (state.svc.length) p.set("svc", state.svc.join(","));
      if (state.aud.length) p.set("aud", state.aud.join(","));
      if (state.suburbs.length) p.set("suburb", state.suburbs.join(","));
      if (state.q) p.set("q", state.q);
      if (state.page > 1) p.set("pg", String(state.page));
      var qs = p.toString();
      history.replaceState(null, "", qs ? "?" + qs : window.location.pathname);
    }

    /* Typewriter headings (data-typewrite): type the text out on every
       load, then reveal the sibling marked .typewrite__after. The text is
       server-rendered for crawlers and no-JS; reduced motion leaves it. */
    $$("[data-typewrite]").forEach(function (el) {
      if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        var after0 = el.nextElementSibling; if (after0 && after0.classList.contains("typewrite__after")) after0.classList.add("is-shown");
        return;
      }
      var full = el.textContent.trim(), after = el.nextElementSibling;
      el.setAttribute("aria-label", full);
      el.textContent = "";
      var out = document.createElement("span"), caret = document.createElement("span");
      caret.className = "typewrite__caret"; caret.setAttribute("aria-hidden", "true");
      el.appendChild(out); el.appendChild(caret);
      var i = 0;
      var step = function () {
        out.textContent = full.slice(0, ++i);
        if (i < full.length) {
          // a little unevenness reads as typing rather than a progress bar
          setTimeout(step, 28 + Math.random() * 38 + (full[i - 1] === " " ? 40 : 0));
        } else {
          if (after && after.classList.contains("typewrite__after")) after.classList.add("is-shown");
          setTimeout(function () { caret.remove(); }, 1400);
        }
      };
      setTimeout(step, 650); // after the spine has dropped in
    });

    /* Toolbar (directory-toolbar.php): the specialty typeahead filters the
       checkbox list as you type — the inputs are the same [data-filter]
       checkboxes, just searchable — and opening one popover closes the rest. */
    $$("[data-facet-search]").forEach(function (box) {
      var list = $(box.dataset.facetSearch);
      if (!list) return;
      var rows = $$("[data-facet-label]", list), empty = $(".facetlist__empty", list);
      box.addEventListener("input", function () {
        var q = box.value.trim().toLowerCase(), shown = 0;
        rows.forEach(function (r) {
          var hit = !q || r.dataset.facetLabel.indexOf(q) > -1;
          r.hidden = !hit; if (hit) shown++;
        });
        if (empty) empty.hidden = shown > 0;
      });
    });
    /* The one-time nudge on a facet button (directory-toolbar.php). Shown
       until the visitor opens that popover once, or for ten seconds,
       whichever comes first; remembered per browser so it never nags. */
    /* The nudge on a facet button (directory-toolbar.php): a pulsing ring
       and a tooltip, shown on every page view once the toolbar is on screen,
       and dismissed for that view the moment the popover opens or after ten
       seconds. Nothing is remembered between views — it comes back on refresh. */
    $$("[data-hint-key]").forEach(function (host) {
      var det = host.querySelector("details");
      if (!det) return;
      var hide = function () { host.classList.remove("is-hinting"); };
      det.addEventListener("toggle", function () { if (det.open) hide(); });
      var started = false;
      var inView = function () { var r = host.getBoundingClientRect(); return r.top < window.innerHeight * 0.92 && r.bottom > 0; };
      var start = function () {
        if (started) return;
        started = true;
        window.removeEventListener("scroll", tick);
        // A host can ask to hold back (data-hint-delay, ms) so two hints
        // on one toolbar take turns instead of bobbing side by side.
        var delay = parseInt(host.getAttribute("data-hint-delay") || "0", 10);
        setTimeout(function () {
          host.classList.add("is-hinting");
          setTimeout(hide, 10000);
        }, delay);
      };
      var tick = function () { if (inView()) start(); };
      window.addEventListener("scroll", tick, { passive: true });
      tick();
    });
    $$("[data-popover]").forEach(function (d) {
      d.addEventListener("toggle", function () {
        if (!d.open) return;
        $$("[data-popover]").forEach(function (o) { if (o !== d) o.open = false; });
      });
    });
    document.addEventListener("click", function (e) {
      // An open sheet is moved to the body, so "inside" means inside the
      // details or inside its panel, wherever that panel currently lives.
      $$("[data-popover][open]").forEach(function (d) {
        var panel = d.oriaPanel;
        if (d.contains(e.target)) return;
        if (panel && panel.contains(e.target)) return;
        d.open = false;
      });
    });

    $$("[data-filter]").forEach(function (input) {
      input.addEventListener("change", function () {
        var kind = input.dataset.filter, val = input.value;
        if (kind === "rating") {
          state.rating = input.checked ? Number(val) : 0;
        } else {
          var key = kind === "cat" ? "cats" : kind === "region" ? "regions" : kind === "suburb" ? "suburbs" : kind;
          if (input.checked) { if (state[key].indexOf(val) === -1) state[key].push(val); }
          else { state[key] = state[key].filter(function (x) { return x !== val; }); }
        }
        state.page = 1;
        /* Two controls can now drive the same filter — a specialty tag under
           the count and its checkbox in the sidebar — so every input has to
           be written back from state, or the two disagree about what is on.
           Setting .checked in script fires no change event, so this cannot
           loop. */
        syncInputs();
        render();

        /* Only when a filter goes ON. Reporting the off-switch too would
           double the volume to say the same thing twice, and the count
           after the redraw already shows whether it narrowed too far. */
        if (input.checked || (kind === "rating" && state.rating)) {
          pushEvent("dir_filter", {
            filter_name: kind,
            filter_value: val,
            results_count: lastCount
          });
        }
      });
    });

    var sortSel = $("#dirSort");
    if (sortSel) sortSel.addEventListener("change", function () { state.sort = sortSel.value; state.page = 1; render(); });

    var qInput = $("#dirQ");
    if (qInput) {
      qInput.value = state.q;
      var t;
      /* Two timers on purpose. The short one redraws as you type; the long
         one reports, and only once you have stopped — otherwise "massage"
         arrives as seven searches, six of which nobody made. The same term
         is never reported twice in a row for the same reason. */
      var reportTimer, lastReported = "";
      qInput.addEventListener("input", function () {
        clearTimeout(t);
        t = setTimeout(function () { state.q = qInput.value.trim(); state.page = 1; render(); }, 180);

        clearTimeout(reportTimer);
        reportTimer = setTimeout(function () {
          var term = state.q.toLowerCase();
          if (term.length < 2 || term === lastReported) return;
          lastReported = term;
          pushEvent("dir_search", { search_term: term, results_count: lastCount });
        }, 1200);
      });

      initDirSuggest(qInput);
    }

    /* Suggestions for #dirQ, drawn from what this page is showing.
       Everything except the query itself is applied first, so a suggestion
       can never lead to an empty result — and the counts beside each one
       are the counts you will get. */
    function initDirSuggest(input) {
      var panel = document.getElementById("dirQList");
      if (!panel) return;
      var items = [], active = -1, timer;

      function poolWithoutQuery() {
        var saved = state.q;
        state.q = "";
        var out = (DATA.listings || []).filter(matches);
        state.q = saved;
        return out;
      }

      function build(raw) {
        var q = raw.trim().toLowerCase();
        if (q.length < 2) return [];
        var pool = poolWithoutQuery();
        if (!pool.length) return [];

        var specN = {}, svcN = {}, subN = {}, out = [];
        pool.forEach(function (l) {
          (l.spec || []).forEach(function (s) { specN[s] = (specN[s] || 0) + 1; });
          (l.svc || []).forEach(function (s) { svcN[s] = (svcN[s] || 0) + 1; });
          if (l.suburb) subN[l.suburb] = (subN[l.suburb] || 0) + 1;
        });

        pool.forEach(function (l) {
          var at = (l.name || "").toLowerCase().indexOf(q);
          if (at > -1) {
            out.push({ kind: "Practice", label: l.name, sub: l.suburb,
                       url: l.url, rank: at === 0 ? 0 : 1 });
          }
        });

        /* A style or specialty already on this page. Applied as a filter,
           never followed, so the visitor keeps the page they chose. */
        Object.keys(specN).forEach(function (id) {
          var name = specNames[id] || id;
          var at = name.toLowerCase().indexOf(q);
          if (at > -1 && !(state.spec || []).length) {
            out.push({ kind: "Style", label: name, sub: specN[id] + " here",
                       apply: ["spec", id], rank: at === 0 ? 2 : 3 });
          }
        });
        Object.keys(svcN).forEach(function (id) {
          var name = svcNames[id] || id;
          var at = name.toLowerCase().indexOf(q);
          // Skip anything a specialty of the same name already offered.
          var dupe = out.some(function (r) { return r.label.toLowerCase() === name.toLowerCase(); });
          if (at > -1 && !dupe) {
            out.push({ kind: "Style", label: name, sub: svcN[id] + " here",
                       apply: ["svc", id], rank: at === 0 ? 2 : 3 });
          }
        });
        Object.keys(subN).forEach(function (name) {
          if (name.toLowerCase().indexOf(q) === 0) {
            out.push({ kind: "Suburb", label: name, sub: subN[name] + " here",
                       apply: ["suburbs", name], rank: 4 });
          }
        });

        out.sort(function (a, b) { return a.rank - b.rank || a.label.localeCompare(b.label); });
        return out.slice(0, 8);
      }

      function close() {
        panel.hidden = true;
        panel.innerHTML = "";
        items = [];
        active = -1;
        input.setAttribute("aria-expanded", "false");
        input.removeAttribute("aria-activedescendant");
      }

      function paint() {
        items = build(input.value);
        if (!items.length) { close(); return; }
        panel.innerHTML = items.map(function (r, i) {
          return '<span class="osearch__opt" role="option" id="dirQ-o' + i +
            '" data-i="' + i + '" aria-selected="false"><b>' + esc(r.label) +
            "</b><em>" + esc(r.kind) + (r.sub ? " · " + esc(r.sub) : "") + "</em></span>";
        }).join("");
        panel.hidden = false;
        panel.setAttribute("role", "listbox");
        input.setAttribute("aria-expanded", "true");
        active = -1;
      }

      function highlight(next) {
        var opts = panel.querySelectorAll(".osearch__opt");
        if (!opts.length) return;
        if (active > -1 && opts[active]) {
          opts[active].classList.remove("is-active");
          opts[active].setAttribute("aria-selected", "false");
        }
        active = (next + opts.length) % opts.length;
        opts[active].classList.add("is-active");
        opts[active].setAttribute("aria-selected", "true");
        input.setAttribute("aria-activedescendant", opts[active].id);
        opts[active].scrollIntoView({ block: "nearest" });
      }

      function choose(i) {
        var r = items[i];
        if (!r) return;
        if (r.url) { window.location.href = r.url; return; }
        var key = r.apply[0], val = r.apply[1];
        if (state[key] && state[key].indexOf(val) === -1) state[key].push(val);
        // The filter says it better than the words did, so the box empties.
        state.q = "";
        input.value = "";
        state.page = 1;
        close();
        render();
      }

      input.addEventListener("input", function () {
        clearTimeout(timer);
        timer = setTimeout(paint, 120);
      });
      input.addEventListener("focus", function () {
        if (input.value.trim().length > 1) paint();
      });
      input.addEventListener("keydown", function (e) {
        if (e.key === "ArrowDown" && panel.hidden) { paint(); return; }
        if (panel.hidden) return;
        if (e.key === "ArrowDown") { e.preventDefault(); highlight(active + 1); }
        else if (e.key === "ArrowUp") { e.preventDefault(); highlight(active - 1); }
        else if (e.key === "Enter" && active > -1) { e.preventDefault(); choose(active); }
        else if (e.key === "Escape") { close(); }
      });
      panel.addEventListener("mousedown", function (e) {
        var el = e.target.closest(".osearch__opt");
        if (!el) return;
        e.preventDefault();
        choose(Number(el.dataset.i));
      });
      document.addEventListener("click", function (e) {
        if (!panel.hidden && !input.parentNode.contains(e.target)) close();
      });
    }

    /* Filters: a collapsible sidebar on desktop, a slide-up sheet on
       phones. Same button, same panel — only the presentation differs,
       so the filter logic below never has to know which it is. */
    var filterToggle = $("#filterToggle");
    var panel = $("#dirFilters");
    var scrim = $("#dirScrim");
    var done = $("#dirSheetDone");
    // Must track the CSS breakpoint above, where .dir loses its sidebar.
    var isSheet = function () { return window.matchMedia("(max-width: 1000px)").matches; };

    function openSheet(open) {
      panel.classList.toggle("is-open", open);
      if (scrim) scrim.classList.toggle("is-on", open);
      if (done) done.classList.toggle("is-on", open);
      // Stop the page behind the sheet scrolling with it.
      document.body.style.overflow = open ? "hidden" : "";
      filterToggle.setAttribute("aria-expanded", open ? "true" : "false");

      // As a sheet it is a modal dialog; as a sidebar it is just a panel,
      // so the role is only true while it is open over the page.
      if (open) {
        panel.setAttribute("role", "dialog");
        panel.setAttribute("aria-modal", "true");
        panel.scrollTop = 0;
        var close = panel.querySelector("[data-sheet-close]");
        if (close) close.focus();
      } else {
        panel.removeAttribute("role");
        panel.removeAttribute("aria-modal");
        filterToggle.focus();
      }
    }

    if (filterToggle) {
      filterToggle.addEventListener("click", function () {
        if (isSheet()) {
          openSheet(!panel.classList.contains("is-open"));
        } else {
          var collapsed = panel.classList.toggle("is-collapsed");
          filterToggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
        }
      });
    }
    if (scrim) scrim.addEventListener("click", function () { openSheet(false); });
    if (done) done.addEventListener("click", function () { openSheet(false); });
    $$("[data-sheet-close]").forEach(function (b) {
      b.addEventListener("click", function () { openSheet(false); });
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && panel.classList.contains("is-open")) openSheet(false);
    });
    // Rotating to landscape shouldn't strand an open sheet or a locked page.
    window.addEventListener("resize", function () {
      if (!isSheet() && panel.classList.contains("is-open")) openSheet(false);
    });

    /* Long filter groups (Specialty runs past seventy) show a dozen until
       asked for the rest. */
    $$("[data-filter-more]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var box = btn.closest(".filterbox");
        var open = box.classList.toggle("is-expanded");
        btn.textContent = open ? btn.dataset.less : btn.dataset.more;
      });
    });

    // Region shortcuts on the directory mini-map
    $$("#dirMap .region").forEach(function (g) {
      g.addEventListener("click", function () {
        var id = g.dataset.region;
        var i = state.regions.indexOf(id);
        if (i > -1) state.regions.splice(i, 1); else state.regions.push(id);
        syncInputs();
        state.page = 1;
        render();
      });
    });

    syncInputs();
    render();
  }

  /* --- Forms ---------------------------------------------------------- */
  /* Prototype behaviour: confirm in place instead of navigating, so the
     copy for every success state is designed rather than assumed. */
  function initForms() {
    $$("[data-demo-form]").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var msg = form.dataset.demoForm;
        var out = form.querySelector("[data-demo-result]") || document.createElement("p");
        out.setAttribute("data-demo-result", "");
        out.className = "notice";
        out.style.marginTop = "1rem";
        out.innerHTML =
          '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>' +
          "<span>" + esc(msg) + "</span>";
        if (!out.parentNode) form.appendChild(out);
        form.reset();
      });
    });
  }

  /* --- Odds and ends --------------------------------------------------- */
  function initYear() {
    $$("[data-year]").forEach(function (el) { el.textContent = String(new Date().getFullYear()); });
  }

  function initCarousels() {
    $$("[data-scroller]").forEach(function (wrap) {
      var track = $(".scroller__track", wrap) || wrap.querySelector("[data-scroller-track]");
      if (!track) return;
      $$("[data-scroll]", wrap).forEach(function (btn) {
        btn.addEventListener("click", function () {
          var dir = btn.dataset.scroll === "next" ? 1 : -1;
          track.scrollBy({ left: dir * Math.min(track.clientWidth * 0.8, 520), behavior: reduced ? "auto" : "smooth" });
        });
      });
    });
  }

  /* --- Analytics ------------------------------------------------------- */
  /* Two destinations, one click. The site's own counter is what a paying
     practitioner sees on their listing ("is this sending me people?");
     the dataLayer push is what turns the same tap into a GA4 conversion.
     Neither is allowed to break the tap, and neither sets a cookie of
     ours or sends anything about who clicked. */

  /* GA4 names for our short internal codes. The internal ones stay short
     because they're stored per listing per day, forever. */
  var LEAD_EVENTS = {
    tel: "contact_phone",
    mail: "contact_email",
    web: "outbound_website",
    book: "booking_click",
    dir: "directions_click",
    enq: "enquiry_started"
  };

  function pushEvent(name, params) {
    if (!name) return;
    window.dataLayer = window.dataLayer || [];
    var payload = { event: name };
    var ctx = window.ORIA_PROFILE;
    if (ctx) {
      payload.listing_id = ctx.id;
      payload.listing_category = ctx.category;
      payload.listing_suburb = ctx.suburb;
      payload.listing_plan = ctx.plan;
    }
    if (params) {
      for (var k in params) if (Object.prototype.hasOwnProperty.call(params, k)) payload[k] = params[k];
    }
    try { window.dataLayer.push(payload); } catch (err) { /* never block */ }
  }

  function initTracking() {
    document.addEventListener("click", function (e) {
      var el = e.target.closest && e.target.closest("[data-oria-track]");
      if (!el) return;
      var id = parseInt(el.getAttribute("data-oria-id"), 10);
      var type = el.getAttribute("data-oria-track");
      if (!id || !type) return;

      pushEvent(LEAD_EVENTS[type] || type);

      if (!window.ORIA_TRACK || !navigator.sendBeacon) return;
      try {
        navigator.sendBeacon(
          ORIA_TRACK.url,
          new Blob([JSON.stringify({ id: id, type: type })], { type: "application/json" })
        );
      } catch (err) { /* counting must never break a tap */ }
    });

    /* Profile views: one event per page, carrying the category, suburb
       and plan so GA4 can answer "which categories convert?"

       The same load also beacons the view to the site's own counter. That
       used to be done server-side on the `wp` hook, but listing pages are
       served from the page cache and a cached response never runs PHP, so
       every visitor after the first went uncounted — in the one figure a
       practitioner is shown to justify paying for the listing. A REST call
       is never cached. The endpoint re-checks that this is not the owner
       and not a crawler before it counts. */
    if (window.ORIA_PROFILE) {
      pushEvent("practice_view");

      var pid = parseInt(window.ORIA_PROFILE.id, 10);
      if (pid && window.ORIA_TRACK && navigator.sendBeacon) {
        try {
          navigator.sendBeacon(
            ORIA_TRACK.url,
            new Blob([JSON.stringify({ id: pid, type: "view" })], { type: "application/json" })
          );
        } catch (err) { /* counting must never break a page */ }
      }
    }

    /* Claim funnel. Started fires on the first real interaction with the
       form rather than on render, so a listing that merely displays the
       form doesn't report an intent nobody had. */
    /* Three kinds of element carry data-oria-event, and they must not be
       treated alike.

       A form fires on first input, so a page that merely renders one does
       not report an intent nobody had. A link or a button fires on CLICK —
       these used to fire on render along with everything else, which meant
       "category_compare" was pushed on every category page view whether or
       not anyone pressed it, and any report built on it was counting
       impressions while calling them clicks.

       Anything else still fires on render, because a non-interactive
       element carrying an event name is a state marker: claim_completed
       exists on the page precisely because the claim completed.

       Clicks are delegated so links drawn later are covered too, and each
       carries its href and label — enough for GTM to break a single event
       down by destination without a bespoke tag per link. */
    document.addEventListener("click", function (e) {
      var el = e.target.closest && e.target.closest("a[data-oria-event], button[data-oria-event]");
      if (!el) return;
      pushEvent(el.getAttribute("data-oria-event"), {
        link_url: el.getAttribute("href") || "",
        link_text: (el.textContent || "").replace(/\s+/g, " ").trim().slice(0, 100)
      });
    });

    $$("[data-oria-event]").forEach(function (el) {
      var name = el.getAttribute("data-oria-event");
      if (el.tagName === "FORM") {
        var fired = false;
        el.addEventListener("input", function () {
          if (fired) return;
          fired = true;
          pushEvent(name);
        });
        return;
      }
      if (el.tagName === "A" || el.tagName === "BUTTON") {
        return; // handled by the delegated click above
      }
      pushEvent(name);
    });

    /* Lead submissions round-trip through a redirect, so the completed
       event fires on the state the server sends back — a real stored
       lead, not just a button press. */
    var params = new URLSearchParams(window.location.search);
    if (params.get("olead") === "sent") {
      if (params.has("omatched")) {
        pushEvent("match_submitted", { matched_count: parseInt(params.get("omatched"), 10) || 0 });
      } else {
        pushEvent("enquiry_submitted");
      }
    }
  }

  /* --- Copy-to-clipboard ----------------------------------------------- */
  /* Used by the share kit's suggested post. The clipboard API needs a
     secure context and can be refused outright, so the textarea-and-
     execCommand path stays as a fallback — the same lesson the article
     share button taught us. */
  function initCopy() {
    $$("[data-copy-target]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var src = document.querySelector(btn.getAttribute("data-copy-target"));
        if (!src) return;
        var text = src.value !== undefined ? src.value : src.textContent;

        var done = function () {
          btn.classList.add("is-copied");
          pushEvent("share_copy");
          window.setTimeout(function () { btn.classList.remove("is-copied"); }, 1600);
        };

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done, function () { legacy(); });
        } else {
          legacy();
        }

        function legacy() {
          try {
            src.removeAttribute("readonly");
            src.select();
            src.setSelectionRange(0, text.length);
            document.execCommand("copy");
            src.setAttribute("readonly", "readonly");
            done();
          } catch (err) { /* leave the text selected for a manual copy */ }
        }
      });
    });

    /* Which network a practitioner actually uses is worth knowing. */
    $$("[data-oria-share]").forEach(function (a) {
      a.addEventListener("click", function () {
        pushEvent("share_click", { network: a.getAttribute("data-oria-share") });
      });
    });
  }

  /* --- Wellness Finder wizard ------------------------------------------ */
  /* The form arrives with every question on the page and works that way if
     this never runs. Here we fold it into one question at a time: choosing
     an option advances, and the last step reveals the submit. Answers live
     in the radios throughout, so a normal submit carries them however far
     the visitor got. */
  function initFinder() {
    var form = document.querySelector("[data-finder]");
    if (!form) return;

    var steps = $$("[data-finder-step]", form);
    var submit = form.querySelector(".finder__submit");
    if (steps.length < 2 || !submit) return;

    var progress = form.querySelector("[data-finder-progress]");
    var fill = form.querySelector("[data-finder-fill]");
    var count = form.querySelector("[data-finder-count]");
    var at = 0;
    var back = false;

    form.classList.add("is-wizard");
    if (progress) progress.hidden = false;
    $$("[data-finder-nav]", form).forEach(function (nav) { nav.hidden = false; });

    /* A hidden step has to leave the tab order too, or a keyboard user tabs
       into questions they cannot see. */
    function show(i) {
      at = Math.max(0, Math.min(i, steps.length));
      steps.forEach(function (step, n) {
        var live = n === at;
        step.classList.toggle("is-live", live);
        step.classList.toggle("is-back", live && back);
        step.hidden = !live;
        $$("input", step).forEach(function (input) { input.tabIndex = live ? 0 : -1; });
      });

      var done = at >= steps.length;
      submit.classList.toggle("is-live", done);

      if (fill) fill.style.width = ((done ? steps.length : at) / steps.length) * 100 + "%";
      if (count) {
        count.textContent = done
          ? "Ready"
          : "Question " + (at + 1) + " of " + steps.length;
      }

      /* Move focus to the new question so it's announced, but don't yank
         the page around on first paint. */
      var target = done ? submit.querySelector("button") : steps[at].querySelector("legend");
      if (target && at > 0) {
        target.setAttribute("tabindex", "-1");
        target.focus({ preventScroll: true });
      }
      back = false;
    }

    form.addEventListener("change", function (e) {
      if (!e.target || e.target.type !== "radio") return;
      pushEvent("finder_answer", { step: e.target.name, answer: e.target.value });
      /* A beat, so the option is visibly chosen before the step moves on. */
      window.setTimeout(function () { show(at + 1); }, 180);
    });

    $$("[data-finder-back]", form).forEach(function (btn) {
      btn.addEventListener("click", function () { back = true; show(at - 1); });
    });
    $$("[data-finder-skip]", form).forEach(function (btn) {
      btn.addEventListener("click", function () { show(at + 1); });
    });

    form.addEventListener("submit", function () {
      pushEvent("finder_complete", { answered: $$("input:checked", form).length });
    });

    show(0);
  }

  /* --- Get-matched dialog ---------------------------------------------- */
  /* Desktop gets the form as a modal (opened from the hero card); mobile
     keeps the in-page band, so triggers fall through to their #enquire
     anchor there. After a submission the server redirects back with
     ?olead=sent — reopen the dialog so the confirmation isn't sealed
     inside a closed modal. */
  function initMatchDialog() {
    var dialog = document.getElementById("matchDialog");
    if (!dialog || typeof dialog.showModal !== "function") return;
    var isDesktop = function () { return window.matchMedia("(min-width: 901px)").matches; };

    document.addEventListener("click", function (e) {
      var open = e.target.closest && e.target.closest("[data-match-open]");
      if (open && isDesktop()) {
        e.preventDefault();
        dialog.showModal();
        return;
      }
      if (e.target.closest && e.target.closest("[data-match-close]")) {
        dialog.close();
        return;
      }
      /* A click on the backdrop lands on the <dialog> itself. */
      if (e.target === dialog) dialog.close();
    });

    var params = new URLSearchParams(window.location.search);
    if (params.get("olead") && isDesktop()) dialog.showModal();
  }

  /* --- Get-matched comboboxes ------------------------------------------ */
  /* Type-ahead for the service and area pickers: fifty services and
     ninety suburbs make a select a wall. The visible input posts its
     text regardless (the server resolves names too), so this is a
     convenience layer, not a dependency — picking a suggestion just
     fills the hidden slug so matching is exact. */
  function initMatchCombos() {
    var data = window.ORIA_MATCH;
    if (!data) return;

    $$("[data-matchcombo]").forEach(function (wrap) {
      var options = data[wrap.getAttribute("data-matchcombo")] || [];
      var input = wrap.querySelector("input[type=text]");
      var hidden = wrap.querySelector("input[type=hidden]");
      var panel = wrap.querySelector("[data-matchcombo-panel]");
      if (!input || !hidden || !panel) return;

      var active = -1, shown = [];

      function close() {
        panel.hidden = true;
        panel.innerHTML = "";
        input.setAttribute("aria-expanded", "false");
        active = -1;
        shown = [];
      }

      function pick(opt) {
        input.value = opt.l;
        hidden.value = opt.s;
        close();
      }

      /* An exact name typed without picking still resolves. */
      function syncExact() {
        var q = input.value.trim().toLowerCase();
        var hit = null;
        for (var i = 0; i < options.length; i++) {
          if (options[i].l.toLowerCase() === q) { hit = options[i]; break; }
        }
        hidden.value = hit ? hit.s : "";
      }

      function render() {
        var q = input.value.trim().toLowerCase();
        if (!q) { close(); return; }
        shown = options.filter(function (o) {
          return o.l.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);
        if (!shown.length) { close(); return; }
        var esc = function (s) {
          return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        };
        panel.innerHTML = shown.map(function (o, i) {
          return '<span class="oform-lookup__item' + (i === active ? " is-active" : "") + '" role="option" data-i="' + i + '"><b>'
            + esc(o.l) + "</b><em>" + esc(o.g) + "</em></span>";
        }).join("");
        panel.hidden = false;
        input.setAttribute("aria-expanded", "true");
      }

      input.addEventListener("input", function () { active = -1; syncExact(); render(); });
      input.addEventListener("keydown", function (e) {
        if (panel.hidden) return;
        if (e.key === "ArrowDown") { e.preventDefault(); active = Math.min(active + 1, shown.length - 1); render(); }
        else if (e.key === "ArrowUp") { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
        else if (e.key === "Enter") {
          if (active >= 0) { e.preventDefault(); pick(shown[active]); }
          else if (shown.length === 1) { e.preventDefault(); pick(shown[0]); }
        } else if (e.key === "Escape") { close(); }
      });
      panel.addEventListener("mousedown", function (e) {
        var item = e.target.closest && e.target.closest("[data-i]");
        if (item) { e.preventDefault(); pick(shown[parseInt(item.getAttribute("data-i"), 10)]); }
      });
      input.addEventListener("blur", function () { window.setTimeout(close, 150); });
    });
  }

  /* --- Featured rotation ---------------------------------------------- */
  /* Every featured listing takes a turn on the home section: groups of
     three, next group every 30s. Paused while hovered (no yanking a card
     someone is about to click), while the tab is hidden, and entirely for
     reduced-motion users — their randomised first group still varies per
     visit, so exposure stays fair. */
  function initFeaturedRotator() {
    if (reduced) return;
    $$(".featrotator").forEach(function (rot) {
      var groups = rot.querySelectorAll(".featrotator__group");
      if (groups.length < 2) return;

      var i = 0, paused = false;
      rot.addEventListener("mouseenter", function () { paused = true; });
      rot.addEventListener("mouseleave", function () { paused = false; });
      rot.addEventListener("focusin", function () { paused = true; });
      rot.addEventListener("focusout", function () { paused = false; });

      // data-offset staggers side-by-side rotators so they take turns
      // changing instead of blinking in unison.
      window.setTimeout(function () {
        window.setInterval(function () {
          if (paused || document.hidden) return;
          groups[i].classList.remove("is-active");
          groups[i].hidden = true;
          i = (i + 1) % groups.length;
          groups[i].hidden = false;
          void groups[i].offsetWidth; // restart the entrance animation
          groups[i].classList.add("is-active");
        }, parseInt(rot.dataset.rotate, 10) || 30000);
      }, parseInt(rot.dataset.offset, 10) || 0);
    });
  }

  /* --- Journal article ------------------------------------------------ */
  /* The pull quote rises word by word. Words are wrapped before initReveal
     runs so the .is-in class the reveal system adds finds them ready; the
     stagger itself is pure CSS via the --i custom property. */
  function initPullquote() {
    $$("[data-pullquote]").forEach(function (el) {
      var words = el.textContent.trim().split(/\s+/);
      el.textContent = "";
      words.forEach(function (w, i) {
        var span = document.createElement("span");
        span.className = "pq-w";
        span.style.setProperty("--i", String(i));
        span.textContent = w;
        el.appendChild(span);
        if (i < words.length - 1) el.appendChild(document.createTextNode(" "));
      });
    });
  }

  /* --- Guide scrollspy --------------------------------------------------- */
  /* Keeps the "In this guide" link for the section under the reader's eye
     lit. Driven by the H2 anchors the TOC already points at, so the two can
     never disagree about what a section is. */
  function initGuideToc() {
    var links = $$(".jtoc__list a[href^='#']");
    if (!links.length || !("IntersectionObserver" in window)) return;
    var byId = {};
    links.forEach(function (a) { byId[a.getAttribute("href").slice(1)] = a; });
    var heads = Object.keys(byId)
      .map(function (id) { return document.getElementById(id); })
      .filter(Boolean);
    if (!heads.length) return;

    function light(id) {
      links.forEach(function (a) {
        a.classList.toggle("is-here", a.getAttribute("href") === "#" + id);
      });
    }

    /* The active section is the last heading above the reading line (30%
       down the viewport). An observer per heading just tells us "something
       crossed"; the arithmetic picks which. */
    var line = function () { return window.innerHeight * 0.3; };
    function pick() {
      var current = heads[0].id;
      for (var i = 0; i < heads.length; i++) {
        if (heads[i].getBoundingClientRect().top <= line()) current = heads[i].id;
      }
      light(current);
    }

    var io = new IntersectionObserver(pick, { rootMargin: "0px 0px -60% 0px" });
    heads.forEach(function (h) { io.observe(h); });
    window.addEventListener("scroll", pick, { passive: true });
    pick();
  }

  /* --- Class day filter -------------------------------------------------- */
  /* Two levels: sessions carry the days the server read off their own day
     field, and a class hides only when every one of its sessions is hidden.
     A class with no sessions at all -- by arrangement -- always shows: it is
     still available on a Tuesday. */
  function initClasses() {
    var root = document.querySelector("[data-classes]");
    if (!root) return;
    var chips = $$("[data-cls-day]", root);
    if (!chips.length) return;
    var rows = $$(".classrow", root);
    var empty = root.querySelector("[data-cls-empty]");

    function apply(day) {
      var shown = 0;
      rows.forEach(function (li) {
        var sessions = $$("[data-cls-days]", li);
        var visible = 0;
        sessions.forEach(function (sess) {
          var days = (sess.dataset.clsDays || "").split(" ").filter(Boolean);
          /* A session naming no day is "any day": it survives every filter. */
          var ok = day === "all" || !days.length || days.indexOf(day) > -1;
          sess.hidden = !ok;
          if (ok) visible++;
        });
        var keep = !sessions.length || visible > 0;
        li.hidden = !keep;
        if (keep) shown++;
      });
      if (empty) empty.hidden = shown > 0;
    }

    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        chips.forEach(function (c) { c.classList.toggle("is-on", c === chip); });
        apply(chip.dataset.clsDay);
      });
    });
  }

  /* --- Saved listings --------------------------------------------------- */
  /* Kept on the device, never sent anywhere. No account to create, and
     nothing for us to hold. The cost is honest and the saved page says it:
     clear the browser and the list goes with it. */
  var SAVE_KEY = "oria_saved";

  function savedIds() {
    try {
      var raw = window.localStorage.getItem(SAVE_KEY);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr.map(String) : [];
    } catch (e) {
      /* Private windows and blocked site data throw on read. */
      return [];
    }
  }

  function writeSaved(ids) {
    try {
      window.localStorage.setItem(SAVE_KEY, JSON.stringify(ids));
      paintSavedNav();
      return true;
    } catch (e) {
      return false;
    }
  }

  /* The count in the nav, and its twin in the drawer.

     Both ship hidden inside cached markup -- LiteSpeed serves one copy of the
     header to everybody, so there is no number the server could have printed.
     This is the only place it can be known. */
  function paintSavedNav() {
    var n = savedIds().length;
    $$("[data-saved-nav]").forEach(function (el) {
      el.hidden = n === 0;
      el.setAttribute(
        "aria-label",
        n === 1 ? "1 saved practice" : n + " saved practices"
      );
    });
    $$("[data-saved-nav-count]").forEach(function (el) {
      el.textContent = String(n);
    });
  }

  function initSave() {
    var buttons = $$("[data-save]");
    if (!buttons.length) return;

    function paint() {
      var ids = savedIds();
      buttons.forEach(function (b) {
        var on = ids.indexOf(String(b.dataset.save)) > -1;
        b.setAttribute("aria-pressed", on ? "true" : "false");
        var label = b.querySelector(".savebtn__label");
        if (label) label.textContent = on ? "Saved" : "Save";
      });
    }

    buttons.forEach(function (b) {
      b.addEventListener("click", function () {
        var id = String(b.dataset.save);
        var ids = savedIds();
        var at = ids.indexOf(id);
        if (at > -1) { ids.splice(at, 1); } else { ids.push(id); }
        if (!writeSaved(ids)) {
          /* Storage refused. Say so once rather than leaving a button that
             looks broken. */
          b.setAttribute("title", "Saving needs site data enabled in your browser.");
          return;
        }
        paint();
        pushEvent(at > -1 ? "listing_unsave" : "listing_save", { listing_id: id });
      });
    });

    paint();
  }

  /* The saved page. Rendered empty by PHP and filled from ORIA_DATA, so a
     shortlist costs no request and duplicates no listing data. */
  function initSavedPage() {
    var root = document.querySelector("[data-saved-list]");
    if (!root) return;
    var empty = document.querySelector("[data-saved-empty]");
    var count = document.querySelector("[data-saved-count]");
    var D = window.ORIA_DATA || window.ORIA_SEARCH_DATA;

    /* The full payload carries an id (the post slug); the slim index shipped
       on non-directory pages carries only a url. Derive the same key from
       either, or this page silently matches nothing. */
    function keyOf(l) {
      if (l && l.id) return String(l.id);
      if (l && l.url) {
        var parts = String(l.url).replace(/[?#].*$/, "").replace(/\/+$/, "").split("/");
        return parts[parts.length - 1] || "";
      }
      return "";
    }

    function render() {
      var ids = savedIds();
      var all = (D && D.listings) || [];
      var byId = {};
      all.forEach(function (l) {
        var k = keyOf(l);
        if (k) byId[k] = l;
      });

      var rows = [];
      var keep = [];
      ids.forEach(function (id) {
        var l = byId[id];
        if (!l) return;               // unpublished since it was saved
        keep.push(id);
        rows.push(l);
      });

      /* Prune only when the payload is actually usable. An empty index means
         the script could not identify anything, not that the visitor's saves
         are stale — and quietly deleting somebody's shortlist because a
         payload arrived in an unexpected shape is not a trade worth making.
         This is not hypothetical: the slim index has no id field, and the
         first version of this wiped both saves on sight. */
      var usable = Object.keys(byId).length > 0;
      if (usable && keep.length !== ids.length) writeSaved(keep);

      if (count) {
        count.textContent = rows.length
          ? rows.length + (rows.length === 1 ? " saved practice" : " saved practices")
          : "";
      }
      if (empty) empty.hidden = rows.length > 0;

      root.innerHTML = rows.map(function (l) {
        var meta = [l.suburb, l.km != null ? l.km + " km from the CBD" : ""].filter(Boolean).join(" · ");
        /* The listing's own photo, else the shipped practice scene. Either
           way alt is empty: the name is the very next line. */
        var img = l.image || l.image_fb || "";
        /* keyOf, not l.id — the slim index has no id, so this button was
           rendering data-unsave="undefined" and Remove did nothing. */
        return '<div class="savedcard">' +
          (img ? '<a class="savedcard__media" href="' + esc(l.url) + '" tabindex="-1" aria-hidden="true"><img class="savedcard__img" src="' + esc(img) + '" alt="" loading="lazy" decoding="async"></a>' : "") +
          '<div class="savedcard__body">' +
          '<a class="savedcard__name" href="' + esc(l.url) + '">' + esc(l.name) + "</a>" +
          '<span class="savedcard__meta">' + esc(meta) + "</span>" +
          '<button class="savedcard__drop" type="button" data-unsave="' + esc(keyOf(l)) + '">Remove</button>' +
          "</div></div>";
      }).join("");
    }

    root.addEventListener("click", function (e) {
      var b = e.target.closest("[data-unsave]");
      if (!b) return;
      var ids = savedIds();
      var at = ids.indexOf(String(b.dataset.unsave));
      if (at > -1) { ids.splice(at, 1); writeSaved(ids); render(); }
    });

    render();
  }

  /* --- Listing sticky bar ---------------------------------------------- */
  /* Shown once the hero's own buttons have scrolled away, so the two never
     compete. Observing those buttons rather than watching scroll: it is the
     actual question, and it costs nothing per frame. */
  function initStickyCta() {
    var bar = document.querySelector("[data-sticky-cta]");
    if (!bar) return;
    var hero = document.querySelector(".profile__cta");

    function show(on) {
      bar.classList.toggle("is-on", on);
      bar.setAttribute("aria-hidden", on ? "false" : "true");
      if (on) { bar.removeAttribute("inert"); } else { bar.setAttribute("inert", ""); }
    }

    /* No hero to watch (a listing with neither a website nor a booking link
       renders no buttons) — then the bar has nothing to add either. */
    if (!hero || !("IntersectionObserver" in window)) return;

    /* Only once the buttons have gone UP past the top of the screen — not
       merely because they are further down the page than the reader has got.
       The gallery puts the hero CTA below the fold on a lot of screens, so
       "not visible" on its own would show the bar the moment the page loads,
       before anybody has scrolled anywhere. */
    new IntersectionObserver(function (entries) {
      var e = entries[0];
      show(!e.isIntersecting && e.boundingClientRect.top < 0);
    }).observe(hero);

    /* The enquiry form is a <details>; jumping to a closed one lands on a
       summary and looks like nothing happened. Open it first. */
    var enq = bar.querySelector("[data-sticky-enquire]");
    if (enq) {
      enq.addEventListener("click", function () {
        var d = document.querySelector("#enquire details");
        if (d) d.open = true;
      });
    }
  }

  /* What's On filters: rows carry precomputed tokens, this only matches. */
  function initWhatsOn() {
    var root = document.querySelector("[data-whatson]");
    if (!root) return;

    var state = { when: "all", suburb: "", type: "", band: "" };
    var empty = root.querySelector("[data-wo-empty]");

    function apply() {
      var shown = 0;
      $$(".wkrow", root).forEach(function (row) {
        var ok =
          (row.dataset.when || "").split(" ").indexOf(state.when) > -1 &&
          (!state.suburb || row.dataset.suburb === state.suburb) &&
          (!state.type || row.dataset.type === state.type) &&
          (!state.band || row.dataset.band === state.band);
        row.hidden = !ok;
        if (ok) shown++;
      });
      // A day heading with nothing left under it disappears too.
      $$(".wogroup", root).forEach(function (g) {
        g.hidden = !g.querySelector(".wkrow:not([hidden])");
      });
      if (empty) empty.hidden = shown > 0;
    }

    $$(".fchip", root).forEach(function (chip) {
      chip.addEventListener("click", function () {
        state[chip.dataset.f] = chip.dataset.v;
        $$('.fchip[data-f="' + chip.dataset.f + '"]', root).forEach(function (c) {
          c.classList.toggle("is-on", c === chip);
        });
        apply();
      });
    });
    $$("select[data-f]", root).forEach(function (sel) {
      sel.addEventListener("change", function () {
        state[sel.dataset.f] = sel.value;
        apply();
      });
    });
  }

  /* Category tiles: eight at a time from a shuffled deck, next window of
     eight every 30s so all categories share the front door. Paused on
     hover/focus and in background tabs; reduced-motion users keep the
     initial random eight. */
  function initCatsRotator() {
    var grid = document.querySelector("[data-cats-rotate]");
    if (!grid) return;
    var per = parseInt(grid.dataset.catsRotate, 10) || 8;
    var tiles = $$(".cat", grid);
    if (tiles.length <= per || reduced) return;

    var start = 0, paused = false;
    grid.addEventListener("mouseenter", function () { paused = true; });
    grid.addEventListener("mouseleave", function () { paused = false; });
    grid.addEventListener("focusin", function () { paused = true; });
    grid.addEventListener("focusout", function () { paused = false; });

    window.setInterval(function () {
      if (paused || document.hidden) return;
      start = (start + per) % tiles.length;
      var order = 0;
      tiles.forEach(function (tile, i) {
        var visible = ((i - start + tiles.length) % tiles.length) < per;
        tile.hidden = !visible;
        if (visible) {
          // Rotation may fire before the scroll reveal has; force it.
          tile.classList.add("is-in");
          tile.classList.remove("cat--enter");
          void tile.offsetWidth;
          tile.style.setProperty("--i", String(order++));
          tile.classList.add("cat--enter");
        }
      });
    }, parseInt(grid.dataset.rotate, 10) || 30000);
  }

  /* Events strip marquee: items drift across the page in an endless loop.
     The original children are wrapped into a "set", cloned until the set
     fills the container, then the whole set is duplicated — two identical
     sets each translating -100% loop seamlessly. Reduced-motion users keep
     the plain scrollable strip. */
  function initMarquee() {
    $$("[data-marquee]").forEach(function (track) {
      if (reduced) return;
      var items = Array.prototype.slice.call(track.children);
      if (!items.length) return;

      var set = document.createElement("div");
      set.className = "marq__set";
      items.forEach(function (el) { set.appendChild(el); });
      track.appendChild(set);

      var guard = 0;
      while (set.scrollWidth < track.offsetWidth && guard++ < 6) {
        items.forEach(function (el) { set.appendChild(el.cloneNode(true)); });
      }

      var copy = set.cloneNode(true);
      copy.setAttribute("aria-hidden", "true");
      track.appendChild(copy);

      // ~55px/s regardless of how many events are on.
      track.style.setProperty("--marq-dur", Math.max(18, Math.round(set.scrollWidth / 55)) + "s");
      track.classList.add("is-marquee");

      function pause(on) { return function () { track.classList.toggle("is-paused", on); }; }
      track.addEventListener("mouseenter", pause(true));
      track.addEventListener("mouseleave", pause(false));
      track.addEventListener("focusin", pause(true));
      track.addEventListener("focusout", pause(false));
    });
  }

  /* Shop page: category chips over the product grid. */
  function initShopFilter() {
    var root = document.querySelector("[data-shopfilter]");
    if (!root) return;
    var empty = root.querySelector("[data-shop-empty]");
    $$(".fchip", root).forEach(function (chip) {
      chip.addEventListener("click", function () {
        $$(".fchip", root).forEach(function (c) { c.classList.toggle("is-on", c === chip); });
        var cat = chip.dataset.cat || "";
        var shown = 0;
        $$(".prodcard", root).forEach(function (card) {
          var ok = !cat || card.dataset.oshopCat === cat;
          card.hidden = !ok;
          if (ok) shown++;
        });
        if (empty) empty.hidden = shown > 0;
      });
    });
  }

  /* Thin progress bar along the top while reading an article. */
  function initReadbar() {
    var bar = document.querySelector("[data-readbar]");
    if (!bar) return;
    function update() {
      var doc = document.documentElement;
      var max = doc.scrollHeight - window.innerHeight;
      bar.style.transform = "scaleX(" + (max > 0 ? Math.min(1, doc.scrollTop / max) : 0) + ")";
    }
    window.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update);
    update();
  }

  /* Share: native sheet on touch devices, copy-the-link on desktop.
     Desktop Chrome HAS navigator.share but it's the wrong experience
     there (and fails silently on some setups) — pointer type decides. */
  function initShare() {
    $$("[data-share]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var url = window.location.href.split("#")[0];
        function copied() {
          var was = btn.textContent;
          btn.textContent = btn.dataset.copied || "Copied";
          window.setTimeout(function () { btn.textContent = was; }, 1800);
        }
        // execCommand path works on plain http, where clipboard API doesn't.
        function legacyCopy() {
          var ta = document.createElement("textarea");
          ta.value = url;
          ta.setAttribute("readonly", "");
          ta.style.position = "absolute";
          ta.style.left = "-9999px";
          document.body.appendChild(ta);
          ta.select();
          var ok = false;
          try { ok = document.execCommand("copy"); } catch (e) { ok = false; }
          document.body.removeChild(ta);
          if (ok) { copied(); } else { window.prompt("Copy this link:", url); }
        }
        if (navigator.share && window.matchMedia("(pointer: coarse)").matches) {
          navigator.share({ title: document.title, url: url }).catch(function () {});
          return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(copied, legacyCopy);
        } else {
          legacyCopy();
        }
      });
    });
  }

  /* Build-your-session sliders.

     All JS does here is write the current value in words beside each
     label. The form is a plain GET submit, so with scripting off the
     sliders still work — you read the value after submitting rather than
     while dragging, and every result set is still a shareable URL. */
  function initBuildSliders() {
    var ranges = $$("[data-bld-range]");
    if (!ranges.length) return;

    var WORDS = ["Any", "Very low", "Low", "Moderate", "High", "Very high"];

    ranges.forEach(function (r) {
      var out = $('[data-bld-out="' + r.getAttribute("name") + '"]');
      if (!out) return;
      var paint = function () {
        var v = parseInt(r.value, 10) || 0;
        out.textContent = WORDS[Math.max(0, Math.min(5, v))];
      };
      r.addEventListener("input", paint);
      paint();
    });
  }

  /* After "Show me what fits", the page reloads at the top with the
     answers a screen or so further down.

     Ease down to them rather than jumping. The sliders stay in view on the
     way past, which keeps the cause of the result visible — and a glide
     says the page moved, where a jump just looks like a different page
     loaded. Anyone who asked for reduced motion gets the same destination
     without the travel. */
  function scrollToBuildResults() {
    var target = $("#result");
    if (!target || !$(".bld__hits")) return;
    // Only when the visitor actually asked for something, and never over
    // a fragment they navigated to themselves.
    if (!window.location.search || window.location.hash) return;
    // Back and forward should land where the reader left off, not here.
    var nav = performance.getEntriesByType && performance.getEntriesByType("navigation")[0];
    if (nav && "back_forward" === nav.type) return;

    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    window.setTimeout(function () {
      var top = target.getBoundingClientRect().top + window.pageYOffset - (chromeTop() + 16);
      window.scrollTo({ top: Math.max(0, top), behavior: reduce ? "auto" : "smooth" });
    }, 90);
  }

  /* The compare tray: the toggles on the cards, and the bar that collects
     them.

     Clicks are delegated from the document, so cards drawn later by the
     directory's own renderer need no wiring. The buttons ship hidden and
     are revealed here — without scripting there is nothing for them to do,
     and a dead control is worse than no control. */
  function initCompareTray() {
    var tray, count, go, clear, note, noteTimer;

    function build() {
      tray = document.createElement("div");
      tray.className = "cmptray";
      tray.setAttribute("role", "region");
      tray.setAttribute("aria-label", "Compare selection");
      tray.hidden = true;
      tray.innerHTML =
        '<div class="cmptray__inner">' +
          '<p class="cmptray__count" data-cmp-count aria-live="polite"></p>' +
          '<p class="cmptray__note" data-cmp-note hidden></p>' +
          '<div class="cmptray__acts">' +
            '<button class="cmptray__clear" type="button" data-cmp-clear>Clear</button>' +
            '<a class="btn btn--dark btn--plain cmptray__go" data-cmp-go href="/compare/"></a>' +
          "</div>" +
        "</div>";
      document.body.appendChild(tray);
      count = tray.querySelector("[data-cmp-count]");
      note = tray.querySelector("[data-cmp-note]");
      go = tray.querySelector("[data-cmp-go]");
      clear = tray.querySelector("[data-cmp-clear]");
      clear.addEventListener("click", function () { Compare.clear(); });
    }

    function say(msg) {
      if (!note) return;
      note.textContent = msg;
      note.hidden = false;
      clearTimeout(noteTimer);
      noteTimer = setTimeout(function () { note.hidden = true; }, 4000);
    }

    // Redraw every toggle on the page from the store — the single place
    // that decides what a button looks like, so server-rendered and
    // JS-rendered cards can never drift apart.
    function paint() {
      var arr = Compare.all();
      $$("[data-compare-toggle]").forEach(function (b) {
        b.hidden = false;
        var on = arr.indexOf(b.getAttribute("data-slug")) > -1;
        b.setAttribute("aria-pressed", on ? "true" : "false");
        var word = b.querySelector("[data-compare-word]");
        if (word) word.textContent = on ? "Selected" : "Compare";
      });

      if (!tray) return;
      var n = arr.length;
      tray.hidden = n === 0;
      if (n === 0) return;
      count.textContent = n === 1 ? "1 place selected" : n + " places selected";
      var ready = n >= Compare.MIN;
      go.textContent = ready ? "Compare " + n : "Pick one more";
      go.href = ready ? Compare.url() : "#";
      go.setAttribute("aria-disabled", ready ? "false" : "true");
      go.classList.toggle("is-off", !ready);
    }

    build();
    Compare.onChange(paint);

    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-compare-toggle]");
      if (btn) {
        e.preventDefault();
        var r = Compare.toggle(btn.getAttribute("data-slug"));
        if (r.full) say("Four is the most you can compare at once.");
        return;
      }
      var g = e.target.closest("[data-cmp-go]");
      if (g && g.getAttribute("aria-disabled") === "true") {
        e.preventDefault();
        say("Choose at least two to compare.");
      }
    });

    // The directory redraws its results wholesale; repaint after it settles
    // so restored cards show their state.
    var results = $("#dirResults");
    if (results && window.MutationObserver) {
      var pending;
      new MutationObserver(function () {
        clearTimeout(pending);
        pending = setTimeout(paint, 30);
      }).observe(results, { childList: true, subtree: true });
    }

    paint();
  }

  /* The compare picker.

     The markup carried data-max="4" and nothing ever read it, so a visitor
     could tick six boxes and silently get four — the server keeps the first
     four and drops the rest. This makes the ceiling visible instead.

     It also gives the submit button something to say. Two picks is the
     minimum for a comparison, so below that the button is inert and says
     why; at two it wakes up, once, and then carries the count. The pulse
     fires only on the transition, never on a loop: it is there to tell you
     the thing is now available, not to keep asking for attention. */
  function initComparePicker() {
    var grid = document.querySelector("[data-compare-picker]");
    if (!grid) return;
    var form = grid.closest("form");
    if (!form) return;
    var go = form.querySelector("[data-compare-go]");
    if (!go) return;

    var label = go.querySelector("[data-compare-label]");
    var hint = form.querySelector("[data-compare-hint]");
    var boxes = $$("input[type=checkbox]", grid);
    var MIN = 2;
    var max = parseInt(grid.getAttribute("data-max"), 10) || 4;
    var base = label ? label.textContent : "";
    var wasReady = null;

    function sync() {
      var n = boxes.filter(function (b) { return b.checked; }).length;

      boxes.forEach(function (b) {
        var spent = !b.checked && n >= max;
        b.disabled = spent;
        var pick = b.closest(".cmp__pick");
        if (pick) pick.classList.toggle("is-spent", spent);
      });

      var ready = n >= MIN;
      go.disabled = !ready;
      go.classList.toggle("is-ready", ready);
      if (label) label.textContent = ready ? base + " " + n : base;
      if (hint) hint.hidden = ready;

      // Re-trigger the one-shot by tearing the class off and forcing a
      // reflow; without the reflow the browser coalesces both changes and
      // the animation never restarts.
      if (ready && wasReady === false) {
        go.classList.remove("is-woken");
        void go.offsetWidth;
        go.classList.add("is-woken");
      }
      wasReady = ready;
    }

    boxes.forEach(function (b) { b.addEventListener("change", sync); });
    sync();
  }

  /* Nav dropdowns.

     CSS already opens the panel on :hover and :focus-within, so this is
     enhancement, not access — with scripting off the menu still works.
     What JS adds: the caret that says a panel exists, aria-expanded so a
     screen reader is told the same thing, a tap target for touch devices
     that have no hover, and Escape to get out.

     The caret is a sibling of the parent link, never inside it: a button
     within an anchor is invalid, and the link must keep working on its
     own. */
  function initNavDropdowns() {
    var parents = $$(".nav__links .menu-item-has-children");
    if (!parents.length) return;

    var CARET =
      '<svg viewBox="0 0 10 10" fill="none" stroke="currentColor" ' +
      'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ' +
      'aria-hidden="true"><path d="M2 3.5 5 6.5 8 3.5"/></svg>';

    /* Three states, not two.

       "false" is an explicit dismissal — Escape or a click outside — and
       the CSS lets it beat :hover and :focus-within. Without it, Escape
       handing focus back to the parent link re-opens the panel through
       :focus-within, and the key appears to do nothing.

       Removing the attribute is the neutral state, where hover and focus
       govern again. A pointer leaving must land here rather than on
       "false", or a later keyboard user would find the panel wedged shut. */
    function setState(li, state) {
      if (state === null) {
        li.removeAttribute("data-open");
      } else {
        li.setAttribute("data-open", state);
      }
      var b = li.querySelector(".nav__caret");
      if (b) b.setAttribute("aria-expanded", state === "true" ? "true" : "false");
    }

    function closeAll(except) {
      parents.forEach(function (li) {
        if (li !== except) setState(li, "false");
      });
    }

    parents.forEach(function (li, i) {
      var link = li.querySelector("a");
      var sub = li.querySelector(".sub-menu");
      if (!link || !sub) return;

      if (!sub.id) sub.id = "navsub" + (i + 1);

      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "nav__caret";
      btn.innerHTML = CARET;
      btn.setAttribute("aria-expanded", "false");
      btn.setAttribute("aria-controls", sub.id);
      // Named for what it opens, so the label is never a bare "expand".
      btn.setAttribute(
        "aria-label",
        "Show more under " + (link.textContent || "").trim()
      );
      link.insertAdjacentElement("afterend", btn);

      btn.addEventListener("click", function (e) {
        e.preventDefault();
        var wasOpen = li.getAttribute("data-open") === "true";
        closeAll(li);
        setState(li, wasOpen ? "false" : "true");
      });

      li.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
          setState(li, "false");
          link.focus();
          return;
        }
        /* Tab means focus is on the move. Whichever way it lands, neutral
           is right: still inside and :focus-within holds the panel open,
           gone and it closes. Clearing here matters because a dismissal
           left standing would out-rank :hover and :focus-within for good,
           wedging the menu shut until the page reloaded. */
        if (e.key === "Tab") setState(li, null);
      });

      /* Three ways out of a dismissal, because leaving one in place breaks
         the menu permanently. focusout is the precise one; mouseenter and
         Tab are the belt and braces, and unlike focusout they fire in every
         environment this has been tested in. */
      li.addEventListener("focusout", function (e) {
        if (!e.relatedTarget || !li.contains(e.relatedTarget)) setState(li, null);
      });

      li.addEventListener("mouseenter", function () {
        if (li.getAttribute("data-open") === "false") setState(li, null);
      });

      // The CSS :hover has already closed the panel; the flag returns to
      // neutral rather than "false", for the same reason as above.
      li.addEventListener("mouseleave", function () {
        if (!li.contains(document.activeElement)) setState(li, null);
      });
    });

    document.addEventListener("click", function (e) {
      if (!e.target.closest(".nav__links .menu-item-has-children")) closeAll();
    });
  }


  /* ---------------------------------------------------------- distance
   * "How far is this from me?", answered without anybody being tracked.
   *
   * The coordinates the page already carries are the listings'. The
   * visitor's own position is asked for by the browser, granted per visit,
   * and used only here — it is never sent anywhere, because there is no
   * endpoint that takes it. Nothing is stored, so the prompt returns on the
   * next visit, which is the honest trade for not keeping it.
   */
  function haversineKm(a, b) {
    var R = 6371, r = Math.PI / 180;
    var dLat = (b[0] - a[0]) * r, dLng = (b[1] - a[1]) * r;
    var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(a[0] * r) * Math.cos(b[0] * r) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
  }

  function initDistance() {
    var nodes = document.querySelectorAll("[data-oria-distance]");
    if (!nodes.length || !navigator.geolocation) return;

    nodes.forEach(function (node) {
      if (node.querySelector("[data-oria-distance-btn]")) return;
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "linkish";
      btn.setAttribute("data-oria-distance-btn", "");
      btn.textContent = "Distance from me";
      btn.style.cssText = "display:block;margin-top:.3rem;background:none;border:0;padding:0;font:inherit;font-size:.82rem;color:var(--moss);cursor:pointer;text-decoration:underline";
      node.appendChild(btn);

      btn.addEventListener("click", function () {
        var lat = parseFloat(node.getAttribute("data-lat"));
        var lng = parseFloat(node.getAttribute("data-lng"));
        if (isNaN(lat) || isNaN(lng)) return;

        btn.disabled = true;
        btn.textContent = "Asking your browser…";

        navigator.geolocation.getCurrentPosition(
          function (pos) {
            var km = haversineKm(
              [pos.coords.latitude, pos.coords.longitude],
              [lat, lng]
            );
            var out = node.querySelector("[data-oria-distance-value]");
            var txt = km < 1
              ? "About " + Math.round(km * 1000 / 100) * 100 + " m from you"
              : "About " + (km < 10 ? km.toFixed(1) : Math.round(km)) + " km from you";
            if (out) out.textContent = txt;
            btn.remove();
          },
          function () {
            // Declined, or the device could not say. Neither is an error
            // worth shouting about: the CBD figure is still on screen.
            btn.disabled = false;
            btn.textContent = "Location unavailable";
            setTimeout(function () { btn.textContent = "Distance from me"; }, 2500);
          },
          { timeout: 8000, maximumAge: 300000 }
        );
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initTracking();
    initCopy();
    initFinder();
    initMatchDialog();
    initMatchCombos();
    initNav();
    initNavDropdowns();
    initComparePicker();
    initBuildSliders();
    scrollToBuildResults();
    initCompareTray();
    initAccordions();
    initPullquote();
    initReveal();
    initMap();
    initNiceSelects();
    initSiteSearch();
    initStickyCta();
    initSave();
    initClasses();
    initGuideToc();
    initSavedPage();
    paintSavedNav();
    /* Another tab is the same shortlist. Without this the count goes stale
       the moment somebody browses in two windows, which on a directory is
       ordinary behaviour rather than an edge case. */
    window.addEventListener("storage", function (e) {
      if (!e.key || e.key === SAVE_KEY) paintSavedNav();
    });
    initHomeSearch();
    initDirectory();
    initGoodFor();
    initCatMap();
    initCardQuickActions();
    scrollToFilteredResults();
    initPopoverDone();
    initFilterSheet();
    initStickyToolbar();
    initStarRate();
    initCountUp();
    initIntentGridMore();
    initForms();
    initCarousels();
    initFeaturedRotator();
    initCatsRotator();
    initMarquee();
    initWhatsOn();
    initShopFilter();
    initReadbar();
    initShare();
    initYear();
    initDistance();
  });
})();
