/* ==========================================================================
   Oria Haven — behaviour
   No framework, no build step. Every module is opt-in: it looks for its
   hook in the DOM and does nothing if the page doesn't use it.
   ========================================================================== */
(function () {
  "use strict";

  var DATA = window.ORIA_DATA || { listings: [], categories: [], regions: [] };
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var ICON = {
    pin: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 14.5s5-4.2 5-8a5 5 0 1 0-10 0c0 3.8 5 8 5 8Z"/><circle cx="8" cy="6.4" r="1.9"/></svg>',
    star: '<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>',
    arrow: '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg>',
    x: '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M2.5 2.5l7 7M9.5 2.5l-7 7"/></svg>'
  };

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
    var D = window.ORIA_DATA;
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

  function searchSuggest(raw) {
    var idx = searchIndex();
    var q = raw.trim().toLowerCase();
    if (!idx || q.length < 2) return [];
    var D = idx.D, counts = idx.counts, syn = synonymSlugs(q), out = [];

    (D.specialties || []).forEach(function (s) {
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
  function initDirectory() {
    var root = $("#dirResults");
    if (!root) return;

    var catNames = {}, regionNames = {}, suburbRegion = {}, specNames = {};
    DATA.categories.forEach(function (c) { catNames[c.id] = c.name; });
    (DATA.specialties || []).forEach(function (s) { specNames[s.id] = s.name; });
    DATA.regions.forEach(function (r) {
      regionNames[r.id] = r.name;
      r.suburbs.forEach(function (s) { suburbRegion[s.toLowerCase()] = r.id; });
    });

    var PER_PAGE = 10;
    var state = { cats: [], regions: [], spec: [], price: [], format: [], rating: 0, q: "", sort: "relevance", page: 1 };

    // Read the URL so category tiles, map regions and footer links all land
    // on a pre-filtered view — the same URLs the WordPress build will use.
    var params = new URLSearchParams(window.location.search);
    if (params.get("cat")) state.cats = params.get("cat").split(",");
    if (params.get("region")) state.regions = params.get("region").split(",");
    if (params.get("q")) state.q = params.get("q");
    if (params.get("spec")) state.spec = params.get("spec").split(",");
    if (params.get("pg")) state.page = Math.max(1, parseInt(params.get("pg"), 10) || 1);

    // Category and suburb landing pages lock one facet: the page IS the
    // filter, so it never appears as a removable chip and never hits the URL.
    var locked = {
      cat: root.dataset.cat || "",
      region: root.dataset.region || "",
      spec: root.dataset.spec || "",
      suburb: root.dataset.suburb || ""
    };
    if (locked.cat) state.cats = [locked.cat];
    if (locked.region) state.regions = [locked.region];
    if (locked.spec) state.spec = [locked.spec];

    function matches(l) {
      if (state.cats.length && state.cats.indexOf(l.cat) === -1 &&
          !(l.also || []).some(function (a) { return state.cats.indexOf(a) > -1; })) return false;
      if (state.regions.length && state.regions.indexOf(l.region) === -1) return false;
      if (locked.suburb && l.suburb !== locked.suburb) return false;
      if (state.spec.length && !(l.spec || []).some(function (s) { return state.spec.indexOf(s) > -1; })) return false;
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
      var statusBadge = l.status === "featured"
        ? '<span class="badge badge--featured"><span class="badge-dot"></span>Featured</span>'
        : l.status === "claimed"
          ? '<span class="badge badge--claimed"><span class="badge-dot"></span>Claimed</span>'
          : '<span class="badge badge--unclaimed">Unclaimed</span>';

      return '<article class="listing' + (l.status === "featured" ? " listing--featured" : "") + '">' +
        '<div class="listing__media">' +
          (l.image ? '<img src="' + esc(l.image) + '" alt="" loading="lazy"' +
            (l.image_fb && l.image_fb !== l.image
              ? " onerror=\"this.onerror=null;this.src='" + esc(l.image_fb) + "'\""
              : "") + '>' : "") +
          '<div class="listing__flag">' + statusBadge + "</div>" +
        "</div>" +
        '<div class="listing__body">' +
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
            '<span class="pill pill--sand">' + esc(catNames[l.cat] || l.cat) + "</span>" +
            (l.format !== "in-person" ? '<span class="pill">Online available</span>' : "") +
            (l.offer ? '<span class="pill" style="background:var(--gold-soft);border-color:transparent;color:#7A5A12;font-weight:700">Special offer</span>' : "") +
            (l.next ? '<span class="pill">Next: ' + esc(l.next) + "</span>" : "") +
          "</div>" +
          '<div class="listing__foot">' +
            '<span class="listing__price">' +
              (l.priceFrom > 0 ? "$" + l.priceFrom + ' <span>/ session</span>' : "&nbsp;") +
            "</span>" +
            '<a class="btn btn--sm btn--dark" href="' + esc(l.url || '#') + '">View profile<span class="btn__dot">' + ICON.arrow + "</span></a>" +
          "</div>" +
        "</div>" +
      "</article>";
    }

    function chips() {
      var box = $("#dirChips");
      if (!box) return;
      var out = [];
      state.cats.forEach(function (c) { if (c !== locked.cat) out.push(["cat", c, catNames[c] || c]); });
      state.regions.forEach(function (r) { if (r !== locked.region) out.push(["region", r, regionNames[r] || r]); });
      state.spec.forEach(function (s) { if (s !== locked.spec) out.push(["spec", s, specNames[s] || s]); });
      state.price.forEach(function (p) { out.push(["price", p, p === "Free" ? "Free" : p]); });
      state.format.forEach(function (f) { out.push(["format", f, f === "online" ? "Online" : "In person"]); });
      if (state.rating) out.push(["rating", String(state.rating), state.rating + "+ rating"]);
      if (state.q) out.push(["q", state.q, '"' + state.q + '"']);

      box.innerHTML = out.map(function (c) {
        return '<span class="chip">' + esc(c[2]) +
          '<button type="button" data-clear-kind="' + c[0] + '" data-clear-val="' + esc(c[1]) +
          '" aria-label="Remove filter ' + esc(c[2]) + '">' + ICON.x + "</button></span>";
      }).join("") + (out.length > 1
        ? '<button type="button" class="pill" id="clearAll">Clear all</button>' : "");

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
        state.cats = locked.cat ? [locked.cat] : [];
        state.regions = locked.region ? [locked.region] : [];
        state.spec = locked.spec ? [locked.spec] : [];
        state.price = []; state.format = []; state.rating = 0; state.q = "";
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
          var key = kind === "cat" ? "cats" : kind === "region" ? "regions" : kind;
          input.checked = state[key].indexOf(val) > -1;
        }
      });
    }

    // The pager lives directly under the results grid, created once so the
    // three directory templates need no markup of their own.
    var pagerBox = document.createElement("nav");
    pagerBox.className = "pager";
    pagerBox.id = "dirPager";
    pagerBox.setAttribute("aria-label", "Listing pages");
    root.parentNode.insertBefore(pagerBox, root.nextSibling);

    function goPage(n) {
      state.page = n;
      render();
      var y = root.getBoundingClientRect().top + window.pageYOffset - 130;
      window.scrollTo({ top: y, behavior: "smooth" });
    }

    function pager(pages) {
      if (pages < 2) { pagerBox.innerHTML = ""; return; }
      var out = ['<button type="button" class="pager__btn" data-page="' + (state.page - 1) + '"' +
        (state.page === 1 ? " disabled" : "") + ' aria-label="Previous page">&lsaquo;</button>'];

      // All numbers up to 7 pages; beyond that: first, current±1, last.
      var nums = [];
      for (var n = 1; n <= pages; n++) {
        if (pages <= 7 || n === 1 || n === pages || Math.abs(n - state.page) <= 1) nums.push(n);
      }
      var prev = 0;
      nums.forEach(function (n) {
        if (n - prev > 1) out.push('<span class="pager__gap" aria-hidden="true">&hellip;</span>');
        out.push('<button type="button" class="pager__num' + (n === state.page ? " is-current" : "") +
          '" data-page="' + n + '"' + (n === state.page ? ' aria-current="page"' : "") + ">" + n + "</button>");
        prev = n;
      });

      out.push('<button type="button" class="pager__btn" data-page="' + (state.page + 1) + '"' +
        (state.page === pages ? " disabled" : "") + ' aria-label="Next page">&rsaquo;</button>');
      pagerBox.innerHTML = out.join("");

      $$("button[data-page]", pagerBox).forEach(function (b) {
        b.addEventListener("click", function () { goPage(Number(b.dataset.page)); });
      });
    }

    function render() {
      var found = DATA.listings.filter(matches).sort(sortFn);
      var pages = Math.max(1, Math.ceil(found.length / PER_PAGE));
      if (state.page > pages) state.page = pages;
      var start = (state.page - 1) * PER_PAGE;
      var shown = found.slice(start, start + PER_PAGE);

      root.innerHTML = shown.length
        ? shown.map(card).join("")
        : '<div class="dir__empty"><h3 class="h3">Nothing matches those filters yet</h3>' +
          '<p class="muted" style="margin-top:.5rem">Try widening the area, or clear a filter to see more.</p></div>';

      var count = $("#dirCount");
      if (count) {
        count.innerHTML = (found.length > PER_PAGE
          ? "<b>" + (start + 1) + "&ndash;" + (start + shown.length) + "</b> of " + found.length + " listings"
          : "<b>" + found.length + "</b> of " + DATA.listings.length + " listings") +
          (state.regions.length === 1 ? " in " + esc(regionNames[state.regions[0]]) : "");
      }
      chips();
      pager(pages);

      // Keep the URL shareable and indexable-looking as filters change.
      if (locked.cat || locked.region || locked.spec || locked.suburb) return;
      var p = new URLSearchParams();
      if (state.cats.length) p.set("cat", state.cats.join(","));
      if (state.regions.length) p.set("region", state.regions.join(","));
      if (state.spec.length) p.set("spec", state.spec.join(","));
      if (state.q) p.set("q", state.q);
      if (state.page > 1) p.set("pg", String(state.page));
      var qs = p.toString();
      history.replaceState(null, "", qs ? "?" + qs : window.location.pathname);
    }

    $$("[data-filter]").forEach(function (input) {
      input.addEventListener("change", function () {
        var kind = input.dataset.filter, val = input.value;
        if (kind === "rating") {
          state.rating = input.checked ? Number(val) : 0;
        } else {
          var key = kind === "cat" ? "cats" : kind === "region" ? "regions" : kind;
          if (input.checked) { if (state[key].indexOf(val) === -1) state[key].push(val); }
          else { state[key] = state[key].filter(function (x) { return x !== val; }); }
        }
        state.page = 1;
        render();
      });
    });

    var sortSel = $("#dirSort");
    if (sortSel) sortSel.addEventListener("change", function () { state.sort = sortSel.value; state.page = 1; render(); });

    var qInput = $("#dirQ");
    if (qInput) {
      qInput.value = state.q;
      var t;
      qInput.addEventListener("input", function () {
        clearTimeout(t);
        t = setTimeout(function () { state.q = qInput.value.trim(); state.page = 1; render(); }, 180);
      });
    }

    var filterToggle = $("#filterToggle");
    if (filterToggle) {
      filterToggle.addEventListener("click", function () {
        var panel = $("#dirFilters");
        var open = panel.classList.toggle("is-collapsed");
        filterToggle.setAttribute("aria-expanded", open ? "false" : "true");
      });
    }

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

  /* --- Analytics beacon ------------------------------------------------ */
  /* Contact taps on listing profiles report to the site's own counter —
     one POST, no cookies, nothing personal. */
  function initTracking() {
    if (!window.ORIA_TRACK || !navigator.sendBeacon) return;
    document.addEventListener("click", function (e) {
      var el = e.target.closest && e.target.closest("[data-oria-track]");
      if (!el) return;
      var id = parseInt(el.getAttribute("data-oria-id"), 10);
      var type = el.getAttribute("data-oria-track");
      if (!id || !type) return;
      try {
        navigator.sendBeacon(
          ORIA_TRACK.url,
          new Blob([JSON.stringify({ id: id, type: type })], { type: "application/json" })
        );
      } catch (err) { /* counting must never break a tap */ }
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

  document.addEventListener("DOMContentLoaded", function () {
    initTracking();
    initNav();
    initAccordions();
    initPullquote();
    initReveal();
    initMap();
    initNiceSelects();
    initSiteSearch();
    initHomeSearch();
    initDirectory();
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
  });
})();
