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
     categories, individual practices by name, wellness apps, and suburbs.
     Everyday wording that isn't in any of those names ("ice bath",
     "reformer") is mapped onto specialties by ORIA_SEARCH.synonyms.

     Apps sit below the places on purpose. Somebody typing "meditation"
     into a Perth directory almost always wants the classes; somebody
     typing "headspace" gets the app anyway, because nothing else
     matches, and a name they have typed in full jumps above the
     practices that merely contain it. */
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
    /* Wellness apps, from their own small payload — ORIA_APPS is on every
       page the search box is, and absent while no app is published. */
    (window.ORIA_APPS || []).forEach(function (a) {
      var at = (a.name || "").toLowerCase();
      if (at.indexOf(q) > -1) {
        out.push({ kind: "App", label: a.name, sub: a.sub, url: a.url,
                   rank: at.indexOf(q) === 0 ? 2.5 : 3.5 });
      }
    });
    /* A place someone types is usually somewhere they want to go, not a
       filter they want to set. Where we have a guide for it, that is the
       destination; the directory search stays for the suburbs that have
       no page of their own. Ranked below an exact practice match on
       purpose -- typing a studio's name should never be answered with a
       neighbourhood. */
    var guided = {};
    (D.areas || []).forEach(function (a) {
      var name = (a.name || "").toLowerCase();
      if (name.indexOf(q) !== 0 && q.indexOf(name) !== 0) return;
      guided[name] = true;
      var bits = a.places + (a.places === 1 ? " place" : " places");
      if (a.events > 0) bits += " · " + a.events + (a.events === 1 ? " upcoming event" : " upcoming events");
      out.push({ kind: "Neighbourhood guide", label: a.name, sub: bits, url: a.url,
                 rank: name === q ? 1.5 : 3.2 });
    });
    (D.regions || []).forEach(function (r) {
      (r.suburbs || []).forEach(function (name) {
        if (guided[name.toLowerCase()]) return;
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

  /* The checkboxes a want should drive on this page.

     A want's vocabulary can be filed under either taxonomy, and the engine
     ANDs `spec` against `svc` — so ticking both kinds intersects two sets
     instead of widening one, and a want that reads "19 places" delivers 5.
     Take whichever kind reaches more of the want here, and use only that. */
  function gfBoxesFor(slugs) {
    var bySpec = [], bySvc = [];
    (slugs || []).forEach(function (slug) {
      var a = document.querySelector('[data-filter="spec"][value="' + slug + '"]');
      var b = document.querySelector('[data-filter="svc"][value="' + slug + '"]');
      if (a) { bySpec.push(a); } else if (b) { bySvc.push(b); }
    });
    return bySpec.length >= bySvc.length ? bySpec : bySvc;
  }

  /* --- List | Map, on category pages ------------------------------------ */
  /* The map used to fill half the opening screen before anybody had asked
     for it. Now it sits behind a switch above the results, and starts the
     first time it is opened. Filters are shared state, so switching views
     never resets them; the hero's "Open map" and each card's pin button
     come here too. No Leaflet, no switch -- the list is the whole page. */
  function initDirView() {
    var panel = $("#catMapView");
    var browse = $("#browse");
    var buttons = $$("[data-view]");
    if (!panel || !browse || !buttons.length) return;
    /* The map library arrives on demand (window.ORIA_LEAFLET, from
       functions.php): most visitors never open the map, and 162KB of it has
       no business on their page. No library and no way to fetch one means
       no switch -- the list is the whole page. */
    var LF = window.ORIA_LEAFLET || null;
    if (!window.L && !LF) {
      buttons.forEach(function (b) { b.closest(".viewswitch") && (b.closest(".viewswitch").hidden = true); });
      $$("[data-open-map]").forEach(function (b) { b.hidden = true; });
      return;
    }
    document.body.classList.add("has-catmap"); // shows the cards' pin buttons

    var loading = null;
    function withLeaflet(cb) {
      if (window.L) { cb(); return; }
      if (!loading) {
        loading = new Promise(function (resolve, reject) {
          var css = document.createElement("link");
          css.rel = "stylesheet";
          css.href = LF.css;
          document.head.appendChild(css);
          var js = document.createElement("script");
          js.src = LF.js;
          js.onload = resolve;
          js.onerror = reject;
          document.head.appendChild(js);
        });
        panel.setAttribute("aria-busy", "true");
      }
      loading.then(function () {
        panel.removeAttribute("aria-busy");
        cb();
      }, function () {
        /* It failed to arrive: say so where the map would be, and keep the
           list fully usable -- the brief's "map unavailable" state. */
        panel.removeAttribute("aria-busy");
        var host = $("[data-catmap]", panel);
        if (host) host.innerHTML = '<p class="catmap__fail">The map could not load just now. Every listing is in the list.</p>';
      });
    }

    var started = false;
    var realFocus = null;
    /* On a phone the map is the whole screen, not a box on the page -- the
       brief's "do not squeeze a desktop map into the page". Opening it
       remembers where the list was; closing it goes back there. */
    var phone = window.matchMedia("(max-width: 50rem)");
    var listY = 0;
    var fab = $(".mapfab");
    function writeView(isMap) {
      try {
        var u = new URL(window.location.href);
        if (isMap) u.searchParams.set("view", "map"); else u.searchParams.delete("view");
        history.replaceState(null, "", u.pathname + (u.search || "") + u.hash);
      } catch (e) { /* old browsers: the view just is not in the address */ }
    }
    /* Hiding or showing the whole list above the rest of the page makes the
       browser's scroll anchoring "help": it shifts the scroll by the list's
       height to keep whatever it anchored to in place, which threw a phone
       from the listings to the foot of the page on closing the map. So
       anchoring is off for the moment of the swap, and the position is put
       back explicitly -- twice, a frame apart, because the list's height is
       only known once it has laid out again. */
    function pinScroll(y) {
      var root = document.documentElement;
      // "instant": the site scrolls smoothly by default, and a restore that
      // glides from wherever the browser put it is its own small jolt.
      var jump = function () {
        try { window.scrollTo({ top: y, left: 0, behavior: "instant" }); } catch (e) { window.scrollTo(0, y); }
      };
      root.style.overflowAnchor = "none";
      jump();
      requestAnimationFrame(function () {
        jump();
        requestAnimationFrame(jump);
      });
      // A timer as well as the frames: frames pause in a background tab, and
      // anchoring must never be left switched off.
      window.setTimeout(function () { jump(); root.style.overflowAnchor = ""; }, 120);
    }
    /* On a phone the full-screen map is a dialog in all but name, so it
       behaves as one: lifted to the body (the way the filter sheets are),
       labelled modal, everything else made inert so Tab cannot wander
       into the page behind it, and focus handed back to whatever opened it
       when it closes. */
    var home = document.createComment("catmap-home");
    var opener = null;
    function modal(on) {
      if (on) {
        if (panel.parentNode !== document.body) {
          panel.parentNode.insertBefore(home, panel);
          document.body.appendChild(panel);
        }
        panel.setAttribute("role", "dialog");
        panel.setAttribute("aria-modal", "true");
        panel.setAttribute("aria-label", "Map");
        Array.prototype.forEach.call(document.body.children, function (el) {
          if (el !== panel && el.tagName !== "SCRIPT") el.setAttribute("inert", "");
        });
      } else {
        Array.prototype.forEach.call(document.body.children, function (el) { el.removeAttribute("inert"); });
        panel.removeAttribute("role");
        panel.removeAttribute("aria-modal");
        panel.removeAttribute("aria-label");
        if (home.parentNode) {
          home.parentNode.insertBefore(panel, home);
          home.parentNode.removeChild(home);
        }
      }
    }
    function show(view) {
      var isMap = view === "map";
      var wasMap = browse.classList.contains("is-map");
      if (isMap && !wasMap) opener = document.activeElement;
      modal(isMap && phone.matches);
      if (isMap && !wasMap) {
        listY = window.pageYOffset;
        document.documentElement.style.overflowAnchor = "none";
      }
      panel.hidden = !isMap;
      browse.classList.toggle("is-map", isMap);
      document.body.classList.toggle("is-mapfull", isMap && phone.matches);
      buttons.forEach(function (b) { b.setAttribute("aria-pressed", b.getAttribute("data-view") === view ? "true" : "false"); });
      writeView(isMap);
      if (fab) fab.classList.toggle("is-away", isMap);
      if (!isMap) {
        if (wasMap) pinScroll(listY);
        else document.documentElement.style.overflowAnchor = "";
        if (wasMap && opener && document.body.contains(opener) && opener.focus) {
          try { opener.focus({ preventScroll: true }); } catch (e) { opener.focus(); }
        }
        return;
      }
      var close = $(".dirmap__close");
      if (close && phone.matches) close.focus({ preventScroll: true });
      if (!started) {
        started = true;
        withLeaflet(function () {
          initCatMap();
          realFocus = DirAPI.focusOnMap && DirAPI.focusOnMap !== focus ? DirAPI.focusOnMap : null;
          DirAPI.focusOnMap = focus;
          // A card's pin pressed before the library had arrived.
          if (pendingFocus && realFocus) { realFocus(pendingFocus); }
          pendingFocus = null;
        });
      } else if (DirAPI.mapRefresh) {
        DirAPI.mapRefresh(focusing);
      }
      (DirAPI.catEvent || pushEvent)("category_map_open", { results_count: (DirAPI.lastUrls || []).length });
    }
    var pendingFocus = null, focusing = false;
    function focus(url) {
      focusing = true;
      show("map");
      focusing = false;
      if (realFocus) return realFocus(url);
      pendingFocus = url; // the library is still on its way
      return true;
    }
    DirAPI.focusOnMap = focus;

    buttons.forEach(function (b) {
      b.addEventListener("click", function () { show(b.getAttribute("data-view")); });
    });
    // Escape closes the full-screen map on a phone, like any dialog.
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && document.body.classList.contains("is-mapfull")) show("list");
    });
    /* The floating Map button only shows while the listings are on screen:
       above them it would float over the hero, below them over the guide. */
    if (fab && "IntersectionObserver" in window) {
      new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { fab.classList.toggle("is-shown", en.isIntersecting); });
      }, { rootMargin: "-20% 0px -30% 0px" }).observe($("#dirResults") || browse);
    }
    // A shared link to the map opens on the map.
    if (new URLSearchParams(window.location.search).get("view") === "map") show("map");
    $$("[data-open-map]").forEach(function (b) {
      b.addEventListener("click", function () {
        show("map");
        var top = browse.getBoundingClientRect().top + window.pageYOffset - chromeTop() - 12;
        window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
      });
    });
    /* "Find near me" in the hero is the toolbar's location button, pressed
       from further up the page -- it only ever asks for location because
       somebody chose to press it. */
    $$("[data-hero-near]").forEach(function (b) {
      var near = $("[data-near]");
      if (!near) { b.hidden = true; return; }
      b.addEventListener("click", function () {
        near.click();
        var top = browse.getBoundingClientRect().top + window.pageYOffset - chromeTop() - 12;
        window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
      });
    });
  }

  /* --- Category map ---------------------------------------------------- */
  /* A real, interactive map of every listing on a category page — Leaflet
     over CARTO's light basemap, both self-hosted/keyless. Hover names the
     place; click opens a card with the link. Leaflet is only enqueued on
     the pages that render a map, so window.L is the feature test. */
  function initCatMap() {
    var host = $("[data-catmap]");
    var dataEl = $("[data-catmap-data]");
    if (!host || !dataEl || !window.L) return;
    /* Behind the List | Map switch the map starts only when it is opened --
       Leaflet measures its box on creation, and a hidden box measures zero.
       initDirView() calls this again once the panel is showing. */
    if (host._oriaMap || host.closest("[hidden]")) return;
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

    /* The guide for a suburb, by its name. Built from the same payload
       search reads, so a pin can only offer a neighbourhood that has a
       page -- there is nothing to guess and nothing to 404. */
    var areaByName = (function () {
      var D = window.ORIA_DATA || window.ORIA_SEARCH_DATA || {};
      var by = {};
      (D.areas || []).forEach(function (a) { by[(a.name || "").toLowerCase()] = a; });
      return by;
    })();

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
      /* Rating and open-now, when the Places cache holds them. p.o is
         true/false/null -- null means unknown, and the popup then says
         nothing rather than guessing. */
      var meta = "";
      if (p.r > 0) meta += '<span class="catmap__pop-star">★ ' + Number(p.r).toFixed(1) + "</span>";
      if (p.o === true) meta += '<span class="catmap__pop-open">Open now</span>';
      else if (p.o === false) meta += '<span class="catmap__pop-shut">Closed now</span>';
      mk.bindPopup(
        '<div class="catmap__pop">' +
        (p.i ? '<img class="catmap__pop-img" src="' + esc(p.i) + '" alt="" loading="lazy">' : "") +
        "<b>" + esc(p.n) + "</b>" +
        (p.s ? "<span>" + esc(p.s) + "</span>" : "") +
        (meta ? '<span class="catmap__pop-meta">' + meta + "</span>" : "") +
        '<a href="' + esc(p.u) + '" data-catmap-view>View profile &rarr;</a>' +
        /* Secondary, and only where a guide exists: the pin was opened
           for the practice, and a popup on a phone has room for one
           decision. */
        (function () {
          var a = areaByName[(p.s || "").toLowerCase()];
          if (!a) return "";
          return '<a class="catmap__pop-area" href="' + esc(a.url) + '" data-area-promo="map-popup" data-area-slug="' +
            esc(a.id) + '">Explore ' + esc(a.name) + " &rarr;</a>";
        })() +
        "</div>",
        { minWidth: 200 }
      );
      mk._oriaSub = (p.s || "").toLowerCase();
      mk.on("mouseover", function () { mk.setStyle({ fillColor: "#C9A24B" }); });
      mk.on("mouseout", function () { mk.setStyle({ fillColor: mk._oriaHeld ? "#C9A24B" : "#0E3B38" }); });
      // Which practice a pin was opened for -- by its address, never its position.
      mk.on("click", function () {
        if (!host.closest("#catMapView") || !DirAPI.catEvent) return;
        DirAPI.catEvent("category_map_pin_select", { listing_id: String(p.u || "").split("/").filter(Boolean).pop() || "" });
      });
      mk._oriaPlace = p;
      mk.on("popupopen", function () { solo = mk; });
      mk.on("popupclose", function () {
        if (solo !== mk) return;
        solo = null;
        window.setTimeout(recluster, 0); // not from inside Leaflet's own close
      });
      group.push(mk);
    });

    /* Clusters. Pins closer than CELL pixels on screen fold into one
       numbered circle, worked out again after every zoom -- a plain grid
       over the projected points, written here rather than pulled in as a
       plugin: a few dozen lines against a dependency to keep patched.
       The whole-of-Perth map carries some 400 pins, and many listings are
       placed at their suburb's centre (geo "suburb"), so without this they
       sat exactly on top of each other with only the top one clickable.

       Pressing a cluster zooms to its pins. Where zooming cannot pull them
       apart -- the same suburb centre, or already close to street level --
       it lists them instead, each a link to its profile. A pin whose card
       is open is never folded away (solo), so a card's "show on map" and
       a popup the visitor is reading both survive a zoom out. */
    var CELL = 44;
    var shown = group.slice();   // the pins the list's filters leave
    var solo = null;
    var clusters = L.layerGroup().addTo(map);

    function clusterPopup(mks) {
      var items = mks.slice().sort(function (a, b) {
        return a._oriaPlace.n.localeCompare(b._oriaPlace.n);
      }).map(function (mk) {
        var p = mk._oriaPlace;
        return '<li><a href="' + esc(p.u) + '">' + esc(p.n) + "</a>" +
          (p.r > 0 ? ' <span class="catmap__pop-star">★ ' + Number(p.r).toFixed(1) + "</span>" : "") + "</li>";
      }).join("");
      var sub = mks[0]._oriaPlace.s || "";
      return '<div class="catmap__pop catmap__pop--list"><b>' + mks.length + " practices" +
        (sub ? " in " + esc(sub) : " here") + "</b><ul>" + items + "</ul></div>";
    }

    function recluster() {
      if (!map._loaded) return;
      clusters.clearLayers();
      var zoom = map.getZoom();
      var cells = {}, order = [];
      var want = {};
      shown.forEach(function (mk) {
        want[L.stamp(mk)] = 1;
        var key;
        if (mk === solo) {
          key = "solo";
        } else {
          var pt = map.project(mk.getLatLng(), zoom);
          key = Math.floor(pt.x / CELL) + ":" + Math.floor(pt.y / CELL);
        }
        if (!cells[key]) { cells[key] = []; order.push(key); }
        cells[key].push(mk);
      });
      // Pins the filters took out.
      group.forEach(function (mk) {
        if (!want[L.stamp(mk)] && map.hasLayer(mk)) map.removeLayer(mk);
      });
      order.forEach(function (key) {
        var mks = cells[key];
        if (mks.length === 1) {
          if (!map.hasLayer(mks[0])) mks[0].addTo(map);
          return;
        }
        mks.forEach(function (mk) { if (map.hasLayer(mk)) map.removeLayer(mk); });
        var b = L.latLngBounds(mks.map(function (mk) { return mk.getLatLng(); }));
        var held = mks.some(function (mk) { return mk._oriaHeld; });
        var n = mks.length;
        var size = n < 10 ? 32 : n < 50 ? 38 : 44;
        var c = L.marker(b.getCenter(), {
          icon: L.divIcon({
            className: "catmap__cluster" + (held ? " is-held" : ""),
            html: "<span>" + n + "</span>",
            iconSize: [size, size]
          }),
          title: n + " practices here",
          keyboard: true,
          riseOnHover: true
        });
        c.on("click", function () {
          // Can zooming in separate them? The same spot never separates.
          var same = b.getNorthEast().equals(b.getSouthWest(), 1e-4);
          var z = map.getBoundsZoom(b, false, L.point(80, 80));
          if (same || z <= zoom || zoom >= 16) {
            c.bindPopup(clusterPopup(mks), { minWidth: 220, maxWidth: 280 }).openPopup();
            return;
          }
          map.fitBounds(b, { padding: [40, 40], maxZoom: 17 });
        });
        clusters.addLayer(c);
      });
    }

    var bounds = L.featureGroup(group).getBounds();
    map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });
    host._oriaMap = map;
    map.on("moveend", recluster); // after zoomend: the grid follows the zoom
    recluster();

    /* The pins follow the list: whatever the filters leave in the list is
       what the map shows, refitted to those pins. Pages that have no list
       engine never send the event, so their maps keep every pin. */
    function applyResults(urls, keepView) {
      if (!urls) return;
      var want = {};
      urls.forEach(function (u) { want[u] = 1; });
      var vis = group.filter(function (mk, i) { return !!want[places[i].u]; });
      shown = vis.length ? vis : group.slice();
      if (solo && shown.indexOf(solo) < 0) { solo.closePopup(); solo = null; }
      bounds = L.featureGroup(shown).getBounds();
      if (!keepView) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });
      recluster(); // fitBounds may not move at all, and then sends no moveend
    }
    document.addEventListener("oria:dir-results", function (e) { applyResults(e.detail && e.detail.urls); });
    if (DirAPI.lastUrls) applyResults(DirAPI.lastUrls);
    /* keepView: a card's pin is about to zoom somewhere. Refitting to every
       pin first starts an animated zoom that lands after the pin's own, and
       the map ends up back at the city view with the popup open off-screen. */
    DirAPI.mapRefresh = function (keepView) {
      map.invalidateSize();
      applyResults(DirAPI.lastUrls, keepView);
    };

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
      solo = mk;
      map.setView(mk.getLatLng(), Math.max(map.getZoom(), 14));
      recluster();
      mk.openPopup();
      return true;
    };

    /* "View profile" in a popup goes to the card in the list below —
       scrolled to and spotlit gold — rather than straight off the page:
       the card holds the rating, price and blurb the decision needs. The
       href stays the real profile URL, so new-tab and no-JS still land
       there, and so does anyone the filters have hidden the card from.

       Desktop only. Below the breakpoint where the map stops sitting beside
       its list, the card is somewhere further down a stacked page, so the
       redirect reads as a link that scrolled instead of opening — and a
       button saying "View profile" has to open the profile. Checked at click
       time, not at load, so rotating a phone gets the right behaviour. */
    var sideBySide = window.matchMedia("(min-width: 60.0625rem)");
    host.addEventListener("click", function (e) {
      var link = e.target.closest && e.target.closest("[data-catmap-view]");
      if (!link || !DirAPI.revealCard) return;
      if (!sideBySide.matches) return; // let the link do what it says
      // Behind the List | Map switch the list is hidden while the map
      // shows, so the popup's link does what it says: opens the profile.
      if (host.closest("#catMapView")) return;
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
      recluster();
    }
    function resetMap() {
      pills.forEach(function (o) { o.classList.remove("is-here"); });
      releasePins();
      openLink.hidden = true;
      // `bounds` tracks the pins the filters leave, so this goes back to
      // the list's view rather than to every pin on the page.
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
        recluster();
        openLink.href = pill.getAttribute("href");
        openLink.textContent = "Open the " + (pill.getAttribute("data-suburb") || "area") + " page →";
        openLink.hidden = false;
      });
    });
    /* The Area filter drives the map too. Named suburbs are framed and
       held gold exactly as a pill does; an empty list means no area is
       chosen, which is the same "show me everything" the declick means.

       Kept separate from the pills rather than folded into them: the pills
       are a zoom toggle that never touches the results, while this follows
       a filter that has already changed them. */
    DirAPI.zoomToAreas = function (names) {
      if (!names || !names.length) { resetMap(); return; }

      var mks = group.filter(function (mk) {
        return names.indexOf(mk._oriaSub) > -1;
      });
      /* Every pin in the chosen area lacks coordinates, or sits in a suburb
         the map has nothing for. Leave the view alone rather than fitting
         to an empty set, which throws in Leaflet. */
      if (!mks.length) { return; }

      pills.forEach(function (o) { o.classList.remove("is-here"); });
      openLink.hidden = true;
      releasePins();
      mks.forEach(function (mk) { mk._oriaHeld = true; mk.setStyle({ fillColor: "#C9A24B" }); });
      map.fitBounds(L.featureGroup(mks).getBounds(), { padding: [46, 46], maxZoom: 15 });
      recluster();
    };

    /* The engine syncs the area once, on init -- and that happens before
       this function runs, so a page opened at ?region=freo would draw the
       whole of Perth under a list of eight. Ask for the sync again now the
       map can answer. */
    if (DirAPI.syncMapToArea) DirAPI.syncMapToArea();

    /* Zooming back out by hand reads as "never mind" — same as a declick. */
    map.on("zoomend", function () {
      if (map.getZoom() <= map.getBoundsZoom(bounds, false)) {
        pills.forEach(function (o) { o.classList.remove("is-here"); });
        releasePins();
        openLink.hidden = true;
      }
    });
  }

  /* The products and apps sections at the foot of a category page. Every
     event names the item and its place in the row -- never the outbound
     URL, which for a product carries the affiliate tag. The category and
     city ride along through the directory's catEvent where it is running. */
  function initSupportBands() {
    var bands = $$(".supband");
    if (!bands.length) return;
    function send(name, params) {
      (DirAPI.catEvent || pushEvent)(name, params);
    }
    bands.forEach(function (band) {
      var section = band.getAttribute("data-sup-section") || "";
      function place(card) {
        return $$(section === "apps" ? ".appcard" : ".prodcard", band).indexOf(card) + 1;
      }
      band.addEventListener("click", function (e) {
        var t = e.target;
        if (!t.closest) return;
        var cta = t.closest("[data-sup-cta]");
        if (cta) {
          send(cta.getAttribute("data-sup-cta") === "all_apps" ? "category_all_apps_click" : "category_shop_all_click",
            { section: section, destination_type: "internal" });
          return;
        }
        var link = t.closest("a[href]");
        if (!link) return;
        var prod = link.closest(".prodcard");
        if (prod && link.hasAttribute("data-oshop-click")) {
          send("category_product_click", {
            item_id: prod.getAttribute("data-oshop-product") || "",
            position: place(prod), section: section, destination_type: "amazon"
          });
          return;
        }
        var app = link.closest(".appcard");
        if (app) {
          send("category_app_click", {
            item_id: app.getAttribute("data-oapp") || "",
            position: place(app), section: section, destination_type: "app_profile"
          });
        }
      });
      // "Why we picked it" is a native <details>: its toggle is the open.
      $$(".prodcard__why", band).forEach(function (d) {
        d.addEventListener("toggle", function () {
          if (!d.open) return;
          var prod = d.closest(".prodcard");
          send("category_product_reason_open", {
            item_id: prod ? prod.getAttribute("data-oshop-product") || "" : "",
            position: prod ? place(prod) : 0, section: section
          });
        });
      });
    });
  }

  /* Oria Reel Recommendation (template-parts/reel-card.php).

     Nothing from Instagram loads with the page. "Watch Reel" builds
     Instagram's own embed from the validated address and loads Instagram's
     script -- once per page however many frames there are, then asks it to
     process the new embed. Ten seconds with no player (removed, private,
     blocked by an extension) and the frame shows its fallback text and the
     link out instead: never a blank box or a broken iframe. The frame's
     height is reserved in CSS, and any growth after this follows the
     visitor's own press, so it is not counted as layout shift. */
  /* The site's own counter (Core\Analytics) for a trend: a view, a Reel
     opened, a next step taken. Each at most once per page view, so the
     report's rates are per visit, not per click. */
  var trendSent = {};
  function trendBeacon(id, type) {
    id = parseInt(id, 10);
    if (!id || trendSent[id + ":" + type] || !window.ORIA_TRACK || !navigator.sendBeacon) return;
    trendSent[id + ":" + type] = 1;
    try {
      navigator.sendBeacon(ORIA_TRACK.url, new Blob([JSON.stringify({ id: id, type: type })], { type: "application/json" }));
    } catch (e) { /* counting must never break the page */ }
  }

  var igLoading = null;
  function loadInstagram() {
    if (window.instgrm && window.instgrm.Embeds) return Promise.resolve();
    if (igLoading) return igLoading;
    igLoading = new Promise(function (resolve, reject) {
      var sc = document.createElement("script");
      sc.src = "https://www.instagram.com/embed.js";
      sc.async = true;
      sc.onload = function () { resolve(); };
      sc.onerror = function () { igLoading = null; reject(); };
      document.body.appendChild(sc);
    });
    return igLoading;
  }

  function initReels() {
    var frames = $$("[data-reel]");
    if (!frames.length) return;
    frames.forEach(function (frame) {
      var btn = $("[data-reel-load]", frame);
      var fallback = $("[data-reel-fallback]", frame);
      var params = { trend_slug: frame.getAttribute("data-reel-trend") || "", reel_location: frame.getAttribute("data-reel-where") || "" };
      function fail() {
        var q = $(".instagram-media", frame);
        if (q) q.parentNode.removeChild(q);
        var ph = $(".reelrec__placeholder", frame);
        if (ph) ph.hidden = true;
        frame.classList.remove("is-loading");
        if (fallback) fallback.hidden = false;
      }
      if (btn) {
        btn.addEventListener("click", function () {
          var url = frame.getAttribute("data-reel-url") || "";
          if (!url) { fail(); return; }
          frame.classList.add("is-loading");
          btn.disabled = true;
          // Instagram's own embed markup, built from the stored address --
          // never HTML an editor pasted.
          var q = document.createElement("blockquote");
          q.className = "instagram-media";
          q.setAttribute("data-instgrm-permalink", url);
          q.setAttribute("data-instgrm-version", "14");
          var a = document.createElement("a");
          a.href = url;
          a.textContent = "View this Reel on Instagram";
          a.target = "_blank";
          a.rel = "noopener nofollow";
          q.appendChild(a);
          frame.appendChild(q);
          pushEvent("reel_load", params);
          trendBeacon(frame.getAttribute("data-reel-id"), "reel");
          loadInstagram().then(function () {
            try { window.instgrm.Embeds.process(); } catch (e) { fail(); return; }
            var waited = 0;
            var t = window.setInterval(function () {
              waited += 500;
              var iframe = $("iframe", frame);
              if (iframe) {
                window.clearInterval(t);
                /* The compact card's button reads just "Watch Reel" and carries
                   the creator in aria-label, so prefer that for the frame title. */
                var reelName = (btn.getAttribute("aria-label") || btn.textContent || "").trim().replace(/^Watch /, "");
                iframe.setAttribute("title", "Instagram Reel" + (reelName ? ": " + reelName : ""));
                var ph = $(".reelrec__placeholder", frame);
                if (ph) ph.hidden = true;
                frame.classList.remove("is-loading");
                frame.classList.add("is-loaded");
              } else if (waited >= 10000) {
                window.clearInterval(t);
                fail();
              }
            }, 500);
          }, fail);
        });
      }
      frame.addEventListener("click", function (e) {
        if (e.target.closest && e.target.closest("[data-reel-out]")) pushEvent("reel_external_open", params);
      });
    });
  }

  /* Trend pages and the /trends/ hub: which next step people take, and the
     hub's goal chips and "What have you seen online?" box, which filter the
     cards in place. The box's text never goes to analytics -- what somebody
     saw online can be about their health. */
  function initTrends() {
    var page = $("[data-trend-page]");
    var slug = page ? page.getAttribute("data-trend-page") : "";
    var tid = page ? page.getAttribute("data-trend-id") : "";
    if (page) {
      pushEvent("trend_view", { trend_slug: slug });
      trendBeacon(tid, "view");
    }
    // What counts as the trend page having done its job: somebody went on
    // to somewhere to try it, or to decide between options.
    var NEXT = { listing: 1, category: 1, cta: 1, compare: 1, event: 1 };

    var NAMES = { listing: "trend_listing_click", compare: "trend_compare_click", product: "trend_product_click", related: "trend_related_click" };
    document.addEventListener("click", function (e) {
      var t = e.target;
      if (!t.closest) return;
      var a = t.closest("[data-trend-cta]");
      var kind, where;
      if (a) {
        kind = a.getAttribute("data-trend-cta");
        where = a.getAttribute("data-trend-where") || "";
      } else {
        var wrap = t.closest("[data-trend-cta-wrap]");
        if (!wrap || !t.closest("a[href]")) return;
        kind = wrap.getAttribute("data-trend-cta-wrap");
        where = "where";
      }
      pushEvent(NAMES[kind] || "trend_cta_click", {
        trend_slug: slug, cta_location: where, destination_type: kind
      });
      if (page && NEXT[kind]) trendBeacon(tid, "next");
    });

    var grid = $("[data-trend-grid]");
    if (!grid) return;
    var cards = $$(".trendcard", grid);
    var chips = $$("[data-trend-goal]");
    var box = $("[data-trend-search]");
    var none = $("[data-trend-none]");
    var goal = "";
    function apply() {
      var q = box ? box.value.trim().toLowerCase() : "";
      var shown = 0;
      cards.forEach(function (c) {
        var ok = (!goal || (c.getAttribute("data-trend-goals") || "").indexOf(" " + goal + " ") > -1) &&
          (!q || (c.getAttribute("data-trend-search") || "").indexOf(q) > -1);
        c.hidden = !ok;
        if (ok) shown++;
      });
      if (none) none.hidden = shown > 0;
    }
    chips.forEach(function (b) {
      b.addEventListener("click", function () {
        var g = b.getAttribute("data-trend-goal");
        goal = goal === g ? "" : g;
        chips.forEach(function (o) { o.setAttribute("aria-pressed", o.getAttribute("data-trend-goal") === goal ? "true" : "false"); });
        apply();
        if (goal) pushEvent("trend_goal_filter", { goal: goal });
      });
    });
    if (box) {
      // The search looks across the featured card too.
      var feat = $(".trendcard--feature");
      if (feat) cards.push(feat);
      box.addEventListener("input", apply);
    }
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
        if (at < 0) meNudge("save");
        pushEvent(at > -1 ? "listing_unsave" : "listing_save", { listing_id: id });
        return;
      }
      var pin = e.target.closest && e.target.closest("[data-card-pin]");
      if (pin && DirAPI.focusOnMap) DirAPI.focusOnMap(pin.dataset.cardPin);
    });
  }

  /* Quick refinements above the results.

     A refine button owns nothing. It ticks the real [data-filter] box the
     dock already renders and lets the engine re-render, count, chip and
     write the URL exactly as if the visitor had opened the panel and
     found it -- which, three taps deep, they did not: the cold plunge
     filter sat in the dock on every sauna page and went unused.

     State is derived, never stored. After any filter change -- including
     "Clear all" and the back button -- a button is lit only while its box
     is still ticked, so the two can never disagree. */
  function initRefine() {
    var btns = $$("[data-refine]");
    if (!btns.length) return;

    function boxFor(btn) {
      var bits = (btn.dataset.refine || "").split(":");
      if (bits.length !== 2 || !bits[0] || !bits[1]) return null;
      return document.querySelector(
        '[data-filter="' + bits[0] + '"][value="' + bits[1].replace(/"/g, '\\"') + '"]'
      );
    }

    function paint() {
      btns.forEach(function (btn) {
        var box = boxFor(btn);
        // No box on this page means nothing to narrow: hide rather than
        // offer a control that cannot work.
        if (!box) { btn.hidden = true; return; }
        btn.setAttribute("aria-pressed", box.checked ? "true" : "false");
      });
    }

    btns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var box = boxFor(btn);
        if (!box) return;
        box.checked = !box.checked;
        box.dispatchEvent(new Event("change", { bubbles: true }));
        paint();
      });
    });

    document.addEventListener("change", function (e) {
      if (e.target && e.target.closest && e.target.closest("[data-filter]")) paint();
    });

    paint();
  }

  function initGoodFor() {

    if (!$("#dirResults")) return;
    var chips = $$("[data-goodfor-chip]");
    var opts = $$("[data-goodfor-opt]");
    if (!chips.length && !opts.length) return;

    function specsOf(el) {
      var list;
      try { list = JSON.parse(el.getAttribute("data-specs") || "[]"); } catch (e) { list = []; }
      return gfBoxesFor(list);
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
    var state = { cats: [], regions: [], suburbs: [], spec: [], svc: [], aud: [], price: [], format: [], rating: 0, q: "", sort: "relevance", page: 1, picks: false };

    /* Category pages (oria-practice-v2.php, data-mode="category") switch on
       four things the other directory pages keep off:

         - "Most relevant" never reads payment: specialists in this
           category first, then the practices with the most to go on;
         - paid placements appear once, in their own labelled band above
           the list, and not again inside it;
         - numbered pages instead of a list that grows, so the guide below
           the results is always one page of listings away, never ten;
         - a List | Map switch, with the map showing what the list shows.

       FAMILY is this category and its own sub-categories: a listing whose
       primary category is in it is a specialist here; one that merely
       offers it alongside something else is not. */
    var CAT = root.dataset.mode === "category";
    var FAMILY = (root.dataset.family || "").split(" ").filter(Boolean);
    var band = CAT ? $("#featBand") : null;
    var bandIds = band ? (band.dataset.ids || "").split(",").filter(Boolean) : [];
    /* "Oria's picks": the listings this category's Best Of guides
       shortlisted (Theme\category_best_of, already cut to this page's own
       set). A filter like any other -- it narrows, it never reorders, so
       "Most relevant" stays free of it. */
    var PICKS = CAT ? (root.dataset.bestPicks || "").split(",").filter(Boolean) : [];
    var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

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
        var boxes = gfBoxesFor(g.specs || []);
        if (!boxes.length) return false;
        var known = boxes.map(function (b) { return b.value; });
        var on = known.filter(function (x) {
          return state.spec.indexOf(x) > -1 || state.svc.indexOf(x) > -1;
        });
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
    if (PICKS.length && params.get("picks") === "1") state.picks = true;
    if (["relevance", "featured", "rating", "price", "name", "near"].indexOf(params.get("sort")) > -1 && params.get("sort") !== "near") {
      state.sort = params.get("sort");
    }

    // Category and suburb landing pages lock one facet: the page IS the
    // filter, so it never appears as a removable chip and never hits the URL.
    var locked = {
      cat: root.dataset.cat || "",
      region: root.dataset.region || "",
      spec: root.dataset.spec || "",
      suburb: root.dataset.suburb || "",
      // A city page is scoped to its city on the server; without the same
      // lock here the rebuild pulled the whole corpus back in.
      city: root.dataset.city || "",
      // An intent page locks one more facet (svc / aud / spec / format /
      // price) the same way. Key and value come from the registry via the
      // template, so the server-rendered set and this view agree exactly.
      intentKey: root.dataset.intentKey || "",
      intentValue: root.dataset.intentValue || ""
    };
    if (locked.cat) state.cats = [locked.cat];
    if (locked.region) state.regions = [locked.region];
    if (locked.spec) state.spec = [locked.spec];
    // A page may lock several values of one key -- "traditional-sauna,
    // infrared-sauna" for Saunas in Perth -- matched as any of them.
    locked.intentValues = locked.intentValue ? locked.intentValue.split(",").filter(Boolean) : [];
    function isLockedIntent(k, v) { return !!locked.intentKey && k === locked.intentKey && locked.intentValues.indexOf(v) > -1; }
    if (locked.intentKey && state[locked.intentKey] !== undefined) {
      if (locked.intentKey === "spec") { locked.spec = locked.intentValues[0] || ""; }
      state[locked.intentKey] = locked.intentValues.slice();
    }

    /* How many filters the VISITOR has added, as opposed to the ones the
       page itself locks (its category, a facet's style, a suburb). The
       featured band only shows on the page as it arrived; the moment
       somebody narrows it, the band steps aside. */
    /* Category-page analytics. Every event carries the page's category and
       city so GA4 can compare categories; none carries a position on a map
       or a visitor's location -- the brief's "no precise coordinates", and
       the site never sends one anywhere. Clicks fire on click (the
       render-vs-click trap in memory: oria-analytics), and a card counts as
       seen once per page view, however often it scrolls past. */
    function catEvent(name, params) {
      if (!CAT) { pushEvent(name, params || {}); return; }
      var p = { page_category: locked.cat || "", page_city: locked.city || "" };
      Object.keys(params || {}).forEach(function (k) { p[k] = params[k]; });
      pushEvent(name, p);
    }
    DirAPI.catEvent = catEvent;

    function cardInfo(article) {
      var a = article && article.querySelector(".listing__name a");
      var slug = a ? (a.getAttribute("href") || "").replace(/\/+$/, "").split("/").pop() : "";
      var inBand = !!(band && band.contains(article));
      var list = Array.prototype.slice.call((inBand ? band : root).querySelectorAll("article.listing"));
      var idx = list.indexOf(article);
      return {
        listing_id: slug,
        position: idx < 0 ? 0 : (inBand ? idx + 1 : (state.page - 1) * PER_PAGE + idx + 1),
        featured: inBand
      };
    }

    if (CAT && PICKS.length) {
      var pickToggle = $("[data-best-toggle]");
      if (pickToggle) {
        pickToggle.addEventListener("click", function () {
          state.picks = !state.picks;
          state.page = 1;
          render();
          catEvent("category_best_of_filter", { on: state.picks, results_count: lastCount });
        });
      }
    }

    if (CAT) {
      // Which filter somebody reaches for, whether or not they use it.
      $$("#dirFilters [data-popover]").forEach(function (d) {
        d.addEventListener("toggle", function () {
          if (!d.open) return;
          var sum = d.querySelector("summary");
          var name = sum ? sum.textContent.replace(/[▾\d]/g, "").replace(/\s+/g, " ").trim() : "";
          catEvent("category_filter_open", { filter_name: name });
        });
      });

      // Opening a practice, saving it, comparing it -- from a card, with its place.
      [root, band].forEach(function (box) {
        if (!box) return;
        box.addEventListener("click", function (e) {
          var t = e.target;
          var article = t.closest && t.closest("article.listing");
          if (!article) return;
          var info = cardInfo(article);
          if (t.closest(".listing__name a, a.btn--dark")) {
            catEvent(info.featured ? "featured_listing_click" : "listing_profile_click", info);
            return;
          }
          // Saving already reports itself (listing_save / listing_unsave in
          // the save handler); only comparing needs telling here.
          var cmp = t.closest("[data-compare-toggle]");
          if (cmp) {
            // After the button's own handler has flipped it: only "on" counts.
            window.setTimeout(function () {
              if (cmp.getAttribute("aria-pressed") === "true") catEvent("listing_compare_add", info);
            }, 0);
          }
        });
      });

      // Seen: half a card on screen, once per listing per page view.
      if ("IntersectionObserver" in window) {
        var seenCards = {};
        var cardObs = new IntersectionObserver(function (entries) {
          entries.forEach(function (en) {
            if (!en.isIntersecting) return;
            var info = cardInfo(en.target);
            cardObs.unobserve(en.target);
            if (!info.listing_id || seenCards[info.listing_id]) return;
            seenCards[info.listing_id] = 1;
            catEvent("listing_card_view", info);
          });
        }, { threshold: 0.5 });
        DirAPI.watchCards = function () {
          [root, band].forEach(function (box) {
            if (box) $$("article.listing", box).forEach(function (a) { cardObs.observe(a); });
          });
        };
      }

      // Which questions people open.
      $$("#faq details").forEach(function (d) {
        d.addEventListener("toggle", function () {
          if (!d.open) return;
          var q = d.querySelector("summary");
          catEvent("category_faq_open", { question: q ? q.textContent.replace(/\s+/g, " ").trim().slice(0, 100) : "" });
        });
      });
    }

    function userFilters() {
      function extra(arr, own) { return arr.filter(function (v) { return own.indexOf(v) === -1; }).length; }
      var n = extra(state.cats, locked.cat ? [locked.cat] : []) +
        extra(state.regions, locked.region ? [locked.region] : []) +
        state.suburbs.length +
        extra(state.spec, locked.spec ? [locked.spec] : []);
      ["svc", "aud", "price", "format"].forEach(function (k) {
        n += extra(state[k], locked.intentKey === k ? locked.intentValues : []);
      });
      return n + (state.rating ? 1 : 0) + (state.q ? 1 : 0) + (state.picks ? 1 : 0);
    }

    function matches(l) {
      if (state.picks && PICKS.indexOf(l.id) === -1) return false;
      if (state.cats.length && state.cats.indexOf(l.cat) === -1 &&
          !(l.also || []).some(function (a) { return state.cats.indexOf(a) > -1; })) return false;
      if (state.regions.length && state.regions.indexOf(l.region) === -1) return false;
      if (locked.city && l.city !== locked.city) return false;
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

    /* ---- find near me --------------------------------------------------
       The listing payload already carries lat, lng and a `geo` precision
       flag. Half these listings sit on a suburb centroid rather than a
       street address, so a distance from one is a fair answer to "roughly
       how far is that" and a lie if shown as the distance to the door --
       hence approx() below, and the "about" the label wears.
       The position never leaves the browser: no request carries it, nothing
       stores it, and reloading the page forgets it. */
    var here = null;

    function haversine(aLat, aLng, bLat, bLng) {
      var R = 6371, rad = Math.PI / 180;
      var dLat = (bLat - aLat) * rad, dLng = (bLng - aLng) * rad;
      var s = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(aLat * rad) * Math.cos(bLat * rad) *
              Math.sin(dLng / 2) * Math.sin(dLng / 2);
      return 2 * R * Math.asin(Math.sqrt(s));
    }

    /* Distance from `here`, or null when we cannot honestly give one. */
    function distanceOf(l) {
      if (!here || typeof l.lat !== "number" || typeof l.lng !== "number") return null;
      return haversine(here.lat, here.lng, l.lat, l.lng);
    }

    /* The distance as shown, rounded. Centroid positions round to the half
       kilometre: quoting one decimal off a suburb centroid reads as though
       we know where the door is, and we know the suburb. Sorting uses this
       same number, so the order on screen can never disagree with the
       figures on screen -- an earlier version sorted on the raw distance and
       put "1.6 km" above "1.5 km". */
    function shownKm(l) {
      var d = distanceOf(l);
      if (d === null) return null;
      if (l.geo === "suburb") return Math.max(0.5, Math.round(d * 2) / 2);
      return d < 10 ? Math.round(d * 10) / 10 : Math.round(d);
    }

    function nearLabel(l) {
      var km = shownKm(l);
      if (km === null) return "";
      var n = km < 10 ? km.toFixed(1) : Math.round(km);
      return (l.geo === "suburb" ? "about " : "") + n + " km away";
    }

    var rank = { featured: 0, claimed: 1, unclaimed: 2 };

    /* Most relevant, on a category page. Never payment. Specialists first,
       then how much there is to go on: the Google rating shrunk towards the
       middle by how few reviews carry it, so one five-star review does not
       outrank forty at 4.8. Then A to Z. */
    function confidence(l) {
      var n = l.reviews || 0;
      return n ? ((l.rating || 0) * n + 4.2 * 8) / (n + 8) : 0;
    }
    /* How much a visitor can decide on without ringing: a published price,
       a real description, more than one service named. Only fields every
       owner can fill in on the free plan (oria-core tiers.php FIELD_TIERS)
       -- next_session, packages and the timetable are paid, so they are
       left out, or the ranking would be selling places after all. Each is
       worth a twelfth of a star, a quarter at most: enough to settle a near
       tie between two ratings, never enough to lift a bare listing over a
       well-reviewed one. "About these results" says so in words. */
    function completeness(l) {
      return ((l.priceFrom > 0 || l.priceBand) ? 1 : 0) +
        ((l.blurb || "").length >= 100 ? 1 : 0) +
        ((l.services || []).length >= 2 ? 1 : 0);
    }
    function standing(l) { return confidence(l) + completeness(l) / 12; }
    function isSpecialist(l) { return FAMILY.indexOf(l.cat) > -1; }

    /* The page of cards, with the two groups named where they meet. On the
       default order specialists come first, so a heading at the boundary
       turns an invisible rule into something a reader can see: the places
       that ARE this, then the ones that also offer it. Only when both groups
       have somebody in them, and only on "Most relevant" -- any other sort
       mixes the two, and a heading would then be lying. */
    var GROUP_LABEL = root.dataset.label || "";
    function groupHead(first, n) {
      var text = first ? "Specialising in " + GROUP_LABEL : "Also offering " + GROUP_LABEL;
      return '<p class="resgroup" role="heading" aria-level="3">' + esc(text) +
        ' <span class="resgroup__n">' + n + "</span></p>";
    }
    function withGroups(shown, list) {
      if (!CAT || !GROUP_LABEL || state.sort !== "relevance" || !FAMILY.length) return shown.map(card).join("");
      var spec = list.filter(isSpecialist).length, other = list.length - spec;
      if (!spec || !other) return shown.map(card).join("");
      var out = "", prev = null;
      shown.forEach(function (l) {
        var g = isSpecialist(l);
        if (g !== prev) { out += groupHead(g, g ? spec : other); prev = g; }
        out += card(l);
      });
      return out;
    }

    function relevance(a, b) {
      var pa = FAMILY.indexOf(a.cat) > -1 ? 0 : 1, pb = FAMILY.indexOf(b.cat) > -1 ? 0 : 1;
      return (pa - pb) || (standing(b) - standing(a)) || a.name.localeCompare(b.name);
    }

    function sortFn(a, b) {
      switch (state.sort) {
        case "near": {
          var da = shownKm(a), db = shownKm(b);
          /* Listings with no coordinates sort last rather than to zero. */
          if (da === null && db === null) return a.name.localeCompare(b.name);
          if (da === null) return 1;
          if (db === null) return -1;
          if (da !== db) return da - db;
          /* Same shown distance: the one whose position we actually know
             goes first. A whole suburb shares one centroid, so without this
             a run of identical approximations leads the page. */
          var pa = a.geo === "address" ? 0 : 1, pb = b.geo === "address" ? 0 : 1;
          return pa - pb || a.name.localeCompare(b.name);
        }
        case "rating": return b.rating - a.rating || b.reviews - a.reviews;
        case "reviews": return b.reviews - a.reviews;
        case "price": return a.priceFrom - b.priceFrom;
        case "name": return a.name.localeCompare(b.name);
        case "featured": return (rank[a.status] - rank[b.status]) || (b.rating - a.rating);
        default:
          if (CAT) return relevance(a, b);
          return (rank[a.status] - rank[b.status]) || (b.rating - a.rating);
      }
    }

    /* At most three tags, and the practical ones first -- the UX audit
       found up to six pills competing with the practice's own name.
       Beginner friendly, online, free and a live offer are things somebody
       chooses on; the wellness-goal tags fill whatever room is left, and
       the category is the fallback for a card with nothing else to say.
       Mirrors template-parts/listing-card.php exactly. */
    function cardTags(l) {
      var out = [];
      if ((l.aud || []).indexOf("beginners") > -1) out.push('<span class="pill">Beginner friendly</span>');
      if (l.format && l.format !== "in-person") out.push('<span class="pill">Online available</span>');
      if (l.priceBand === "Free") out.push('<span class="pill">Free</span>');
      if (l.offer) out.push('<span class="pill pill--offer">Special offer</span>');
      gfTags(l).forEach(function (g) {
        out.push('<span class="pill pill--gf" style="--gf:' + esc(g.color) + '">' + esc(g.label) + "</span>");
      });
      if (!out.length) {
        (l.catTop || []).forEach(function (c) {
          out.push('<span class="pill pill--cat pill--cat-' + esc(c) + '">' + esc(catNames[c] || c) + "</span>");
        });
      }
      return out.length ? '<div class="listing__tags">' + out.slice(0, 3).join("") + "</div>" : "";
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
          /* One editorial Best Of badge, linked to its guide. Same rule and
             markup as BestOf\badge_html(); the server picks which one, and
             sends the year with it. Any change here belongs there too. */
          (l.best && l.best.label
            ? '<div class="listing__best"><a class="badge--best" href="' + esc(l.best.url) +
              '" aria-label="' + esc(bestEyebrow(l.best.year) + ": " + l.best.label) +
              ' — see the Best Of guide it comes from">' +
              '<span class="badge--best__disc" aria-hidden="true"><span class="badge--best__mark">\u2726</span></span>' +
              '<span class="badge--best__text">' +
                '<span class="badge--best__eyebrow">' + esc(bestEyebrow(l.best.year)) + "</span>" +
                '<span class="badge--best__title">' + esc(l.best.label) + "</span>" +
              "</span></a></div>"
            : "") +
          '<div class="listing__head">' +
            "<div>" +
              '<h3 class="listing__name"><a href="' + esc(l.url || '#') + '">' + esc(l.name) + "</a></h3>" +
              '<p class="listing__where">' + ICON.pin + esc(l.suburb) + " · " + esc(regionNames[l.region] || "") +
                (nearLabel(l) ? '<span class="listing__km">' + esc(nearLabel(l)) + "</span>" : "") + "</p>" +
            "</div>" +
            (l.rating > 0
              ? '<span class="rating">' + ICON.star + l.rating.toFixed(1) +
                (l.reviews > 0
                  ? '<span class="rating__count">(' + l.reviews +
                    (l.rating_src === "google" ? " · Google" : "") + ")</span>"
                  : "") + "</span>"
              : "") +
          "</div>" +
          cardTags(l) +
          '<p class="listing__desc">' + esc(l.blurb) + "</p>" +
          '<div class="listing__foot">' +
            '<span class="listing__price">' +
              (l.priceFrom > 0
                ? "$" + l.priceFrom + ' <span>/ session</span>'
                : '<span class="listing__price--none">Price not published</span>') +
              (l.next ? '<span class="listing__next">Next: ' + esc(l.next) + "</span>" : "") +
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
      if (state.picks) kind.unshift(["picks", "1", "Oria\u2019s picks"]);
      var out = kind.concat(area, rest);

      // The quick-filter button says whether it is on.
      var pickBtn = $("[data-best-toggle]");
      if (pickBtn) pickBtn.setAttribute("aria-pressed", state.picks ? "true" : "false");

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
          if (k === "q") { state.q = ""; }
          else if (k === "picks") { state.picks = false; }
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
      if (all) all.addEventListener("click", resetFilters);
    }

    /* Back to the page as it arrived: every filter the visitor added goes,
       the page's own locks (its category, a facet's style, a suburb) stay.
       Shared by the chip row's "Clear all" and the empty state's button --
       the chip row only offers "Clear all" from two filters up, so the empty
       state cannot rely on finding it. */
    function resetFilters() {
      catEvent("category_filter_clear", { results_count: lastCount });
      GFSel = {};
      state.cats = locked.cat ? [locked.cat] : [];
      state.regions = locked.region ? [locked.region] : [];
      state.spec = locked.spec ? [locked.spec] : [];
      state.svc = []; state.aud = []; state.suburbs = [];
      state.price = []; state.format = []; state.rating = 0; state.q = ""; state.picks = false;
      // Clearing never unlocks the page's own facet.
      if (locked.intentKey && state[locked.intentKey] !== undefined) state[locked.intentKey] = locked.intentValues.slice();
      var qb = $("#dirQ");
      if (qb) qb.value = "";
      syncInputs();
      state.page = 1;
      render();
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

      /* A region's suburbs are shown once that region is chosen. Driven from
         state rather than from the click, so it is also right on a page
         loaded with ?region= already set, and folds back up when a chip or
         "clear all" removes the region.

         A checked suburb holds its own group open too: a ?suburb= link can
         arrive without its region, and a filter you cannot see is a filter
         you cannot turn off. */
      $$(".areagroup").forEach(function (group) {
        var region = group.querySelector('[data-filter="region"]');
        var open = !!(region && region.checked) ||
          !!group.querySelector('[data-filter="suburb"]:checked');
        group.classList.toggle("is-open", open);
      });

      syncMapToArea();
    }

    /* The suburbs the area filter is currently showing, as the lowercase
       names the map keys its pins by. A checked region means all of its
       suburbs; otherwise only the suburbs ticked individually. Read from
       the popover because it lists exactly the suburbs that have listings
       in this view. */
    function activeAreaNames() {
      var names = [];
      $$(".areagroup").forEach(function (group) {
        var region = group.querySelector('[data-filter="region"]');
        var subs = $$('[data-filter="suburb"]', group);
        subs.forEach(function (s) {
          if ((region && region.checked) || s.checked) {
            names.push(s.value.toLowerCase());
          }
        });
      });
      return names;
    }

    /* Only when the area actually changed. syncInputs() runs on every
       filter change, and reframing the map because someone picked a price
       would throw away a zoom they set by hand with a pill. */
    var lastAreaKey = null;
    DirAPI.syncMapToArea = function () { syncMapToArea(); };
    function syncMapToArea() {
      if (!DirAPI.zoomToAreas) return;
      var names = activeAreaNames();
      var key = names.slice().sort().join("|");
      if (key === lastAreaKey) return;
      lastAreaKey = key;
      DirAPI.zoomToAreas(names);
    }

    /* The list grows in place instead of turning over a page at a time.
       state.page now counts how many pages have been LOADED rather than
       which one you are looking at, so ?pg= still restores your position
       when you come back from a listing — with the whole run re-rendered,
       not just the tenth page of it.

       The button is the only way the list grows. It used to be clicked
       for you by an observer when it scrolled into view; now reaching the
       end of a run is a place to stop rather than a place that keeps
       going, and the next ten are asked for. Server-rendered pagination
       and rel=next are untouched, so crawlers still get real paginated
       URLs.

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

    /* Three dots while the next run is on its way. The listings are
       already in memory, so appending them is instantaneous — which reads
       as the page twitching rather than as more listings arriving. The
       pause is there to be seen, but it is now answering a click rather
       than pacing a scroll, so it is short: long enough for the dots to
       register, not long enough to feel like waiting. */
    var moreDots = document.createElement("div");
    moreDots.className = "loadmore__dots";
    moreDots.hidden = true;
    moreDots.setAttribute("aria-hidden", "true");
    moreDots.innerHTML = "<span></span><span></span><span></span>";

    moreBox.appendChild(moreBtn);
    moreBox.appendChild(moreDots);
    moreBox.appendChild(moreNote);

    var PAUSE = 320;
    var pending = false;
    var timer = null;

    function loadNext() {
      if (pending || moreBtn.hidden) return;
      pending = true;
      moreBtn.hidden = true;
      moreDots.hidden = false;
      moreNote.textContent = "Loading more listings…";

      timer = window.setTimeout(function () {
        pending = false;
        state.page += 1;
        render(); // more() puts the button back and clears the dots
        /* Follow the button down the page. Every load is a deliberate
           click now, so this can never snatch focus from someone who did
           not ask for it. */
        if (!moreBtn.hidden) moreBtn.focus();
      }, PAUSE);
    }

    moreBtn.addEventListener("click", function () { loadNext(); });

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

    /* Numbered pages, on category pages. A list that grows pushed the guide,
       the FAQs and everything else further down with every click; a page
       of ten keeps them where they are. Page changes are always a click,
       so the scroll back to the top of the results can never surprise
       anybody. */
    var pagerBox = null;
    function pager(list, pages) {
      moreBox.hidden = true;
      if (!pagerBox) {
        pagerBox = document.createElement("nav");
        pagerBox.className = "pager pager--stack";
        pagerBox.setAttribute("aria-label", "Listing pages");
        root.parentNode.insertBefore(pagerBox, moreBox);
        pagerBox.addEventListener("click", function (e) {
          var b = e.target.closest && e.target.closest("[data-page]");
          if (!b || b.disabled) return;
          state.page = parseInt(b.getAttribute("data-page"), 10) || 1;
          render();
          var head = $("#results") || root;
          var top = head.getBoundingClientRect().top + window.pageYOffset - chromeTop() - 16;
          window.scrollTo({ top: Math.max(0, top), behavior: reducedMotion ? "auto" : "smooth" });
          /* The button that was pressed has just been redrawn away. Focus
             goes to the results heading -- where a keyboard or screen-reader
             user now needs to start reading -- rather than falling to the top
             of the document. */
          head.setAttribute("tabindex", "-1");
          head.focus({ preventScroll: true });
          catEvent("category_page", { page: state.page, results_count: lastCount });
        });
      }
      if (pages <= 1) { pagerBox.hidden = true; pagerBox.innerHTML = ""; return; }
      pagerBox.hidden = false;
      var h = '<p class="pager__note">Page ' + state.page + " of " + pages + "</p>" + '<div class="pager__row">' +
        '<button type="button" class="pager__btn" data-page="' + (state.page - 1) + '"' + (state.page === 1 ? " disabled" : "") + ' aria-label="Previous page">&larr;</button>';
      for (var i = 1; i <= pages; i++) {
        // A long run collapses to the first, the last and the pages either
        // side of this one.
        if (pages > 7 && i !== 1 && i !== pages && Math.abs(i - state.page) > 1) {
          if (i === 2 || i === pages - 1) h += '<span class="pager__gap" aria-hidden="true">&hellip;</span>';
          continue;
        }
        h += '<button type="button" class="pager__num' + (i === state.page ? " is-current" : "") + '" data-page="' + i + '"' +
          (i === state.page ? ' aria-current="page"' : "") + ' aria-label="Page ' + i + '">' + i + "</button>";
      }
      h += '<button type="button" class="pager__btn" data-page="' + (state.page + 1) + '"' + (state.page === pages ? " disabled" : "") + ' aria-label="Next page">&rarr;</button></div>';
      pagerBox.innerHTML = h;
    }

    root.addEventListener("click", function (e) {
      var b = e.target.closest && e.target.closest("[data-dir-clear]");
      if (!b) return;
      resetFilters();
      // The button that was focused has just been redrawn away; hand focus
      // to the results rather than let it fall to the top of the page.
      root.setAttribute("tabindex", "-1");
      root.focus({ preventScroll: true });
    });

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
      var found = organic(DATA.listings.filter(matches).sort(sortFn));
      var idx = -1;
      found.forEach(function (l, i) { if (idx === -1 && l.url === url) idx = i; });
      if (idx === -1) {
        // Not in the list: it may be one of the featured cards above it.
        var inBand = null;
        if (band && !band.hidden) {
          $$(".listing__name a", band).forEach(function (a) {
            if (!inBand && a.getAttribute("href") === url) inBand = a.closest("article");
          });
        }
        return inBand;
      }
      var want = Math.ceil((idx + 1) / PER_PAGE);
      if (CAT ? want !== state.page : idx >= state.page * PER_PAGE) {
        state.page = want;
        render();
      }
      var card = null;
      $$(".listing__name a", root).forEach(function (a) {
        if (!card && a.getAttribute("href") === url) card = a.closest("article");
      });
      return card;
    };

    /* The organic list: everything matching, minus the featured band while
       the band is showing, so nobody appears twice. */
    function bandOn() {
      return !!(band && bandIds.length && state.sort === "relevance" && !userFilters());
    }
    function organic(found) {
      return bandOn() ? found.filter(function (l) { return bandIds.indexOf(l.id) === -1; }) : found;
    }

    function render() {
      var found = DATA.listings.filter(matches).sort(sortFn);
      lastCount = found.length;
      // Page one only: paging on through the list should not re-present
      // the same paid cards on every page. They stay out of the list either
      // way, so nobody appears twice.
      if (band) band.hidden = !bandOn() || state.page > 1;
      var list = organic(found);
      var pages = Math.max(1, Math.ceil(list.length / PER_PAGE));
      if (state.page > pages) state.page = pages;
      var shown = CAT
        ? list.slice((state.page - 1) * PER_PAGE, state.page * PER_PAGE)
        : list.slice(0, state.page * PER_PAGE);

      var key = JSON.stringify([state.cats, state.regions, state.spec, state.svc, state.aud,
                                state.suburbs, state.price, state.format, state.rating,
                                state.q, state.sort]);

      if (!shown.length) {
        root.innerHTML = '<div class="dir__empty"><h3 class="h3">Nothing matches those filters yet</h3>' +
          '<p class="muted" style="margin-top:.5rem">Try widening the area, removing a price limit, or looking at online options.</p>' +
          (userFilters() ? '<p style="margin-top:1rem"><button type="button" class="btn btn--ghost btn--sm" data-dir-clear>Clear all filters</button></p>' : "") +
          "</div>";
        drawn = { key: null, count: 0 };
      } else if (!CAT && key === drawn.key && shown.length > drawn.count) {
        root.insertAdjacentHTML("beforeend", shown.slice(drawn.count).map(card).join(""));
        drawn.count = shown.length;
      } else {
        root.innerHTML = CAT ? withGroups(shown, list) : shown.map(card).join("");
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
      if (CAT) {
        /* The sheet's button says what closing it will show: "Show 31
           results", live. And each filter pill says how many of its options
           are on -- once the toolbar is stuck at the top of a phone, the
           chips row that lists them has scrolled away. */
        var showTxt = found.length === 1 ? "Show 1 result" : "Show " + found.length + " results";
        $$(".popover__done").forEach(function (b) { b.textContent = showTxt; });
        $$("#dirFilters [data-popover]").forEach(function (d) {
          var panel = d.oriaPanel || d.querySelector(".popover__panel");
          var sum = d.querySelector("summary");
          if (!panel || !sum) return;
          var n = panel.querySelectorAll("input:checked").length;
          sum.classList.toggle("is-active", n > 0);
          var badge = sum.querySelector(".popover__n");
          if (n) {
            if (!badge) {
              badge = document.createElement("span");
              badge.className = "popover__n";
              sum.insertBefore(badge, sum.lastElementChild);
            }
            badge.textContent = String(n);
            badge.setAttribute("aria-label", n + " selected");
          } else if (badge) {
            badge.remove();
          }
        });
      }
      // A search cleared from its chip empties the box too -- but never
      // while somebody is typing in it.
      var qBox = $("#dirQ");
      if (qBox && document.activeElement !== qBox && qBox.value.trim() !== state.q) qBox.value = state.q;
      if (CAT) pager(list, pages);
      else more(found, pages);

      if (DirAPI.watchCards) DirAPI.watchCards();

      /* The map listens for this, so its pins are always the listings the
         list is showing -- the same filters, never a second set. */
      DirAPI.lastUrls = found.map(function (l) { return l.url; });
      try {
        document.dispatchEvent(new CustomEvent("oria:dir-results", { detail: { urls: DirAPI.lastUrls } }));
      } catch (e) { /* very old browsers: the map simply keeps every pin */ }

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
        /* Category pages keep everything a shared link needs: the area and
           style choices beyond the page's own lock, the search and the sort.
           All of it is noindexed server-side (Seo\filter_params), so no
           combination becomes a page of its own. */
        if (CAT) {
          var extra = {
            region: state.regions.filter(function (v) { return v !== locked.region; }).join(","),
            spec: state.spec.filter(function (v) { return v !== locked.spec; }).join(","),
            q: state.q || "",
            picks: state.picks ? "1" : "",
            sort: state.sort !== "relevance" && state.sort !== "near" ? state.sort : ""
          };
          Object.keys(extra).forEach(function (k) {
            if (extra[k]) {
              if (lp.get(k) !== extra[k]) { lp.set(k, extra[k]); moved = true; }
            } else if (lp.has(k)) {
              lp.delete(k); moved = true;
            }
          });
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
    /* Now shown ONCE per browser, not on every view, and never left
       floating over the results: it goes the moment the popover opens, the
       visitor clicks anywhere, the toolbar scrolls out of view, or ten
       seconds pass -- and once it has been seen it does not come back.
       The UX audit found it sitting over the listing count and cards. */
    $$("[data-hint-key]").forEach(function (host) {
      var det = host.querySelector("details");
      if (!det) return;
      var key = "oria_hint_" + host.getAttribute("data-hint-key");
      var seen = false;
      try { seen = window.localStorage.getItem(key) === "1"; } catch (e) { seen = false; }
      if (seen) return;
      var hide = function () {
        host.classList.remove("is-hinting");
        window.removeEventListener("scroll", away);
        document.removeEventListener("click", hide, true);
      };
      var away = function () {
        var r = host.getBoundingClientRect();
        if (r.bottom < 0 || r.top > window.innerHeight) hide();
      };
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
          if (!inView()) return;
          try { window.localStorage.setItem(key, "1"); } catch (e) { /* private window: shows again next time, harmlessly */ }
          host.classList.add("is-hinting");
          window.addEventListener("scroll", away, { passive: true });
          document.addEventListener("click", hide, true);
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
          catEvent("dir_filter", {
            filter_name: kind,
            filter_value: val,
            results_count: lastCount
          });
        }
      });
    });

    var sortSel = $("#dirSort");
    if (sortSel && state.sort !== "relevance") sortSel.value = state.sort;
    if (sortSel) sortSel.addEventListener("change", function () {
      state.sort = sortSel.value;
      state.page = 1;
      render();
      catEvent("category_sort_change", { sort: state.sort, results_count: lastCount });
    });

    /* Free-text search. Redraws a beat after typing stops, and reports only
       once the term has settled -- otherwise "massage" arrives in analytics
       as seven searches, six of which nobody made. The same term is never
       reported twice running. The engine has always matched names, suburbs,
       services and synonyms (matches()); until now nothing on a category
       page fed it. */
    var qEl = $("#dirQ");
    if (qEl) {
      if (state.q) qEl.value = state.q;
      var qDraw = null, qReport = null, qLast = "";
      qEl.addEventListener("input", function () {
        window.clearTimeout(qDraw);
        window.clearTimeout(qReport);
        qDraw = window.setTimeout(function () {
          state.q = qEl.value.trim();
          state.page = 1;
          render();
        }, 180);
        qReport = window.setTimeout(function () {
          var term = qEl.value.trim().toLowerCase();
          if (term.length < 2 || term === qLast) return;
          qLast = term;
          // dir_search, the site's existing name for this (already in the GTM
          // allowlist and GA4's history), with the page's category added.
          catEvent("dir_search", { search_term: term, results_count: lastCount });
        }, 1200);
      });
    }

    /* ---- the button ---------------------------------------------------- */
    (function initNearMe() {
      var btn = $("[data-near]");
      var msg = $("#dirNearMsg");
      if (!btn) return;

      /* No geolocation, or an insecure page: remove the control rather than
         leave one that cannot work. Geolocation needs HTTPS; localhost is
         exempt, so development still exercises this path. */
      if (!navigator.geolocation || !window.isSecureContext) {
        btn.parentNode.removeChild(btn);
        return;
      }

      function say(text) { if (msg) msg.textContent = text || ""; }

      btn.addEventListener("click", function () {
        btn.disabled = true;
        say("Finding you\u2026");

        navigator.geolocation.getCurrentPosition(
          function (pos) {
            here = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            btn.disabled = false;

            /* Perth, roughly. Someone in another city gets an honest answer
               instead of a list whose first entry is 2,000km away. */
            var far = haversine(here.lat, here.lng, -31.9535, 115.857);
            if (far > 400) {
              here = null;
              say("You look to be about " + Math.round(far) + " km from Perth \u2014 showing everything instead.");
              return;
            }

            /* Add the option once, then select it. */
            if (sortSel && !sortSel.querySelector('option[value="near"]')) {
              var opt = document.createElement("option");
              opt.value = "near";
              opt.textContent = "Sort: Nearest to me";
              sortSel.insertBefore(opt, sortSel.firstChild);
            }
            if (sortSel) sortSel.value = "near";
            state.sort = "near";
            state.page = 1;
            render();
            btn.textContent = "Sorted by distance";
            say("");
          },
          function (err) {
            btn.disabled = false;
            say(err && err.code === 1
              ? "No problem \u2014 filter by suburb instead."
              : "Could not get your location. Try the suburb filter.");
          },
          { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
        );
      });
    })();


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

  /* Finding a neighbourhood.

     The suggestions are read out of the drawer that is already on the
     page rather than a second payload: the markup a crawler sees and the
     list the field searches are the same forty links. Nothing is
     fetched, and the drawer keeps working with the script switched off,
     because it is a <details>. */
  function initHoods() {
    $$("[data-hoods]").forEach(function (root) {
      var q = root.querySelector("[data-hoods-q]");
      var ac = root.querySelector("[data-hoods-ac]");
      var all = root.querySelector("[data-hoods-all]");
      if (!q || !ac) return;

      var rows = $$("[data-hood-name]", root).map(function (el) {
        return {
          name: el.getAttribute("data-hood-name") || "",
          region: el.getAttribute("data-hood-region") || "",
          n: el.getAttribute("data-hood-n") || "0",
          url: el.getAttribute("href"),
          slug: el.getAttribute("data-area-slug") || ""
        };
      });
      if (!rows.length) return;

      var items = [], active = -1;

      function close() {
        ac.hidden = true;
        ac.innerHTML = "";
        items = [];
        active = -1;
        q.setAttribute("aria-expanded", "false");
      }

      function mark(i) {
        items.forEach(function (el, k) {
          el.classList.toggle("is-on", k === i);
          el.setAttribute("aria-selected", k === i ? "true" : "false");
        });
        active = i;
      }

      function open(term) {
        var hits = rows.filter(function (r) {
          return r.name.toLowerCase().indexOf(term) > -1;
        }).slice(0, 8);

        ac.innerHTML = "";
        items = [];

        /* Grouped by region, in the order the matches arrive, so the
           strongest neighbourhood heads its own group. */
        var seen = [];
        hits.forEach(function (r) { if (seen.indexOf(r.region) < 0) seen.push(r.region); });

        seen.forEach(function (region) {
          var head = document.createElement("p");
          head.className = "hoods__acreg";
          head.textContent = region;
          ac.appendChild(head);

          hits.filter(function (r) { return r.region === region; }).forEach(function (r) {
            var a = document.createElement("a");
            a.className = "hoods__acitem";
            a.href = r.url;
            a.setAttribute("role", "option");
            a.setAttribute("aria-selected", "false");
            a.setAttribute("data-area-promo", "hub-hoods-find");
            a.setAttribute("data-area-slug", r.slug);
            a.innerHTML = '<span class="hoods__acname"></span><span class="hoods__acn"></span>';
            a.firstChild.textContent = r.name;
            a.lastChild.textContent = r.n + (r.n === "1" ? " place" : " places");
            ac.appendChild(a);
            items.push(a);
          });
        });

        if (!hits.length) {
          var none = document.createElement("p");
          none.className = "hoods__acnone";
          none.textContent = "No neighbourhood by that name yet.";
          ac.appendChild(none);
        }

        /* Always the last way out: the whole list, one click away. */
        var more = document.createElement("button");
        more.type = "button";
        more.className = "hoods__acall";
        more.setAttribute("role", "option");
        more.setAttribute("aria-selected", "false");
        more.textContent = "Browse all neighbourhoods";
        more.addEventListener("click", function () {
          if (all) {
            all.open = true;
            all.scrollIntoView({ block: "nearest" });
          }
          close();
        });
        ac.appendChild(more);
        items.push(more);

        ac.hidden = false;
        q.setAttribute("aria-expanded", "true");
        mark(-1);
      }

      q.addEventListener("input", function () {
        var term = q.value.trim().toLowerCase();
        if (term.length < 1) { close(); return; }
        open(term);
      });

      q.addEventListener("keydown", function (e) {
        if (e.key === "Escape") { close(); return; }
        if (ac.hidden || !items.length) return;
        if (e.key === "ArrowDown") { e.preventDefault(); mark((active + 1) % items.length); }
        else if (e.key === "ArrowUp") { e.preventDefault(); mark((active - 1 + items.length) % items.length); }
        else if (e.key === "Enter" && active > -1) { e.preventDefault(); items[active].click(); }
      });

      document.addEventListener("click", function (e) {
        if (!root.contains(e.target)) close();
      });
    });
  }

  /* What this visitor looked at last time on What's On.

     Offered rather than applied. Restoring a filter silently means
     somebody returns to a page showing eight events of twenty-two with no
     visible reason, which reads as a broken page rather than a helpful
     one -- so the choice is put in front of them with a way to forget it. */
  var WO_PREF = "oria_whatson_pref";

  function woPref() {
    try {
      var raw = window.localStorage.getItem(WO_PREF);
      var o = raw ? JSON.parse(raw) : null;
      return o && typeof o === "object" ? o : null;
    } catch (e) {
      return null;
    }
  }

  function writeWoPref(o) {
    try {
      if (o) window.localStorage.setItem(WO_PREF, JSON.stringify(o));
      else window.localStorage.removeItem(WO_PREF);
    } catch (e) { /* private window: the page simply does not remember */ }
  }

  /* Explore a category: two panels behind two tabs, and a field over the
     suburbs.

     The panels are both in the HTML and both visible until this runs, so
     a page with no script -- or a crawler -- gets every link stacked, and
     the tab bar only appears once there is something to switch. The
     suggestions are read out of the drawer already on the page rather
     than a second payload. */
  function initExploreTabs() {
    $$("[data-xtabs]").forEach(function (root) {
      var bar = root.querySelector("[data-xtabs-bar]");
      var panels = $$("[data-xtabs-panel]", root);

      if (bar && panels.length === 2) {
        var tabs = $$(".xtabs__tab", bar);
        bar.hidden = false;

        function show(i) {
          tabs.forEach(function (t, k) {
            t.setAttribute("aria-selected", k === i ? "true" : "false");
            t.tabIndex = k === i ? 0 : -1;
          });
          panels.forEach(function (p, k) { p.hidden = k !== i; });
        }

        tabs.forEach(function (t, i) {
          t.addEventListener("click", function () { show(i); });
          t.addEventListener("keydown", function (e) {
            if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
            e.preventDefault();
            var next = (i + (e.key === "ArrowRight" ? 1 : tabs.length - 1)) % tabs.length;
            show(next);
            tabs[next].focus();
          });
        });
        show(0);
      }

      /* The suburb field. Same shape as the neighbourhood one: it only
         suggests what is already linked below it. */
      var q = root.querySelector("[data-xtabs-q]");
      var ac = root.querySelector("[data-xtabs-ac]");
      if (!q || !ac) return;

      var rows = $$("[data-xtabs-place]", root).map(function (el) {
        return {
          name: el.getAttribute("data-xtabs-place") || "",
          label: el.childNodes[0] ? el.childNodes[0].textContent.trim() : "",
          n: (el.querySelector(".pill__n") || {}).textContent || "",
          url: el.getAttribute("href")
        };
      });
      if (!rows.length) return;

      var items = [], active = -1;

      function close() {
        ac.hidden = true;
        ac.innerHTML = "";
        items = [];
        active = -1;
        q.setAttribute("aria-expanded", "false");
      }

      function mark(i) {
        items.forEach(function (el, k) {
          el.classList.toggle("is-on", k === i);
          el.setAttribute("aria-selected", k === i ? "true" : "false");
        });
        active = i;
      }

      q.addEventListener("input", function () {
        var term = q.value.trim().toLowerCase();
        if (!term) { close(); return; }
        var hits = rows.filter(function (r) { return r.name.indexOf(term) > -1; }).slice(0, 8);
        ac.innerHTML = "";
        items = [];
        hits.forEach(function (r) {
          var a = document.createElement("a");
          a.className = "xtabs__acitem";
          a.href = r.url;
          a.setAttribute("role", "option");
          a.setAttribute("aria-selected", "false");
          a.innerHTML = '<span></span><span class="xtabs__acn"></span>';
          a.firstChild.textContent = r.label;
          a.lastChild.textContent = r.n.trim();
          ac.appendChild(a);
          items.push(a);
        });
        if (!hits.length) {
          var none = document.createElement("p");
          none.className = "xtabs__acnone";
          none.textContent = "Nothing listed in a suburb by that name.";
          ac.appendChild(none);
        }
        ac.hidden = false;
        q.setAttribute("aria-expanded", "true");
        mark(-1);
      });

      q.addEventListener("keydown", function (e) {
        if (e.key === "Escape") { close(); return; }
        if (ac.hidden || !items.length) return;
        if (e.key === "ArrowDown") { e.preventDefault(); mark((active + 1) % items.length); }
        else if (e.key === "ArrowUp") { e.preventDefault(); mark((active - 1 + items.length) % items.length); }
        else if (e.key === "Enter" && active > -1) { e.preventDefault(); items[active].click(); }
      });

      document.addEventListener("click", function (e) {
        if (!root.contains(e.target)) close();
      });
    });
  }

  /* Neighbourhood promotions, wherever they are. One listener rather than
     one per component, and no personal data -- where it was and which area,
     nothing about who clicked. */
  function initAreaTracking() {
    document.addEventListener("click", function (e) {
      var el = e.target.closest && e.target.closest("[data-area-promo]");
      if (!el) return;
      var promo = {
        component_position: el.getAttribute("data-area-promo") || "",
        area_slug: el.getAttribute("data-area-slug") || "",
        destination_url: el.getAttribute("href") || ""
      };
      /* The discovery card also says how many places it offered and which
         of its two shapes it was drawn in -- a text card and a card with a
         photograph are different offers and worth telling apart. Other
         promos carry neither and send neither. */
      if (el.getAttribute("data-area-count")) promo.area_count = Number(el.getAttribute("data-area-count"));
      if (el.getAttribute("data-area-variant")) promo.component_variant = el.getAttribute("data-area-variant");
      pushEvent("area_promo_click", promo);
    });
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

    /* An event page counts a view the same way, so an organiser can be
       shown something real about the page we built for them. */
    if (window.ORIA_EVENT) {
      pushEvent("event_view", { event_id: window.ORIA_EVENT.id });
      var eid = parseInt(window.ORIA_EVENT.id, 10);
      if (eid && window.ORIA_TRACK && navigator.sendBeacon) {
        try {
          navigator.sendBeacon(
            ORIA_TRACK.url,
            new Blob([JSON.stringify({ id: eid, type: "view" })], { type: "application/json" })
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
      var payload = {
        link_url: el.getAttribute("href") || "",
        link_text: (el.textContent || "").replace(/\s+/g, " ").trim().slice(0, 100)
      };
      /* Anything the markup chose to say about itself. Cheaper than a tag
         per link, and the names are the report's names. */
      if (el.getAttribute("data-area-slug")) payload.area_slug = el.getAttribute("data-area-slug");
      if (el.getAttribute("data-term-slug")) payload.standout_term_slug = el.getAttribute("data-term-slug");
      /* Which of two identical triggers was pressed -- the claim link by
         the business details, or the panel at the bottom. Same event
         either way; without this the report cannot tell them apart. */
      if (el.getAttribute("data-oria-placement")) payload.cta_placement = el.getAttribute("data-oria-placement");
      pushEvent(el.getAttribute("data-oria-event"), payload);
    });

    /* A disclosure reports being opened, not being on the page. Without
       this a <summary> fell through to the render branch below and the
       event fired for every visitor who scrolled past it -- the same
       fault the comment above describes for links. */
    document.addEventListener("toggle", function (e) {
      var d = e.target;
      if (!d || d.tagName !== "DETAILS" || !d.open) return;
      var sum = d.querySelector("summary[data-oria-event]");
      if (!sum || sum.parentElement !== d) return;
      pushEvent(sum.getAttribute("data-oria-event"), {
        area_slug: d.getAttribute("data-area-slug") || ""
      });
    }, true);

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
      if (el.tagName === "A" || el.tagName === "BUTTON" || el.tagName === "SUMMARY") {
        return; // handled by the delegated click or toggle above
      }
      var place = el.getAttribute("data-oria-placement");
      pushEvent(name, place ? { cta_placement: place } : null);
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

  /* The badge's first line. The year is whatever the guide could date
     itself to; without one the award still has a name and an owner. */
  function bestEyebrow(year) {
    return year ? "Oria Best of " + year : "Oria Best of";
  }

  /* --- Saved listings --------------------------------------------------- */
  /* Kept on the device, never sent anywhere. No account to create, and
     nothing for us to hold. The cost is honest and the saved page says it:
     clear the browser and the list goes with it. */
  var SAVE_KEY = "oria_saved";

  /* --- My Oria ---------------------------------------------------------
     window.ORIA_ME is printed by the plugin on every page: whether anyone
     is signed in and, if so, the slugs they have saved and tried. Signed
     in, the account is the source of truth and every change is posted to
     it; signed out, the device keeps the list exactly as before, and a
     save is followed once per session by an invitation to keep it. */
  var ME = window.ORIA_ME || {};
  function meOn() { return !!ME.loggedIn; }

  function deviceSavedIds() {
    try {
      var raw = window.localStorage.getItem(SAVE_KEY);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr.map(String) : [];
    } catch (e) {
      /* Private windows and blocked site data throw on read. */
      return [];
    }
  }

  function savedIds() {
    return meOn() ? (ME.saved || []).map(String) : deviceSavedIds();
  }

  function writeSaved(ids) {
    if (meOn()) {
      var before = (ME.saved || []).map(String);
      ME.saved = ids.map(String);
      ids.forEach(function (id) { if (before.indexOf(id) < 0) meActivity(id, "saved", true); });
      before.forEach(function (id) { if (ids.indexOf(id) < 0) meActivity(id, "saved", false); });
      paintSavedNav();
      return true;
    }
    try {
      window.localStorage.setItem(SAVE_KEY, JSON.stringify(ids));
      paintSavedNav();
      return true;
    } catch (e) {
      return false;
    }
  }

  /* Every write is numbered, and only the newest one's answer is painted.
     Two clicks in quick succession -- save, then tried -- are two requests
     the server may finish in either order, and the state in the earlier
     one is already stale by the time it lands. */
  var meSeq = 0;
  function mePost(path, body) {
    var seq = ++meSeq;
    return fetch(ME.api + path, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-WP-Nonce": ME.nonce },
      body: JSON.stringify(body)
    }).then(function (r) { return r.ok ? r.json() : null; })
      .then(function (state) { if (state) state._seq = seq; return state; })
      .catch(function () { return null; });
  }

  /* The server answers every write with the whole state; paint from that
     rather than from what the click assumed. */
  function meApply(state) {
    if (!state || !state.saved) return;
    if (state._seq && state._seq < meSeq) {
      /* A newer request is in flight or already painted; its answer
         supersedes this one, but a badge earned here is still news. */
      (state.new_badges || []).forEach(function (b) { meToast("Badge earned: " + b.label + " ✦"); });
      return;
    }
    ME.saved = state.saved.map(String);
    ME.tried = (state.tried || []).map(String);
    paintSavedNav();
    paintTried();
    /* Hearts drawn before the answer -- or before the device list joined
       the account -- catch up here. */
    $$("[data-card-save]").forEach(function (b) {
      b.setAttribute("aria-pressed", ME.saved.indexOf(String(b.dataset.cardSave)) > -1 ? "true" : "false");
    });
    $$("[data-save]").forEach(function (b) {
      var on = ME.saved.indexOf(String(b.dataset.save)) > -1;
      b.setAttribute("aria-pressed", on ? "true" : "false");
      var label = b.querySelector(".savebtn__label");
      if (label) label.textContent = on ? "Saved" : "Save";
    });
    var counts = state.counts || {};
    $$("[data-my-count]").forEach(function (el) {
      var k = el.dataset.myCount;
      var n = k === "badges" ? state.badges : counts[k];
      if (typeof n === "number") el.textContent = String(n);
    });
    (state.new_badges || []).forEach(function (b) {
      meToast("Badge earned: " + b.label + " ✦");
      pushEvent("oria_badge_earned", { badge: b.slug });
    });
  }

  function meActivity(slug, type, on) {
    if (!meOn()) return Promise.resolve(null);
    pushEvent(
      type === "tried" ? (on ? "oria_mark_tried" : "oria_remove_tried") : (on ? "oria_save_listing" : "oria_unsave_listing"),
      { listing_id: slug }
    );
    return mePost("activity", { slug: slug, type: type, on: !!on }).then(function (state) {
      meApply(state);
      return state;
    });
  }

  /* Whatever the device saved before there was an account joins it, once,
     and the device copy is retired so the two can never disagree. */
  function meSync() {
    if (!meOn()) return;
    var local = deviceSavedIds();
    if (!local.length) return;
    var extra = local.filter(function (id) { return (ME.saved || []).indexOf(id) < 0; });
    var done = function () { try { window.localStorage.removeItem(SAVE_KEY); } catch (e) {} };
    if (!extra.length) { done(); return; }
    mePost("sync", { saved: extra }).then(function (state) { meApply(state); done(); });
  }

  function paintTried() {
    var tried = (ME.tried || []).map(String);
    $$("[data-tried], [data-my-tried]").forEach(function (b) {
      var slug = String(b.dataset.tried || b.dataset.myTried);
      var on = tried.indexOf(slug) > -1;
      b.setAttribute("aria-pressed", on ? "true" : "false");
      var label = b.querySelector(".triedbtn__label");
      if (label) label.textContent = on ? "In your passport" : "I've tried this";
    });
  }

  function meToast(text) {
    var t = document.createElement("div");
    t.className = "mytoast";
    t.setAttribute("role", "status");
    t.textContent = text;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add("is-in"); });
    setTimeout(function () {
      t.classList.remove("is-in");
      setTimeout(function () { t.remove(); }, 300);
    }, 4200);
  }

  /* The invitation a guest sees after saving: once per session, and never
     in the way of the save itself, which has already happened on the
     device. A "tried" has nowhere to go without an account, so that one
     asks every time. */
  function meNudge(kind) {
    if (meOn() || !ME.registerUrl) return;
    var key = "oria_me_nudged";
    try {
      if (kind !== "tried" && window.sessionStorage.getItem(key)) return;
      window.sessionStorage.setItem(key, "1");
    } catch (e) {}
    var back = encodeURIComponent(window.location.href);
    var title = kind === "tried" ? "Add this to your Wellness Passport" : "Saved to this device";
    var body = kind === "tried"
      ? "Create a free My Oria account to mark places you have tried and collect passport badges as you explore."
      : "Create a free My Oria account to keep your saved places on every device and build your Wellness Passport.";
    var m = document.createElement("div");
    m.className = "mymodal";
    m.innerHTML =
      '<div class="mymodal__veil" data-me-close></div>' +
      '<div class="mymodal__box" role="dialog" aria-modal="true" aria-labelledby="meModalTitle">' +
        '<h2 class="h3" id="meModalTitle">' + esc(title) + "</h2>" +
        "<p>" + esc(body) + "</p>" +
        '<div class="mymodal__acts">' +
          '<a class="btn btn--dark" href="' + esc(ME.registerUrl) + "?redirect_to=" + back + '">Create My Oria</a>' +
          '<a class="btn btn--ghost" href="' + esc(ME.loginUrl) + "?redirect_to=" + back + '">Log in</a>' +
        "</div>" +
        '<button class="mymodal__close" type="button" data-me-close aria-label="Not now">' + ICON.x + "</button>" +
      "</div>";
    document.body.appendChild(m);
    var first = m.querySelector(".btn");
    if (first) first.focus();
    function close() { m.remove(); document.removeEventListener("keydown", onKey); }
    function onKey(e) { if (e.key === "Escape") close(); }
    m.addEventListener("click", function (e) { if (e.target.closest("[data-me-close]")) close(); });
    document.addEventListener("keydown", onKey);
  }

  function initMe() {
    meSync();
    paintTried();
    document.addEventListener("click", function (e) {
      var t = e.target.closest && e.target.closest("[data-tried], [data-my-tried]");
      if (t) {
        if (!meOn()) { meNudge("tried"); return; }
        var slug = String(t.dataset.tried || t.dataset.myTried);
        var on = t.getAttribute("aria-pressed") !== "true";
        t.setAttribute("aria-pressed", on ? "true" : "false");
        var label = t.querySelector(".triedbtn__label");
        if (label) label.textContent = on ? "Added to your passport" : "I've tried this";
        meActivity(slug, "tried", on);
        return;
      }
      var r = e.target.closest && e.target.closest("[data-my-remove]");
      if (r && meOn()) {
        var card = r.closest("[data-my-place]");
        r.disabled = true;
        meActivity(String(r.dataset.slug), r.dataset.myRemove, false).then(function () {
          if (card) card.remove();
          var list = document.querySelector("[data-my-list]");
          var empty = document.querySelector("[data-my-empty]");
          if (empty && list && !list.querySelector("[data-my-place]")) empty.hidden = false;
        });
      }
    });
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
        if (at < 0) meNudge("save");
        pushEvent(at > -1 ? "listing_unsave" : "listing_save", { listing_id: id });
      });
    });

    paint();
  }

  /* --- Saved events ------------------------------------------------------ */
  /* Events are kept apart from saved practices, and for a different reason.
     A practice is looked up in the directory payload every page already
     carries; an event is not in that payload, and shipping every event to
     every page to support a shortlist would be a poor trade. So a save
     stores the handful of facts the list needs -- title, link, when, where
     -- as a snapshot on the device.

     The snapshot can go stale if the organiser moves the event. That is why
     the saved page links straight through to the page, which is always the
     truth, and why a finished event drops off the list on its own. */
  var EVENT_KEY = "oria_saved_events";

  function savedEvents() {
    try {
      var raw = window.localStorage.getItem(EVENT_KEY);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr.filter(function (e) { return e && e.id && e.url; }) : [];
    } catch (e) {
      return [];
    }
  }

  function writeSavedEvents(list) {
    try {
      window.localStorage.setItem(EVENT_KEY, JSON.stringify(list));
      return true;
    } catch (e) {
      return false;
    }
  }

  function initSaveEvent() {
    var buttons = $$("[data-save-event]");
    if (!buttons.length) return;

    function paint() {
      var ids = savedEvents().map(function (e) { return String(e.id); });
      buttons.forEach(function (b) {
        var on = ids.indexOf(String(b.dataset.saveEvent)) > -1;
        b.setAttribute("aria-pressed", on ? "true" : "false");
        var label = b.querySelector(".savebtn__label");
        if (label) label.textContent = on ? "Saved" : "Save";
      });
    }

    buttons.forEach(function (b) {
      b.addEventListener("click", function () {
        var id = String(b.dataset.saveEvent);
        var list = savedEvents();
        var at = -1;
        list.forEach(function (e, i) { if (String(e.id) === id) at = i; });

        if (at > -1) {
          list.splice(at, 1);
        } else {
          list.push({
            id: id,
            title: b.dataset.title || "",
            url: b.dataset.url || "",
            when: b.dataset.when || "",
            where: b.dataset.where || ""
          });
        }
        if (!writeSavedEvents(list)) {
          b.setAttribute("title", "Saving needs site data enabled in your browser.");
          return;
        }
        paint();
        document.dispatchEvent(new CustomEvent("oria:saved-events"));
        pushEvent(at > -1 ? "event_unsave" : "event_saved", { event_id: id });

        /* Saves are one of the few signals an organiser can act on, so the
           site counts them as well -- the same anonymous beacon as a view. */
        if (at === -1 && window.ORIA_TRACK && navigator.sendBeacon) {
          try {
            navigator.sendBeacon(
              ORIA_TRACK.url,
              new Blob([JSON.stringify({ id: parseInt(id, 10), type: "save" })], { type: "application/json" })
            );
          } catch (err) { /* counting must never break a tap */ }
        }
      });
    });

    paint();
  }

  /* The saved page's events half. Rendered from the snapshot, newest date
     first, with anything already finished quietly dropped. */
  function initSavedEventsPage() {
    var root = document.querySelector("[data-saved-events-list]");
    if (!root) return;
    var section = document.querySelector("[data-saved-events]");
    var list = savedEvents();

    root.innerHTML = list.map(function (e) {
      /* The date is already the line above; repeating it here was just
         noise on a small card. */
      var meta = e.where || "";
      /* Built from the page URL rather than stored: one fewer thing in the
         snapshot to go stale, and the route has been /calendar.ics since
         the day it shipped. */
      var ics = String(e.url || "").replace(/[?#].*$/, "").replace(/\/+$/, "") + "/calendar.ics";
      return (
        '<div class="evcard evcard--saved">' +
        '<a class="evcard__link" href="' + esc(e.url) + '">' +
        (e.when ? '<span class="micro">' + esc(e.when) + "</span>" : "") +
        '<b class="evcard__name">' + esc(e.title) + "</b>" +
        (meta ? '<span class="evcard__meta">' + esc(meta) + "</span>" : "") +
        "</a>" +
        '<a class="evcard__ics" href="' + esc(ics) + '" download>Add to calendar</a>' +
        "</div>"
      );
    }).join("");

    if (section) section.hidden = list.length === 0;
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

  /* What's On filters: rows carry precomputed tokens, this only matches.

     Filter state lives in the URL (?date=&area=&type=&price=), so a chosen
     view can be bookmarked, shared and reloaded, and Back undoes a filter
     instead of leaving the page. Only non-default values are written, so
     the plain archive URL stays clean. */
  function initWhatsOn() {
    var root = document.querySelector("[data-whatson]");
    if (!root) return;

    var PARAM = { when: "date", suburb: "area", type: "type", band: "price", day: "on", feel: "feel" };
    var DEFAULTS = { when: "all", suburb: "", type: "", band: "", day: "", feel: "" };
    var state = { when: "all", suburb: "", type: "", band: "", day: "", feel: "" };

    var empty = root.querySelector("[data-wo-empty]");
    var countEl = root.querySelector("[data-wo-count]");
    var feat = root.querySelector("[data-wo-feat]");
    var clears = $$("[data-wo-clear]", root);

    function isDefault() {
      return Object.keys(DEFAULTS).every(function (k) { return state[k] === DEFAULTS[k]; });
    }

    function readUrl() {
      var q = new URLSearchParams(window.location.search);
      Object.keys(PARAM).forEach(function (k) {
        var v = q.get(PARAM[k]);
        if (v !== null) state[k] = v;
      });
    }

    function writeUrl(push) {
      var q = new URLSearchParams(window.location.search);
      Object.keys(PARAM).forEach(function (k) {
        if (state[k] === DEFAULTS[k]) q.delete(PARAM[k]);
        else q.set(PARAM[k], state[k]);
      });
      var qs = q.toString();
      var url = window.location.pathname + (qs ? "?" + qs : "") + window.location.hash;
      try {
        window.history[push ? "pushState" : "replaceState"]({ wo: 1 }, "", url);
      } catch (e) { /* history blocked; filtering still works */ }
    }

    /* Controls follow the state, not the other way round, so a URL opened
       cold shows the same chips pressed as a click would have. */
    function syncControls() {
      $$(".fchip", root).forEach(function (c) {
        var on = state[c.dataset.f] === c.dataset.v;
        c.classList.toggle("is-on", on);
        c.setAttribute("aria-pressed", on ? "true" : "false");
      });
      $$(".wodate", root).forEach(function (b) {
        var on = state.day === b.dataset.v;
        b.classList.toggle("is-on", on);
        b.setAttribute("aria-pressed", on ? "true" : "false");
      });
      $$(".wofeel", root).forEach(function (b) {
        var on = state.feel === b.dataset.v;
        b.classList.toggle("is-on", on);
        b.setAttribute("aria-pressed", on ? "true" : "false");
      });
      $$("select[data-f]", root).forEach(function (sel) {
        if (sel.value !== state[sel.dataset.f]) sel.value = state[sel.dataset.f];
      });
    }

    /* "Because you saved X". Built here in the browser from this
       device's own saves, matched against the cards already on the page:
       nothing about anybody's interests is sent anywhere, and there is no
       profile to build. Same type or same suburb, which is as far as the
       data honestly reaches -- two events sharing a category is a real
       thing to say; a taste model is not.

       It stays hidden until somebody has saved something, and shows only
       when it can offer more than one thing. */
    var recsBox = root.querySelector("[data-wo-recs]");
    var recsRow = root.querySelector("[data-wo-recs-row]");

    function paintRecs() {
      if (!recsBox || !recsRow) return;

      /* Hiding the box is not the same as emptying it: leave the last
         render in there and it is stale markup waiting to be shown
         again by the next thing that unhides it. */
      function off() { recsBox.hidden = true; recsRow.innerHTML = ""; }

      var saved = savedEvents();
      if (!saved.length) { off(); return; }

      var savedIdSet = {};
      saved.forEach(function (e) { savedIdSet[String(e.id)] = true; });

      /* The most recent save that is still on this page -- an event three
         months gone is a poor reason to suggest anything. */
      var seedRow = null, seedSave = null;
      for (var i = saved.length - 1; i >= 0 && !seedRow; i--) {
        var btn = root.querySelector('[data-save-event="' + saved[i].id + '"]');
        if (btn) { seedRow = btn.closest(".wkrow"); seedSave = saved[i]; }
      }
      if (!seedRow) { off(); return; }

      var type = seedRow.dataset.type || "";
      var suburb = seedRow.dataset.suburb || "";
      var picks = $$(".wkrow", root).filter(function (row) {
        var b = row.querySelector("[data-save-event]");
        if (!b || savedIdSet[b.dataset.saveEvent]) return false;
        return (type && row.dataset.type === type) || (suburb && row.dataset.suburb === suburb);
      }).slice(0, 3);

      if (picks.length < 2) { off(); return; }

      recsBox.querySelector(".worecs__title").textContent = "Because you saved " + (seedSave.title || "that one");
      recsRow.innerHTML = "";
      picks.forEach(function (row) {
        var link = row.querySelector(".wkrow__link");
        var meta = (row.querySelector(".wkrow__body em") || {}).textContent || "";
        var a = document.createElement("a");
        a.className = "worec";
        a.href = link ? link.getAttribute("href") : "#";
        a.innerHTML = '<b class="worec__name"></b><span class="worec__meta"></span>';
        a.querySelector(".worec__name").textContent = link ? link.textContent.trim() : "";
        a.querySelector(".worec__meta").textContent = meta.replace(/\s+/g, " ").trim();
        recsRow.appendChild(a);
      });
      recsBox.hidden = false;
    }

    /* The map. A second way to look at the rows already on the page --
       it reads their coordinates off them, so it cannot disagree with the
       list, and it redraws whenever the filters do.

       Leaflet arrives the first time somebody presses Map and never
       otherwise. If it fails to arrive the list is still the whole page,
       which is the point: the map is never the only way to browse. */
    var viewBar = root.querySelector("[data-wo-view]");
    var mapWrap = root.querySelector("[data-wo-map]");
    var mapHost = root.querySelector("[data-wo-map-canvas]");
    var listHost = root.querySelector("[data-wo-days]") || root;
    var theMap = null, layer = null, mapLoading = null, mode = "list";

    function withLeafletWO(cb) {
      if (window.L) { cb(); return; }
      var LF = window.ORIA_LEAFLET;
      if (!LF) return;
      if (!mapLoading) {
        mapLoading = new Promise(function (resolve, reject) {
          var css = document.createElement("link");
          css.rel = "stylesheet"; css.href = LF.css;
          document.head.appendChild(css);
          var js = document.createElement("script");
          js.src = LF.js; js.onload = resolve; js.onerror = reject;
          document.head.appendChild(js);
        });
        if (mapWrap) mapWrap.setAttribute("aria-busy", "true");
      }
      mapLoading.then(function () {
        if (mapWrap) mapWrap.removeAttribute("aria-busy");
        cb();
      }, function () {
        if (mapWrap) mapWrap.removeAttribute("aria-busy");
        if (mapHost) mapHost.innerHTML = '<p class="womap__fail">The map could not load just now. Every event is in the list.</p>';
      });
    }

    function drawMap() {
      if (!window.L || !mapHost) return;
      if (!theMap) {
        theMap = L.map(mapHost, { scrollWheelZoom: false });
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 18,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(theMap);
      }
      if (layer) { theMap.removeLayer(layer); layer = null; }

      var pts = [];
      $$(".wkrow", root).forEach(function (row) {
        if (row.hidden || !row.dataset.lat || !row.dataset.lng) return;
        var link = row.querySelector(".wkrow__link");
        pts.push({
          lat: parseFloat(row.dataset.lat),
          lng: parseFloat(row.dataset.lng),
          rough: row.dataset.precision !== "address",
          title: link ? link.textContent.trim() : "",
          url: link ? link.getAttribute("href") : "#",
          when: (row.querySelector(".wkrow__body em") || {}).textContent || ""
        });
      });

      if (!pts.length) {
        if (mapHost) mapHost.setAttribute("data-empty", "1");
        return;
      }
      mapHost.removeAttribute("data-empty");

      /* Several events at one venue -- or one suburb centre -- stack
         exactly on top of each other, so they share a pin and the popup
         lists them. Anything else would hide events behind events. */
      var byPoint = {};
      pts.forEach(function (p) {
        var k = p.lat.toFixed(4) + "," + p.lng.toFixed(4);
        (byPoint[k] = byPoint[k] || []).push(p);
      });

      layer = L.layerGroup();
      var bounds = [];
      Object.keys(byPoint).forEach(function (k) {
        var group = byPoint[k];
        var here = group[0];
        bounds.push([here.lat, here.lng]);
        var mk = L.circleMarker([here.lat, here.lng], {
          radius: group.length > 1 ? 10 : 7,
          color: "#fff", weight: 2,
          fillColor: "#0E3B38", fillOpacity: 0.95
        });
        var html = group.map(function (p) {
          return '<a href="' + esc(p.url) + '"><b>' + esc(p.title) + "</b></a>" +
            (p.when ? "<span>" + esc(p.when.replace(/\s+/g, " ").trim()) + "</span>" : "");
        }).join("");
        if (here.rough) html += '<em class="womap__rough">Shown at the suburb centre</em>';
        mk.bindPopup('<div class="womap__pop">' + html + "</div>", { minWidth: 190 });
        if (group.length > 1) {
          mk.bindTooltip(String(group.length), { permanent: true, direction: "center", className: "womap__count" });
        }
        layer.addLayer(mk);
      });
      layer.addTo(theMap);
      theMap.fitBounds(bounds, { padding: [28, 28], maxZoom: 14 });
      window.setTimeout(function () { theMap.invalidateSize(); }, 0);
    }

    function setMode(next) {
      mode = next;
      if (viewBar) {
        $$(".woview__btn", viewBar).forEach(function (b) {
          var on = b.dataset.woMode === mode;
          b.classList.toggle("is-on", on);
          b.setAttribute("aria-pressed", on ? "true" : "false");
        });
      }
      if (mapWrap) mapWrap.hidden = mode !== "map";
      if (mode === "map") withLeafletWO(drawMap);
    }

    if (viewBar && mapWrap && window.ORIA_LEAFLET) {
      viewBar.hidden = false;
      $$(".woview__btn", viewBar).forEach(function (b) {
        b.addEventListener("click", function () { setMode(b.dataset.woMode); });
      });
    }

    /* What a chosen feeling is showing, said once in words rather than
       repeated as a coloured state on seven tiles. */
    var feelNote = root.querySelector("[data-wo-feelnote]");
    var feelBtns = $$("[data-f='feel']", root);

    function paintFeel(shown) {
      if (!feelNote) return;
      /* Silent on an empty result: the empty state below already says so
         once, and the neighbourhood line says it again -- three ways of
         reporting the same nothing is two too many. */
      if (!state.feel || shown < 1) { feelNote.hidden = true; return; }
      var btn = feelBtns.filter(function (b) { return b.getAttribute("data-v") === state.feel; })[0];
      var name = btn ? (btn.querySelector(".wofeel__name") || {}).textContent || "" : "";
      feelNote.hidden = false;
      feelNote.textContent =
        "Showing " + shown + (shown === 1 ? " experience" : " experiences") + " for " + name.toLowerCase() + ".";
    }

    /* The neighbourhood strip. A suburb filter is a statement of intent --
       somebody looking at Fremantle events is usually interested in
       Fremantle -- so the guide is offered there and nowhere else. */
    var areas = (function () {
      var el = root.querySelector("[data-wo-areas]");
      if (!el) return {};
      try { return JSON.parse(el.textContent) || {}; } catch (e) { return {}; }
    })();
    var strip = root.querySelector("[data-wo-areastrip]");
    var emptyLine = root.querySelector("[data-wo-empty-area]");
    var emptyCta = root.querySelector("[data-wo-empty-cta]");

    function paintArea(shown) {
      var area = state.suburb ? areas[state.suburb] : null;

      if (strip) {
        if (area && shown > 0) {
          strip.hidden = false;
          root.querySelector("[data-wo-area-title]").textContent = "Exploring " + area.name;
          root.querySelector("[data-wo-area-line]").textContent =
            area.places + (area.places === 1 ? " wellness place" : " wellness places") +
            " in " + area.name + ", with local day plans and how to get around.";
          var cta = root.querySelector("[data-wo-area-cta]");
          cta.href = area.url;
          cta.textContent = "Explore " + area.name;
          cta.setAttribute("data-area-slug", area.slug);
        } else {
          strip.hidden = true;
        }
      }

      /* An empty result in a suburb is not a dead end: the places are
         still there. The wording covers both reasons the list is empty --
         nothing on at all, or nothing matching the other filters. */
      if (emptyLine && emptyCta) {
        if (area && shown === 0) {
          emptyLine.hidden = false;
          emptyLine.textContent =
            "No events match that in " + area.name + " — but there are " + area.places +
            " wellness places to explore there.";
          emptyCta.hidden = false;
          emptyCta.href = area.url;
          emptyCta.textContent = "Discover " + area.name;
          emptyCta.setAttribute("data-area-slug", area.slug);
        } else {
          emptyLine.hidden = true;
          emptyCta.hidden = true;
        }
      }
    }

    function apply() {
      var shown = 0;
      $$(".wkrow", root).forEach(function (row) {
        var ok =
          (row.dataset.when || "").split(" ").indexOf(state.when) > -1 &&
          (!state.day || row.dataset.day === state.day) &&
          (!state.suburb || row.dataset.suburb === state.suburb) &&
          (!state.type || row.dataset.type === state.type) &&
          (!state.band || row.dataset.band === state.band) &&
          /* An event answers several feelings, so this is a set test, not
             an equality one -- a sound bath is both a way to slow down and
             time to yourself. */
          (!state.feel || (row.dataset.feel || "").split(" ").indexOf(state.feel) > -1);
        row.hidden = !ok;
        if (ok) shown++;
      });
      // A heading with nothing left under it disappears too — including the
      // featured band, which should not sit there empty over its own label.
      $$(".wogroup", root).forEach(function (g) {
        g.hidden = !g.querySelector(".wkrow:not([hidden])");
        // The heading's own count follows what is left under it, rather
        // than standing there claiming five when one is showing.
        var gc = g.querySelector("[data-wo-group-count]");
        if (gc) {
          var n = $$(".wkrow:not([hidden])", g).length;
          gc.textContent = (n === 1 ? gc.dataset.one : gc.dataset.many || "%d").replace("%d", n);
        }
      });
      if (feat) feat.hidden = !feat.querySelector(".wkrow:not([hidden])");
      if (empty) empty.hidden = shown > 0;
      if (countEl) {
        var tpl = shown === 0 ? countEl.dataset.none : shown === 1 ? countEl.dataset.one : countEl.dataset.many;
        countEl.textContent = (tpl || "%d").replace("%d", shown);
      }
      // The toolbar's Clear is only meaningful once something is filtered;
      // the one inside the empty state is always relevant when it shows.
      clears.forEach(function (b) {
        if (b.closest("[data-wo-toolbar]")) b.hidden = isDefault();
      });
      paintFeel(shown);
      paintArea(shown);
      paintRecs();
      if (mode === "map" && theMap) drawMap();
    }

    function set(key, value, push) {
      state[key] = value;
      syncControls();
      apply();
      writeUrl(push !== false);
      /* Only the two worth remembering. A date is about this week and a
         price is about this afternoon; where you look and how you want to
         feel hold from one visit to the next. */
      if (key === "suburb" || key === "feel") {
        if (state.suburb || state.feel) writeWoPref({ suburb: state.suburb, feel: state.feel });
        else writeWoPref(null);
      }
    }

    /* The offer, made once, above the results. */
    function offerPref() {
      var pref = woPref();
      if (!pref || (!pref.suburb && !pref.feel)) return;
      if (state.suburb || state.feel) return; // they have already chosen
      var host = root.querySelector("[data-wo-toolbar]");
      if (!host) return;

      /* Never offer a road to an empty page. Fremantle had four
         breathwork evenings last month and none this one; an invitation
         to see nothing is worse than no invitation. */
      var would = $$(".wkrow", root).filter(function (row) {
        return (!pref.suburb || row.dataset.suburb === pref.suburb) &&
          (!pref.feel || (row.dataset.feel || "").split(" ").indexOf(pref.feel) > -1);
      }).length;
      if (!would) return;

      function nameOf(sel, val) {
        var el = root.querySelector(sel + "[data-v='" + val + "']");
        if (el) return (el.querySelector(".wofeel__name") || el).textContent.trim();
        var opt = root.querySelector("option[value='" + val + "']");
        return opt ? opt.textContent.trim() : val;
      }

      /* Three sentences, not one with holes in it: "looking at in
         Fremantle" is what a joined list gives you when half of it is
         missing. */
      var feelName = pref.feel ? nameOf(".wofeel", pref.feel).toLowerCase() : "";
      var subName = pref.suburb ? nameOf("[data-no-such-thing]", pref.suburb) : "";
      var what = feelName && subName
        ? feelName + " in " + subName
        : (feelName || "events in " + subName);

      var note = document.createElement("p");
      note.className = "wopref";
      note.innerHTML =
        '<span class="wopref__text"></span>' +
        '<button class="wopref__yes" type="button"></button>' +
        '<button class="wopref__no" type="button">Forget this</button>';
      note.querySelector(".wopref__text").textContent = "Last time you were looking at " + what + ".";
      note.querySelector(".wopref__yes").textContent = "Show me those again";

      note.querySelector(".wopref__yes").addEventListener("click", function () {
        state.suburb = pref.suburb || "";
        state.feel = pref.feel || "";
        syncControls();
        apply();
        writeUrl(true);
        note.remove();
      });
      note.querySelector(".wopref__no").addEventListener("click", function () {
        writeWoPref(null);
        note.remove();
      });

      host.parentNode.insertBefore(note, host);
    }

    $$(".fchip", root).forEach(function (chip) {
      chip.addEventListener("click", function () {
        // Picking a period is a different question from picking a day.
        // Leaving both on produces an empty page and a puzzled visitor.
        state.day = "";
        set(chip.dataset.f, chip.dataset.v);
      });
    });

    $$(".wodate", root).forEach(function (btn) {
      btn.addEventListener("click", function () {
        state.when = "all";
        set("day", btn.dataset.v);
      });
    });
    /* A second press on the same tile clears it: a feeling is a mood, not
       a commitment, and there is no other way back out of one. */
    $$(".wofeel", root).forEach(function (btn) {
      btn.addEventListener("click", function () {
        set("feel", state.feel === btn.dataset.v ? "" : btn.dataset.v);
      });
    });
    $$("select[data-f]", root).forEach(function (sel) {
      sel.addEventListener("change", function () { set(sel.dataset.f, sel.value); });
    });
    clears.forEach(function (btn) {
      btn.addEventListener("click", function () {
        Object.keys(DEFAULTS).forEach(function (k) { state[k] = DEFAULTS[k]; });
        syncControls();
        apply();
        writeUrl(true);
      });
    });

    // Back and forward move between filter views rather than off the page.
    window.addEventListener("popstate", function () {
      Object.keys(DEFAULTS).forEach(function (k) { state[k] = DEFAULTS[k]; });
      readUrl();
      syncControls();
      apply();
    });

    readUrl();
    syncControls();
    apply();
    writeUrl(false);
    offerPref();
    document.addEventListener("oria:saved-events", paintRecs);
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

  /* Shop page: intentions, category chips, search, sort, the finder and the
     quick view, all over the one product grid.

     Reads the data the cards already carry, so there is no second copy of
     the catalogue in the page for the two to disagree about.

     Category and intention live in the URL (?category=singing-bowls,
     ?intent=relax) because those are views worth sharing and linking.
     Search, sort and the finder's other answers do not: they are how one
     person is looking right now, not what they found. */
  function initShopFilter() {
    var root = document.querySelector("[data-shopfilter]");
    if (!root) return;

    var grid = root.querySelector("[data-shop-grid]");
    var empty = root.querySelector("[data-shop-empty]");
    var count = root.querySelector("[data-shop-count]");
    var search = root.querySelector("[data-shop-search]");
    var clear = root.querySelector("[data-shop-clear]");
    var sort = root.querySelector("[data-shop-sort]");
    var reset = root.querySelector("[data-shop-reset]");
    var finder = root.querySelector("[data-shop-finder]");
    var products = document.getElementById("products");
    /* Only the main grid filters. Shelf cards are a fixed selection: a
       collection that emptied itself when a chip was pressed would be a
       second, confusing copy of the filter. */
    var cards = grid ? $$(".prodcard", grid) : [];
    if (!cards.length) return;

    /* Recommended is the order the engine returned: curation order. Keeping
       it means "recommended" can always be returned to, and never has to be
       reconstructed from something that only looks like it. */
    cards.forEach(function (card, i) { card.dataset.oshopOrder = String(i); });

    var state = { cat: "", intent: "", best: "", band: "", q: "", sort: "recommended" };
    var LABELS = {};
    $$("[data-intent]", root).forEach(function (t) {
      var l = t.querySelector(".intile__label");
      LABELS[t.dataset.intent] = l ? l.textContent.trim() : t.dataset.intent;
    });
    var CATS = {};
    $$(".fchip[data-cat]", root).forEach(function (c) {
      CATS[c.dataset.cat || ""] = (c.firstChild && c.firstChild.textContent || "").trim();
    });

    function num(card, key) { return parseFloat(card.dataset[key] || "0") || 0; }
    function has(card, key, val) {
      return !val || (card.dataset[key] || "").indexOf(" " + val + " ") !== -1;
    }

    function describe(shown) {
      var bits = [];
      if (state.intent && LABELS[state.intent]) bits.push(LABELS[state.intent]);
      if (state.cat && CATS[state.cat]) bits.push(CATS[state.cat]);
      if (state.best) bits.push({ beginners: "good for beginners", everyday: "everyday use", practitioner: "for practitioners", gift: "makes a gift" }[state.best] || state.best);
      if (state.band) bits.push({ "under-50": "under $50", "50-100": "$50 to $100", "100-250": "$100 to $250", "250-plus": "over $250" }[state.band] || state.band);
      if (state.q.trim()) bits.push("matching “" + state.q.trim() + "”");
      var n = shown + (shown === 1 ? " product" : " products");
      return bits.length ? n + " · " + bits.join(" · ") : "";
    }

    function apply() {
      var q = state.q.trim().toLowerCase();
      var shown = 0;

      cards.forEach(function (card) {
        /* Every category the product sits in, not just the one on the card,
           so a bowl filed under bowls and sound healing answers to both. */
        var ok = has(card, "oshopCatslugs", state.cat) &&
          has(card, "oshopIntents", state.intent) &&
          (!state.best || card.dataset.oshopBest === state.best) &&
          (!state.band || card.dataset.oshopBand === state.band) &&
          (!q || (card.dataset.oshopSearch || "").indexOf(q) !== -1);
        card.hidden = !ok;
        if (ok) shown++;
      });

      if (grid && state.sort !== "recommended") {
        var visible = cards.filter(function (c) { return !c.hidden; });
        visible.sort(function (a, b) {
          if (state.sort === "newest") {
            return num(b, "oshopProduct") - num(a, "oshopProduct");
          }
          var pa = num(a, "oshopAmount");
          var pb = num(b, "oshopAmount");
          /* A product with no price has no place in a price order. It sorts
             last either way rather than pretending to cost nothing. */
          if (!pa && !pb) return 0;
          if (!pa) return 1;
          if (!pb) return -1;
          return state.sort === "price-asc" ? pa - pb : pb - pa;
        });
        visible.forEach(function (c) { grid.appendChild(c); });
      } else if (grid) {
        cards.slice().sort(function (a, b) {
          return num(a, "oshopOrder") - num(b, "oshopOrder");
        }).forEach(function (c) { grid.appendChild(c); });
      }

      if (empty) empty.hidden = shown > 0;
      if (grid) grid.hidden = shown === 0;
      if (count) count.textContent = shown === cards.length ? "" : describe(shown);
      if (clear) clear.hidden = !state.q;
    }

    function writeUrl() {
      if (!window.history || !window.history.replaceState) return;
      var url = new URL(window.location.href);
      if (state.cat) { url.searchParams.set("category", state.cat); }
      else { url.searchParams.delete("category"); }
      if (state.intent) { url.searchParams.set("intent", state.intent); }
      else { url.searchParams.delete("intent"); }
      /* The finder's other answers arrive in the URL only from a no-script
         submit; once read they are not kept. */
      url.searchParams.delete("best");
      url.searchParams.delete("band");
      window.history.replaceState({}, "", url);
    }

    function paint() {
      $$(".fchip[data-cat]", root).forEach(function (c) {
        var on = (c.dataset.cat || "") === state.cat;
        c.classList.toggle("is-on", on);
        c.setAttribute("aria-pressed", on ? "true" : "false");
      });
      $$("[data-intent]", root).forEach(function (t) {
        var on = t.dataset.intent === state.intent;
        t.classList.toggle("is-on", on);
        t.setAttribute("aria-pressed", on ? "true" : "false");
      });
      /* Reveal this category's header, if an editor has written one. */
      $$("[data-cat-head]", root).forEach(function (h) {
        h.hidden = !state.cat || h.dataset.catHead !== state.cat;
      });
      if (finder) {
        var fi = finder.elements.intent, fb = finder.elements.best, fd = finder.elements.band;
        if (fi) fi.value = state.intent;
        if (fb) fb.value = state.best;
        if (fd) fd.value = state.band;
      }
    }

    function scrollToProducts() {
      if (!products) return;
      var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      var top = products.getBoundingClientRect().top + window.pageYOffset - (chromeTop() + 12);
      window.scrollTo({ top: Math.max(0, top), behavior: reduce ? "auto" : "smooth" });
    }

    /* A category and an intention are two answers to the same question,
       so choosing one lets go of the other. The finder's answers stay: they
       narrow whichever is chosen. */
    function selectCat(cat) {
      state.cat = cat || "";
      if (state.cat) state.intent = "";
      paint();
      writeUrl();
      apply();
    }

    function selectIntent(intent, scroll) {
      state.intent = (intent && state.intent !== intent) ? intent : "";
      if (state.intent) state.cat = "";
      paint();
      writeUrl();
      apply();
      if (scroll && state.intent) scrollToProducts();
    }

    function clearAll() {
      state.cat = ""; state.intent = ""; state.best = ""; state.band = ""; state.q = "";
      if (search) search.value = "";
      paint();
      writeUrl();
      apply();
    }

    $$(".fchip[data-cat]", root).forEach(function (chip) {
      chip.setAttribute("aria-pressed", chip.classList.contains("is-on") ? "true" : "false");
      chip.addEventListener("click", function () {
        selectCat(chip.dataset.cat || "");
        pushEvent("shop_category", { shop_category: chip.dataset.cat || "all" });
      });
    });

    $$("[data-intent]", root).forEach(function (tile) {
      tile.addEventListener("click", function () {
        selectIntent(tile.dataset.intent, true);
        pushEvent("shop_intent", { shop_intent: state.intent || "cleared" });
      });
    });

    $$("[data-shop-intent-go]", root).forEach(function (btn) {
      btn.addEventListener("click", function () {
        state.intent = "";
        selectIntent(btn.dataset.shopIntentGo, true);
        pushEvent("shop_collection", { shop_collection: btn.dataset.shopCollection || "", shop_intent: state.intent });
      });
    });

    if (search) {
      var t, lastQ = "";
      search.addEventListener("input", function () {
        clearTimeout(t);
        t = setTimeout(function () {
          state.q = search.value;
          apply();
          var q = state.q.trim();
          if (q.length >= 3 && q !== lastQ) {
            lastQ = q;
            pushEvent("shop_search", { shop_query: q.toLowerCase() });
          }
        }, 160);
      });
    }
    if (clear) {
      clear.addEventListener("click", function () {
        search.value = ""; state.q = ""; apply(); search.focus();
      });
    }
    if (sort) {
      sort.addEventListener("change", function () {
        state.sort = sort.value;
        apply();
        pushEvent("shop_sort", { shop_sort: state.sort });
      });
    }
    if (reset) {
      reset.addEventListener("click", function () {
        clearAll();
        if (search) search.focus();
      });
    }

    if (finder) {
      finder.addEventListener("submit", function (e) {
        e.preventDefault();
        state.intent = (finder.elements.intent && finder.elements.intent.value) || "";
        state.best = (finder.elements.best && finder.elements.best.value) || "";
        state.band = (finder.elements.band && finder.elements.band.value) || "";
        if (state.intent) state.cat = "";
        paint();
        writeUrl();
        apply();
        scrollToProducts();
        pushEvent("shop_finder_submit", { shop_intent: state.intent || "any", shop_best: state.best || "any", shop_band: state.band || "any" });
      });
    }

    /* Goes-well-with links: the shop handing somebody back to the directory
       is the click worth knowing about. */
    root.addEventListener("click", function (e) {
      var a = e.target.closest("[data-oshop-exp]");
      if (!a) return;
      var card = a.closest(".prodcard");
      pushEvent("shop_experience", { shop_experience: a.textContent.trim(), shop_product: card ? card.dataset.oshopName || "" : "" });
    });

    /* Quick view: one <dialog>, filled from whichever card asked for it. */
    var dialog = root.querySelector("[data-shop-qv]");
    if (dialog && typeof dialog.showModal === "function") {
      var opener = null;
      var f = function (sel) { return dialog.querySelector(sel); };
      $$("[data-oshop-qv]", root).forEach(function (b) { b.hidden = false; });

      function fill(card) {
        var d = card.dataset;
        var img = f("[data-qv-img]");
        var note = f("[data-qv-artnote]");
        if (img) {
          var pic = d.oshopImg || d.oshopArt || "";
          img.hidden = !pic;
          img.src = pic;
          img.alt = d.oshopImg ? (d.oshopName || "") : "";
          if (note) note.hidden = !!d.oshopImg || !pic;
          img.parentNode.classList.toggle("shopqv__media--art", !d.oshopImg && !!pic);
        }
        f("[data-qv-cat]").textContent = d.oshopCatname || "";
        f("[data-qv-name]").textContent = d.oshopName || "";
        var brand = f("[data-qv-brand]");
        brand.hidden = !d.oshopBrand; brand.textContent = d.oshopBrand || "";
        var tag = f("[data-qv-tag]");
        tag.hidden = !d.oshopBestlabel; tag.textContent = d.oshopBestlabel || "";
        var why = f("[data-qv-why]");
        why.hidden = !d.oshopNote; f("[data-qv-note]").textContent = d.oshopNote || "";
        var goes = f("[data-qv-goes]");
        var links = f("[data-qv-goes-links]");
        var list = [];
        try { list = JSON.parse(d.oshopGoes || "[]"); } catch (err) { list = []; }
        links.textContent = "";
        list.forEach(function (g, i) {
          if (i) links.appendChild(document.createTextNode(" · "));
          var a = document.createElement("a");
          a.href = g.url; a.textContent = g.name; a.setAttribute("data-oshop-exp", "");
          links.appendChild(a);
        });
        goes.hidden = !list.length;
        var price = f("[data-qv-price]");
        price.textContent = d.oshopPrice ? "Approx. " + d.oshopPrice : "Check current price on Amazon";
        price.classList.toggle("prodcard__price--none", !d.oshopPrice);
        var buy = f("[data-qv-buy]");
        buy.href = d.oshopUrl || "#";
        buy.setAttribute("data-oshop-click", d.oshopProduct || "");
      }

      root.addEventListener("click", function (e) {
        var b = e.target.closest("[data-oshop-qv]");
        if (!b) return;
        var card = b.closest(".prodcard");
        if (!card) return;
        opener = b;
        fill(card);
        dialog.showModal();
        var close = f("[data-shop-qv-close]");
        if (close) close.focus();
        pushEvent("shop_quickview", { shop_product: card.dataset.oshopName || "", shop_category: card.dataset.oshopCatslug || "" });
      });
      var closeBtn = f("[data-shop-qv-close]");
      if (closeBtn) closeBtn.addEventListener("click", function () { dialog.close(); });
      /* A click on the backdrop lands on the <dialog> itself. */
      dialog.addEventListener("click", function (e) { if (e.target === dialog) dialog.close(); });
      dialog.addEventListener("close", function () {
        if (opener && document.contains(opener)) opener.focus();
        opener = null;
      });
    }

    /* An incoming address only wins if the page actually offers it: a
       category chip, an intention tile, a finder answer. The category
       address (/shop/singing-bowls/) arrives as the initial category. */
    var params = new URL(window.location.href).searchParams;
    var wantedCat = root.dataset.shopInitialCat || params.get("category") || "";
    var wantedIntent = params.get("intent") || "";
    var wantedBest = params.get("best") || "";
    var wantedBand = params.get("band") || "";
    if (wantedCat && root.querySelector('.fchip[data-cat="' + CSS.escape(wantedCat) + '"]')) state.cat = wantedCat;
    if (!state.cat && wantedIntent && LABELS[wantedIntent]) state.intent = wantedIntent;
    if (finder && wantedBest && finder.elements.best && finder.elements.best.querySelector('option[value="' + CSS.escape(wantedBest) + '"]')) state.best = wantedBest;
    if (finder && wantedBand && finder.elements.band && finder.elements.band.querySelector('option[value="' + CSS.escape(wantedBand) + '"]')) state.band = wantedBand;
    paint();
    if (wantedBest || wantedBand) writeUrl();
    apply();
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
    initHoods();
    initExploreTabs();
    initAreaTracking();
    initSaveEvent();
    initSavedEventsPage();
    initClasses();
    initGuideToc();
    initSavedPage();
    paintSavedNav();
    initMe();
    /* Another tab is the same shortlist. Without this the count goes stale
       the moment somebody browses in two windows, which on a directory is
       ordinary behaviour rather than an edge case. */
    window.addEventListener("storage", function (e) {
      if (!e.key || e.key === SAVE_KEY) paintSavedNav();
    });
    initHomeSearch();
    initDirectory();
    initGoodFor();
    initRefine();
    initDirView();
    initCatMap();
    initCardQuickActions();
    initSupportBands();
    initReels();
    initTrends();
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

  /* --- Listing profile: gallery lightbox + click-to-load map ---------- */
  /* The lightbox pages through EVERY photo the listing has, not the three
     the grid shows -- which is where the rest finally go. Keyboard: Esc
     closes, arrows page. Focus returns to the image that opened it. */
  function initLightbox() {
    var host = $(".gallery[data-lightbox]");
    if (!host) return;
    var setEl = host.querySelector("[data-lightbox-set]");
    var urls = [];
    try { urls = JSON.parse(setEl ? setEl.textContent : "[]") || []; } catch (e) { urls = []; }
    if (!urls.length) return;

    var at = 0, box = null, opener = null;

    function paint() {
      var img = box.querySelector("img");
      img.src = urls[at];
      box.querySelector(".lbox__n").textContent = (at + 1) + " / " + urls.length;
    }
    function close() {
      if (!box) return;
      box.remove(); box = null;
      document.removeEventListener("keydown", keys);
      if (opener) opener.focus();
    }
    function step(d) { at = (at + d + urls.length) % urls.length; paint(); }
    function keys(e) {
      if ("Escape" === e.key) close();
      else if ("ArrowRight" === e.key) step(1);
      else if ("ArrowLeft" === e.key) step(-1);
    }
    function open(i, from) {
      at = i; opener = from;
      box = document.createElement("div");
      box.className = "lbox";
      box.setAttribute("role", "dialog");
      box.setAttribute("aria-label", "Photo viewer");
      box.innerHTML =
        '<img alt="">' +
        '<button class="lbox__x" type="button" aria-label="Close">\u00d7</button>' +
        (urls.length > 1
          ? '<button class="lbox__nav lbox__nav--prev" type="button" aria-label="Previous photo">\u2190</button>' +
            '<button class="lbox__nav lbox__nav--next" type="button" aria-label="Next photo">\u2192</button>'
          : "") +
        '<span class="lbox__n"></span>';
      document.body.appendChild(box);
      box.querySelector(".lbox__x").addEventListener("click", close);
      box.addEventListener("click", function (e) { if (e.target === box) close(); });
      var p = box.querySelector(".lbox__nav--prev"), n = box.querySelector(".lbox__nav--next");
      if (p) p.addEventListener("click", function () { step(-1); });
      if (n) n.addEventListener("click", function () { step(1); });
      document.addEventListener("keydown", keys);
      paint();
      box.querySelector(".lbox__x").focus();
    }

    host.addEventListener("click", function (e) {
      var img = e.target.closest("[data-lb]");
      if (!img) return;
      open(parseInt(img.dataset.lb, 10) || 0, img);
    });
  }

  /* The Google Maps iframe is the heaviest asset on the longest page of
     the site; it now loads when somebody asks for it. */
  function initMapFacade() {
    var btn = $(".mapfacade");
    if (!btn) return;
    btn.addEventListener("click", function () {
      var frame = document.createElement("iframe");
      frame.src = btn.dataset.mapSrc;
      frame.title = btn.dataset.mapTitle || "Map";
      frame.style.cssText = "display:block;width:100%;aspect-ratio:16/7;border:0";
      frame.setAttribute("allowfullscreen", "");
      frame.setAttribute("referrerpolicy", "no-referrer-when-downgrade");
      btn.replaceWith(frame);
    });
  }
    initDistance();

  /* The weekly timetable opens on today when it scrolls (phones). */
  function initWeek() {
    var week = $("[data-week]");
    if (!week || week.scrollWidth <= week.clientWidth + 8) return;
    var today = week.querySelector(".week__day--today");
    if (today) week.scrollLeft = Math.max(0, today.offsetLeft - week.offsetLeft - 8);
  }
    initLightbox();
    initWeek();
    initMapFacade();
  });
})();
