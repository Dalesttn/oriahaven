/**
 * The goal-first map prototype. Self-contained: no dependency on app.js
 * beyond Leaflet already being on the page.
 *
 * Chips are the directory's own thirteen wellness goals, with their own
 * colours out of goodfor.json. Nothing is stored on a listing for this page
 * and nothing needed retagging: a goal is derived from the specialties a
 * listing already has.
 */
(function () {
  "use strict";

  var root = document.getElementById("wmap");
  if (!root) return;

  var dataEl = root.querySelector("[data-wmap-data]");
  var goalEl = root.querySelector("[data-wmap-goals-map]");
  if (!dataEl || !goalEl) return;

  var ROWS, GOALS;
  try {
    ROWS = JSON.parse(dataEl.textContent || "[]");
    GOALS = JSON.parse(goalEl.textContent || "{}");   // slug -> {label, colour}
  } catch (e) { return; }

  var centreEl = root.querySelector("[data-wmap-centre]");
  var CENTRE = "the centre";
  try { if (centreEl) CENTRE = JSON.parse(centreEl.textContent) || CENTRE; } catch (e) {}

  var listEl = root.querySelector("[data-wmap-list]");
  var countEl = root.querySelector("[data-wmap-count]");
  var mapEl = root.querySelector("[data-wmap-map]");

  var state = { goals: [], dist: 0, order: "", beginners: false };  // goals hold slugs

  /* Which goals the chosen moods add up to. No mood selected means no goal
     filter at all — the map opens showing everything rather than nothing. */
  /* Rows carry goal LABELS; chips carry slugs. One lookup keeps them honest. */
  function labelOf(slug) { return (GOALS[slug] && GOALS[slug].label) || ""; }
  function colourOf(slug) { return (GOALS[slug] && GOALS[slug].colour) || "#54707E"; }

  /* Rows store goal labels; the palette is keyed by slug. One reverse map
     rather than a lookup loop every time a tag is drawn. */
  var BY_LABEL = {};
  Object.keys(GOALS).forEach(function (slug) {
    BY_LABEL[GOALS[slug].label] = { slug: slug, colour: GOALS[slug].colour };
  });

  /* Which goals to show against a place.
     A listing answers to as many as thirteen, and printing all of them is
     noise wearing a rainbow. When something is selected these are the goals
     that ARE the answer -- the same ones that decide the pin's colour, so a
     tag and its pin always agree. With nothing selected the row still has to
     say something, so it shows the first couple it happens to carry. */
  function tagsFor(r, max) {
    var out = [];
    if (state.goals.length) {
      state.goals.forEach(function (slug) {
        if (r.g.indexOf(labelOf(slug)) > -1) out.push(labelOf(slug));
      });
    } else {
      out = r.g.slice(0, 2);
    }
    return out.slice(0, max || 3);
  }

  function tagRow(r, max) {
    var labels = tagsFor(r, max);
    if (!labels.length) return null;
    var wrap = document.createElement("span");
    wrap.className = "wmtags";
    labels.forEach(function (lab) {
      var meta = BY_LABEL[lab];
      var t = document.createElement("span");
      t.className = "wmtag";
      t.style.setProperty("--gf", meta ? meta.colour : "#54707E");
      t.textContent = lab;
      wrap.appendChild(t);
    });
    return wrap;
  }

  function wantedGoals() {
    return state.goals.map(labelOf).filter(Boolean);
  }

  /* A place answers to several goals at once, so the pin follows the first
     goal the visitor ASKED for that it matches. Nothing selected means no
     claim to make: the map stays one neutral colour. */
  function pinColour(r) {
    for (var i = 0; i < state.goals.length; i++) {
      if (r.g.indexOf(labelOf(state.goals[i])) > -1) return colourOf(state.goals[i]);
    }
    return "#5A6B62";
  }

  function matches(r, goals) {
    if (state.beginners && !r.b) return false;
    if (state.dist && (r.km === null || r.km > state.dist)) return false;
    if (!goals.length) return true;
    for (var i = 0; i < r.g.length; i++) {
      if (goals.indexOf(r.g[i]) > -1) return true;
    }
    return false;
  }

  function ordered(rows) {
    var out = rows.slice();
    if (state.order === "quiet") {
      // Unscored places sort last rather than vanishing.
      out.sort(function (a, b) {
        if (a.q === null && b.q === null) return (a.km || 0) - (b.km || 0);
        if (a.q === null) return 1;
        if (b.q === null) return -1;
        return b.q - a.q || (a.km || 0) - (b.km || 0);
      });
    } else if (state.order === "social") {
      out.sort(function (a, b) {
        if (a.s === null && b.s === null) return (a.km || 0) - (b.km || 0);
        if (a.s === null) return 1;
        if (b.s === null) return -1;
        return b.s - a.s || (a.km || 0) - (b.km || 0);
      });
    } else {
      out.sort(function (a, b) { return (a.km === null ? 1e9 : a.km) - (b.km === null ? 1e9 : b.km); });
    }
    return out;
  }

  /* --------------------------------------------------------------- map -- */

  var map = null, layer = null;
  var markers = [];          // parallel to the currently drawn results
  var hovered = -1;
  var hits = [];             // what the filters currently allow
  var hideTimer = null;      // grace period before a card closes
  var popupOpen = false;     // a card is on screen
  var listDirty = false;     // the view moved while it was

  var PIN = { r: 6, w: 2 };
  var PIN_ON = { r: 10, w: 3 };

  /* The card shown on hover. Built on demand rather than up front: 377
     popups eagerly constructed would mean 377 image requests for pictures
     nobody has asked to see. */
  function card(r) {
    var box = document.createElement("div");
    box.className = "wmcard";

    if (r.img) {
      var im = document.createElement("img");
      im.className = "wmcard__img";
      im.src = r.img;
      im.alt = "";
      im.loading = "lazy";
      im.decoding = "async";
      box.appendChild(im);
    }

    var body = document.createElement("div");
    body.className = "wmcard__body";

    var h = document.createElement("strong");
    h.className = "wmcard__name";
    h.textContent = r.t;
    body.appendChild(h);

    var meta = document.createElement("span");
    meta.className = "wmcard__meta";
    var bits = [];
    if (r.c) bits.push(r.c);
    if (r.sb) bits.push(r.sb);
    if (r.km !== null) bits.push(r.km + " km");
    meta.textContent = bits.join("  \u00b7  ");
    body.appendChild(meta);

    var tags = tagRow(r, 2);
    if (tags) body.appendChild(tags);

    var a = document.createElement("a");
    a.className = "wmcard__view";
    a.href = r.u;
    a.textContent = "View";
    body.appendChild(a);

    /* Google Places photos may only be shown with their author credited, and
       places.php says so in as many words. No attribution, no credit line --
       which means the image is the site's own generic scene. */
    if (r.att) {
      var cr = document.createElement("small");
      cr.className = "wmcard__credit";
      cr.textContent = "Photo: " + r.att;
      body.appendChild(cr);
    }

    box.appendChild(body);
    return box;
  }

  function initMap() {
    if (!window.L || !mapEl || map) return;
    map = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true });
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    layer = L.layerGroup().addTo(map);
    map.setView([-31.9523, 115.8613], 11);

    /* Panning and zooming re-list -- but not while a card is open.
       On a phone you open a card, then drag the map to see where the place
       actually is. Re-listing mid-drag rebuilds the panel and closes the
       card you were reading, so the map fights the person using it. The move
       is remembered instead and applied the moment the card is dismissed.
       moveend, not move: once the gesture settles is when the answer is
       worth recomputing. */
    map.on("moveend zoomend", function () {
      if (popupOpen) { listDirty = true; return; }
      renderList();
    });

    map.on("popupopen", function () { popupOpen = true; });

    map.on("popupclose", function () {
      popupOpen = false;
      resetPin();
      if (listDirty) {
        listDirty = false;
        renderList();
      }
    });
  }

  function drawMap(rows) {
    if (!map || !layer) return;
    layer.clearLayers();
    markers = [];
    hovered = -1;
    popupOpen = false;
    listDirty = false;
    var pts = [];

    rows.forEach(function (r, i) {
      if (!r.la || !r.lo) { markers[i] = null; return; }
      var m = L.circleMarker([r.la, r.lo], {
        radius: PIN.r, weight: PIN.w,
        color: "#ffffff", fillColor: pinColour(r), fillOpacity: 0.95
      });

      /* A function, so the card and its image are built the first time the
         popup opens. autoPan off: running down the list must not make the
         map lurch under the cursor. */
      m.bindPopup(function () { return card(r); }, {
        autoPan: false, closeButton: false, offset: [0, -4], className: "wmpop"
      });

      // Map -> list, so the pairing reads both ways.
      m.on("mouseover", function () { highlight(i, false); });
      m.on("mouseout", scheduleClear);

      m.addTo(layer);
      markers[i] = m;
      pts.push([r.la, r.lo]);
    });

    /* animate:false is not a style choice.
       Animated, this silently did nothing: called in the same tick as the
       initial setView, Leaflet kept zoom 11 and 49 of 377 places sat outside
       the viewport while the panel claimed "328 of 377 in view". Unanimated
       it fits properly -- and it also means getBounds() is correct on the
       very next line, so the list can be built without waiting for moveend.
       Refitting is a jump to a new set of results, not a journey. */
    if (pts.length) {
      map.fitBounds(pts, { padding: [30, 30], maxZoom: 14, animate: false });
    }
  }

  /* --------------------------------------------------------- hovering -- */

  /* The card must survive the journey to it.
     Moving the cursor from a row towards the popup leaves the row, which
     fired mouseleave and shut the card before anyone could reach "View".
     So closing is deferred, and entering the card itself cancels it. */
  function scheduleClear() {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(clearHighlight, 260);
  }
  function holdOpen() {
    clearTimeout(hideTimer);
  }

  function highlight(i, fromList) {
    clearTimeout(hideTimer);
    if (i === hovered) return;
    clearHighlight();
    hovered = i;

    var m = markers[i];
    if (m) {
      m.setStyle({ radius: PIN_ON.r, weight: PIN_ON.w });
      /* 182 of the listings sit on a suburb centroid rather than a street
         address, so a dozen pins can share one point exactly. Without this
         the one being pointed at stays buried under the others. */
      if (m.bringToFront) m.bringToFront();
      m.openPopup();

      var el = m.getPopup() && m.getPopup().getElement();
      if (el && !el.__wired) {
        el.__wired = true;
        el.addEventListener("mouseenter", holdOpen);
        el.addEventListener("mouseleave", scheduleClear);
      }
    }

    var li = listEl.querySelector('[data-i="' + i + '"]');
    if (li) {
      li.classList.add("is-hot");
      if (!fromList && li.scrollIntoView) {
        li.scrollIntoView({ block: "nearest" });
      }
    }
  }

  /* Idempotent, because Leaflet's own popupclose calls it too -- tapping the
     map background dismisses a card without going through clearHighlight. */
  function resetPin() {
    if (hovered < 0) return;
    var m = markers[hovered];
    if (m) m.setStyle({ radius: PIN.r, weight: PIN.w });
    var li = listEl.querySelector('[data-i="' + hovered + '"]');
    if (li) li.classList.remove("is-hot");
    hovered = -1;
  }

  function clearHighlight() {
    clearTimeout(hideTimer);
    if (hovered < 0) return;
    var m = markers[hovered];
    if (m) m.closePopup();     // fires popupclose, which resets the pin
    resetPin();
  }

  /* -------------------------------------------------------------- paint -- */

  /* Is this row inside what the map is currently showing? Before the map
     exists everything counts as visible, so the list is never empty while
     Leaflet is still starting up. */
  function inView(r) {
    if (!map || !r.la || !r.lo) return true;
    return map.getBounds().contains([r.la, r.lo]);
  }

  /* How far this place is from the middle of what you are looking at, in
     metres. Not the same question as r.km, which is its distance from the
     city centre -- that is a fact about the place and stays in the meta
     line. This is a fact about the view, and it changes as you move. */
  function fromCentre(r) {
    if (!map || !r.la || !r.lo) return Infinity;
    try {
      return map.distance(map.getCenter(), [r.la, r.lo]);
    } catch (e) {
      return Infinity;
    }
  }

  function paint() {
    var goals = wantedGoals();
    hits = ordered(ROWS.filter(function (r) { return matches(r, goals); }));
    drawMap(hits);        // fits the map to the new set, which re-lists
    renderList();
  }

  /* The list is the map's legend, so it shows what the map shows. Zoom into
     Fremantle and the names on the right are Fremantle's -- otherwise the
     panel is answering a question the visitor stopped asking two zoom levels
     ago. The filters still decide WHICH places exist; the viewport only
     decides which of them are on screen. */
  function renderList() {
    var vis = [];
    hits.forEach(function (r, i) {
      if (inView(r)) vis.push({ r: r, i: i });
    });

    /* Middle of the view first, edges last.
       Zoom into Fremantle and the first name should be in Fremantle, not
       whichever match happens to sit closest to the CBD. Only "Nearest" is
       re-ordered -- picking "Quietest first" is an explicit instruction and
       silently overruling it with geography would be worse than useless.
       Those two keep their own order and get centre distance as the
       tie-break, so equal scores still read middle-outwards. */
    if (state.order === "") {
      vis.sort(function (a, b) { return fromCentre(a.r) - fromCentre(b.r); });
    } else {
      vis.sort(function (a, b) {
        return (a.i - b.i) || (fromCentre(a.r) - fromCentre(b.r));
      });
    }

    var where = state.dist ? " within " + state.dist + " km of " + CENTRE : "";
    if (vis.length === hits.length) {
      countEl.textContent = (hits.length === 1 ? "1 place" : hits.length + " places") + where;
    } else {
      countEl.textContent = vis.length + " of " + hits.length + " in view"
        + (hits.length === 1 ? "" : "") + where;
    }

    listEl.textContent = "";
    vis.slice(0, 60).forEach(function (pair) {
      var r = pair.r, i = pair.i;
      var li = document.createElement("li");
      li.className = "wmap__item";
      li.setAttribute("data-i", String(i));

      /* Pointer for a mouse, focus for a keyboard. Touch gets neither and
         does not need them -- the link still works, which is the point. */
      li.addEventListener("mouseenter", function () { highlight(i, true); });
      li.addEventListener("mouseleave", scheduleClear);
      li.addEventListener("focusin", function () { highlight(i, true); });
      li.addEventListener("focusout", scheduleClear);

      var a = document.createElement("a");
      a.href = r.u;
      a.className = "wmap__link";
      a.textContent = r.t;
      li.appendChild(a);

      var meta = document.createElement("span");
      meta.className = "wmap__meta";
      var bits = [];
      if (r.c) bits.push(r.c);
      if (r.sb) bits.push(r.sb);
      if (r.km !== null) bits.push(r.km + " km");
      meta.textContent = bits.join("  ·  ");
      li.appendChild(meta);

      var rowTags = tagRow(r, 3);
      if (rowTags) li.appendChild(rowTags);

      if (r.b) {
        var b = document.createElement("span");
        b.className = "wmap__flag";
        b.textContent = "Beginner friendly";
        li.appendChild(b);
      }
      listEl.appendChild(li);
    });

    if (vis.length > 60) {
      var more = document.createElement("li");
      more.className = "wmap__more";
      more.textContent = "and " + (vis.length - 60) + " more in view";
      listEl.appendChild(more);
    }
    if (!vis.length) {
      var none = document.createElement("li");
      none.className = "wmap__more";
      none.textContent = hits.length
        ? "Nothing in view. Zoom out, or pan the map."
        : "Nothing matches that yet. Try a wider distance, or another chip.";
      listEl.appendChild(none);
    }
  }

  /* Per-mood counts, honest about the current distance and beginner state
     so a chip never promises more than tapping it delivers. */
  function paintCounts() {
    root.querySelectorAll("[data-goal]").forEach(function (btn) {
      var label = labelOf(btn.getAttribute("data-goal"));
      var n = ROWS.filter(function (r) {
        if (state.beginners && !r.b) return false;
        if (state.dist && (r.km === null || r.km > state.dist)) return false;
        return r.g.indexOf(label) > -1;
      }).length;
      var el = btn.querySelector("[data-goal-count]");
      if (el) el.textContent = String(n);
      // A chip that would empty the map says so before it is tapped.
      btn.classList.toggle("is-empty", n === 0);
    });
  }

  /* ------------------------------------------------------------ events -- */

  root.querySelectorAll("[data-goal]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var g = btn.getAttribute("data-goal");
      var i = state.goals.indexOf(g);
      if (i > -1) state.goals.splice(i, 1); else state.goals.push(g);
      btn.setAttribute("aria-pressed", i > -1 ? "false" : "true");
      btn.classList.toggle("is-on", i === -1);
      var row = root.querySelector("[data-wmap-goals]");
      if (row) row.classList.toggle("has-choice", state.goals.length > 0);
      paint();
      revealResults();
    });
  });

  /* On a phone the chips fill the screen and the map is below the fold, so
     tapping one looks like nothing happened. Bring the answer into view.
     Only on narrow screens -- on a desktop both are already visible and
     yanking the page would be rude -- and only when the results are actually
     off-screen, so a second tap does not re-scroll a map you are looking at. */
  function revealResults() {
    if (window.innerWidth > 900) return;
    if (!countEl) return;
    var top = countEl.getBoundingClientRect().top;
    if (top >= 0 && top < window.innerHeight * 0.5) return;
    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    countEl.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
  }

  function segGroup(sel, apply) {
    root.querySelectorAll(sel).forEach(function (btn) {
      btn.addEventListener("click", function () {
        btn.parentNode.querySelectorAll(".segbtn").forEach(function (b) { b.classList.remove("is-on"); });
        btn.classList.add("is-on");
        apply(btn);
        paintCounts();
        paint();
      });
    });
  }

  segGroup("[data-dist]", function (b) { state.dist = Number(b.getAttribute("data-dist")) || 0; });
  segGroup("[data-order]", function (b) { state.order = b.getAttribute("data-order") || ""; });

  var beg = root.querySelector("[data-beginners]");
  if (beg) {
    beg.addEventListener("change", function () {
      state.beginners = beg.checked;
      paintCounts();
      paint();
    });
  }

  initMap();
  paintCounts();
  paint();
})();
