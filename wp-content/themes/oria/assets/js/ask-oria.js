/**
 * Reads a sentence into preferences, shows the reading, lists the places.
 *
 * The chips are the search, not a summary of it. Once someone corrects one,
 * the sentence is not consulted again — re-reading it would overwrite the
 * correction with the same reading that was wrong the first time. So a chip
 * click posts the preferences straight back and skips extraction entirely.
 *
 * Nothing here writes prose about a place. The only sentence on the page
 * that is not the site's own is a reviewer's, quoted and credited.
 */
(function () {
  "use strict";

  var root = document.getElementById("askoria");
  if (!root) return;

  /* The hero and the results are separate sections now, so anything below
     the fold is looked up against the document rather than the hero. */
  var out = document.querySelector("[data-ask-results]");
  if (!out) return;

  var form = root.querySelector("[data-ask-form]");
  var input = root.querySelector("[data-ask-input]");
  var go = root.querySelector("[data-ask-go]");
  var readEl = out.querySelector("[data-ask-read]");
  var chipsEl = out.querySelector("[data-ask-chips]");
  var healthEl = out.querySelector("[data-ask-health]");
  var statusEl = out.querySelector("[data-ask-status]");
  var listEl = out.querySelector("[data-ask-list]");
  var vibeEl = out.querySelector("[data-ask-vibe]");
  var vibeRow = out.querySelector("[data-ask-vibe-row]");
  var mapWrap = out.querySelector("[data-ask-map-wrap]");
  var mapEl = out.querySelector("[data-ask-map]");
  var planEl = out.querySelector("[data-ask-plan]");
  var planLink = out.querySelector("[data-ask-plan-link]");
  var guideEl = root.querySelector("[data-ask-guide]");
  var guideIntro = root.querySelector("[data-ask-guide-intro]");
  var guideQ = root.querySelector("[data-ask-guide-q]");
  var guideOpts = root.querySelector("[data-ask-guide-opts]");
  var guideBack = root.querySelector("[data-ask-guide-back]");
  var noIdea = root.querySelector("[data-ask-noidea]");
  var oriaEl = root.querySelector("[data-oria]");
  var sayEl = root.querySelector("[data-oria-say]");

  var ENDPOINT = "/wp-json/oria/v1/ask";
  var busy = false;
  var prefs = null;
  var names = {};   // kind slug -> the words a person reads

  /* ---------------------------------------------------------------- Oria --

     What she says, per state. Every line is "we" or "let's" — the writing
     schema forbids first-person singular, and nothing here needs it.

     No line comments on a place. She reacts to what the PAGE is doing, and
     the moment she starts saying things like "this one looks perfect for
     you" she has become an adviser with an opinion about a business.

     The greeting deliberately does not ask how anyone is feeling. Asked
     that, people answer it honestly — "anxious, and my back hurts" — and
     the only reply this box can give is a refusal, which makes the site
     look like it asked a question it did not want answered. Asking what
     someone is AFTER points at the same search without opening that door. */
  var LINES = {
    idle:      ["What are you after today?"],
    listening: ["Take your time.", "In your own words is fine."],
    thinking:  ["Ok, let's see what we can find…",
                "Right — let's have a look.",
                "Reading that properly…"],
    happy:     ["Here's what we found.",
                "These look like a match.",
                "A few worth a look."],
    empty:     ["Nothing matched all of that.",
                "That's a narrow one — try loosening a chip."],
    quiet:     ["That one's better asked of a GP."],
    limited:   ["That's three for today — the chips still work."],
    error:     ["That didn't go through."]
  };

  function feel(state) {
    if (!oriaEl) return;
    oriaEl.setAttribute("data-state",
      state === "limited" || state === "error" ? "empty" : state);
    var lines = LINES[state] || LINES.idle;
    sayEl.textContent = lines[Math.floor(Math.random() * lines.length)];
  }

  /* Each cycling chip names its states and how to word them. "any" is shown
     rather than hidden: a chip that says "Effort: either" is an invitation to
     set it, where an absent chip is just something the reader never learns
     exists. */
  var CYCLES = [
    { key: "effort", label: "Effort",
      order: ["any", "gentle", "active"],
      words: { any: "either", gentle: "gentle", active: "active" } },
    { key: "social", label: "Company",
      order: ["any", "quiet", "people"],
      words: { any: "either", quiet: "keep it quiet", people: "around people" } },
    { key: "budget", label: "Budget",
      order: ["any", "mid", "free"],
      words: { any: "no limit", mid: "modest", free: "free only" } },
    /* Numeric, so its "unset" is 0 rather than the string "any" — hence the
       flag. Without it `prefs.max_km || "any"` turns a real 0 into "any" and
       the chip can never be cycled back to no limit. */
    { key: "max_km", label: "Distance", numeric: true,
      order: [0, 2, 5, 10, 25],
      words: { 0: "anywhere", 2: "within 2 km", 5: "within 5 km",
               10: "within 10 km", 25: "within 25 km" } }
  ];

  /* Each nudges one preference and re-runs the search. One-way on purpose:
     the chips above are how you take a nudge back, and offering two ways to
     undo the same thing makes neither obvious. */
  var VIBES = [
    { label: "Relaxing",   apply: function (p) { p.effort = "gentle"; p.social = "quiet"; } },
    { label: "Social",     apply: function (p) { p.social = "people"; } },
    { label: "Physical",   apply: function (p) { p.effort = "active"; } },
    { label: "Affordable", apply: function (p) { p.budget = p.budget === "mid" ? "free" : "mid"; } },
    { label: "Closer",     apply: function (p) {
        var steps = [0, 25, 10, 5, 2];
        var i = steps.indexOf(p.max_km);
        p.max_km = steps[Math.min(i + 1, steps.length - 1)];
      } },
    { label: "Beginner friendly", apply: function (p) { p.beginner = true; } },
    { label: "Less spiritual",    apply: function (p) { p.skip_spirit = true; } }
  ];

  var TOGGLES = [
    { key: "beginner", on: "New to this", off: "Not new to this" },
    { key: "skip_spirit", on: "Skipping the spiritual side", off: "Spiritual side included" }
  ];

  /* --------------------------------------------------------------- guide --

     "I don't know what I need" answered without a questionnaire and without
     a single model call: three short questions, each one setting a
     preference, decided entirely here.

     Free matters more than it sounds. A conversational version costing a
     model call per step would spend a visitor's whole daily allowance before
     they saw one place — and this answers instantly, which no round-trip
     does.

     Every question asks what someone wants the session to be LIKE. Not one
     asks how they are feeling: asked that, people answer honestly, and the
     only reply this page can give a health answer is a refusal. */
  var GUIDE = [
    {
      q: "What sounds most appealing right now?",
      opts: [
        { label: "Quiet",        note: "Somewhere to switch off.",          set: function (p) { p.social = "quiet"; p.effort = "gentle"; } },
        { label: "Movement",     note: "Something to get stuck into.",      set: function (p) { p.effort = "active"; } },
        { label: "Connection",   note: "Being around other people.",        set: function (p) { p.social = "people"; } },
        { label: "Hands-on",     note: "An hour where someone else works.", set: function (p) { p.goals = ["Hands-on care"]; } },
        { label: "Something new", note: "Anything I have not tried.",       set: function (p) { p.beginner = true; } }
      ]
    },
    {
      q: "How far are you willing to go?",
      opts: [
        { label: "Keep it close",   note: "Within about 5 km of town.",  set: function (p) { p.max_km = 5; } },
        { label: "A short drive",   note: "Up to about 10 km.",          set: function (p) { p.max_km = 10; } },
        { label: "Anywhere",        note: "Distance is not the issue.",  set: function (p) { p.max_km = 0; } }
      ]
    },
    {
      q: "And what should it cost?",
      opts: [
        { label: "Free",        note: "Only what costs nothing.",   set: function (p) { p.budget = "free"; } },
        { label: "Keep it modest", note: "Nothing expensive.",      set: function (p) { p.budget = "mid"; } },
        { label: "No limit",    note: "Price is not the issue.",    set: function (p) { p.budget = "any"; } }
      ]
    }
  ];

  var guideStep = -1;
  var guidePrefs = null;

  function blankPrefs() {
    return { kinds: [], goals: [], effort: "any", social: "any", budget: "any",
             max_km: 0, beginner: false, skip_spirit: false, health: false };
  }

  function startGuide() {
    guidePrefs = blankPrefs();
    guideStep = 0;
    guideEl.hidden = false;
    guideIntro.textContent = "That is completely fine — let's work it out together.";
    feel("listening");
    drawGuide();
    guideEl.scrollIntoView({ block: "nearest", behavior: prefersReduce() ? "auto" : "smooth" });
  }

  function prefersReduce() {
    return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function drawGuide() {
    var step = GUIDE[guideStep];
    guideQ.textContent = step.q;
    guideOpts.textContent = "";
    guideBack.hidden = guideStep === 0;

    step.opts.forEach(function (o) {
      var b = el("button", "askguide__opt");
      b.type = "button";
      b.appendChild(el("span", "askguide__optlabel", o.label));
      b.appendChild(el("span", "askguide__optnote", o.note));
      b.addEventListener("click", function () {
        o.set(guidePrefs);
        guideStep += 1;
        if (guideStep < GUIDE.length) {
          drawGuide();
          guideIntro.textContent = "";
        } else {
          guideEl.hidden = true;
          guideStep = -1;
          prefs = guidePrefs;
          /* Straight to the chips path: guided answers are already
             preferences, so there is no sentence left to read. */
          reask();
        }
      });
      guideOpts.appendChild(b);
    });
  }

  /* ------------------------------------------------------------ helpers -- */

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function setStatus(msg) {
    statusEl.textContent = msg || "";
  }

  /* ------------------------------------------------------------ the ask -- */

  function post(body) {
    if (busy) return;
    busy = true;
    go.disabled = true;
    feel("thinking");
    setStatus("Searching…");

    var headers = { "Content-Type": "application/json" };
    /* Identifies a logged-in admin to the endpoint, which exempts them from
       the daily cap. Absent for everyone else, which is fine — the route is
       public and the nonce is not an access check. */
    if (window.ORIA_ASK && ORIA_ASK.nonce) headers["X-WP-Nonce"] = ORIA_ASK.nonce;

    fetch(ENDPOINT, {
      method: "POST",
      credentials: "same-origin",
      headers: headers,
      body: JSON.stringify(body)
    })
      .then(function (r) {
        if (!r.ok) throw new Error(r.status === 429 ? "429" : "http");
        return r.json();
      })
      .then(render)
      .catch(function (e) {
        if (e.message === "429") {
          /* The chips still work — they never touch the model. Leave the
             results standing rather than clearing a page that is still
             usable. */
          feel("limited");
          setStatus("That is three questions for today. The chips above still work, and the whole directory is at /explore/.");
          return;
        }
        feel("error");
        setStatus("That did not go through. Try again in a moment.");
        listEl.textContent = "";
      })
      .then(function () {
        busy = false;
        go.disabled = false;
      });
  }

  function ask(text) {
    if (!text.trim()) {
      setStatus("Type something first, or pick one of the examples.");
      return;
    }
    post({ q: text.trim() });
  }

  function reask() {
    post({ prefs: prefs });
  }

  /* ----------------------------------------------------------- the read -- */

  function chipButton(text, on, onClick) {
    var b = el("button", "askchip" + (on ? " is-on" : ""), text);
    b.type = "button";
    b.addEventListener("click", onClick);
    return b;
  }

  function drawChips() {
    chipsEl.textContent = "";

    /* What they came for, first — it is the strongest filter on the page,
       so it should be the first thing anyone checks we got right. */
    prefs.kinds.forEach(function (slug) {
      var b = chipButton((names[slug] || slug) + " ×", true, function () {
        prefs.kinds = prefs.kinds.filter(function (x) { return x !== slug; });
        drawChips();
        reask();
      });
      b.setAttribute("aria-label", "Remove " + (names[slug] || slug));
      chipsEl.appendChild(b);
    });

    /* Goals are removable, not cyclable — there are thirteen of them and a
       chip that cycles through thirteen states is a puzzle, not a control. */
    prefs.goals.forEach(function (g) {
      var b = chipButton(g + " ×", true, function () {
        prefs.goals = prefs.goals.filter(function (x) { return x !== g; });
        drawChips();
        reask();
      });
      b.setAttribute("aria-label", "Remove " + g);
      chipsEl.appendChild(b);
    });

    CYCLES.forEach(function (c) {
      var cur = c.numeric ? (prefs[c.key] || 0) : (prefs[c.key] || "any");
      var isSet = c.numeric ? cur !== 0 : cur !== "any";
      var b = chipButton(c.label + ": " + c.words[cur], isSet, function () {
        var i = c.order.indexOf(cur);
        prefs[c.key] = c.order[(i + 1) % c.order.length];
        drawChips();
        reask();
      });
      chipsEl.appendChild(b);
    });

    TOGGLES.forEach(function (t) {
      var on = !!prefs[t.key];
      var b = chipButton(on ? t.on : t.off, on, function () {
        prefs[t.key] = !prefs[t.key];
        drawChips();
        reask();
      });
      b.setAttribute("aria-pressed", on ? "true" : "false");
      chipsEl.appendChild(b);
    });

    readEl.hidden = false;
  }

  function drawVibes() {
    if (!vibeRow) return;
    vibeRow.textContent = "";
    VIBES.forEach(function (v) {
      var b = el("button", "askvibe__btn", v.label);
      b.type = "button";

      /* A nudge with nowhere left to go is disabled rather than left looking
         live. "Closer" at the tightest band re-ran the whole search and
         changed nothing, which reads as a broken button. Tested by applying
         it to a copy and seeing whether anything actually moves. */
      var trial = JSON.parse(JSON.stringify(prefs));
      v.apply(trial);
      if (JSON.stringify(trial) === JSON.stringify(prefs)) {
        b.disabled = true;
        b.title = "Already as " + v.label.toLowerCase() + " as we can make it";
      } else {
        b.addEventListener("click", function () {
          v.apply(prefs);
          drawChips();
          reask();
        });
      }
      vibeRow.appendChild(b);
    });
    vibeEl.hidden = false;
  }

  /* -------------------------------------------------------------- places -- */

  /* Read directly rather than asking app.js, which keeps its shortlist
     private. Same key, and wrapped because storage throws outright in some
     privacy modes rather than returning null. */
  function isSaved(id) {
    try {
      var raw = window.localStorage.getItem("oria_saved");
      return !!raw && JSON.parse(raw).indexOf(String(id)) > -1;
    } catch (e) { return false; }
  }

  function metaLine(m) {
    var bits = [];
    if (m.cat) bits.push(m.cat);
    if (m.suburb) bits.push(m.suburb);
    bits.push(m.band ? (m.band === "free" ? "free" : m.band) : "price not published");
    /* Already phrased by Geo\label() as "In the CBD" or "3.1 km from the
       CBD". A bare "3.1 km" reads as the distance from wherever the reader
       happens to be standing, and nobody has told us where that is. */
    /* Unless the suburb already said it. "Perth CBD  ·  $$  ·  In the
       CBD" is the same fact twice and the repeat reads as a bug. Only the
       "In X" form can be a duplicate; "3.1 km from the CBD" is a different
       fact and always survives. The article has to come off before the
       comparison, because "Perth CBD" does not contain "the CBD". */
    if (m.where) {
      var near = /^In\s+(?:the\s+)?(.+)$/i.exec(m.where);
      var dup = false;
      if (near && m.suburb) {
        var place = near[1].toLowerCase();
        var sub = m.suburb.toLowerCase();
        dup = sub.indexOf(place) > -1 || place.indexOf(sub) > -1;
      }
      if (!dup) bits.push(m.where);
    }
    return bits.join("  ·  ");
  }

  function card(m, i) {
    var li = el("li", "askitem");
    li.style.setProperty("--i", i);

    if (m.img) {
      var media = el("div", "askitem__media");
      var img = document.createElement("img");
      img.src = m.img;
      img.alt = "";               // the link beside it already names the place
      img.loading = "lazy";
      img.decoding = "async";
      /* Places photo references expire, and a dead one would leave a broken
         image glyph in a row of good cards. Same fallback the site's own
         listing cards use. */
      if (m.scene) {
        img.addEventListener("error", function () {
          img.onerror = null;
          img.src = m.scene;
        });
      }
      media.appendChild(img);

      /* Google's terms require the photographer's name to travel with the
         photo. Only set when the picture came from Places — a business's own
         upload owes nobody a credit. */
      if (m.credit) media.appendChild(el("span", "askitem__credit", m.credit));
      li.appendChild(media);
    }

    var body = el("div", "askitem__body");

    var head = el("div", "askitem__head");
    var a = el("a", "askitem__link", m.title);
    a.href = m.url;
    head.appendChild(a);

    /* Words, never a percentage — and never colour alone, which is why the
       band is spelled out rather than shown as a green dot on its own. */
    if (m.fit) {
      var fit = el("span", "askfit" + (m.fit === "Strong match" ? " askfit--strong" : ""), m.fit);
      head.appendChild(fit);
    }
    body.appendChild(head);

    body.appendChild(el("span", "askitem__meta", metaLine(m)));

    /* Why this one came back. Every chip is a fact about the room — what it
       offers, how busy, what it costs — never a claim about what it does. */
    if (m.reasons && m.reasons.length) {
      var why = el("div", "askwhy");
      m.reasons.forEach(function (r) { why.appendChild(el("span", "askwhy__chip", r)); });
      body.appendChild(why);
    }

    if (m.quote && m.quote.text) {
      var fig = el("figure", "askquote");
      fig.appendChild(el("blockquote", "askquote__text", "\u201c" + m.quote.text + "\u201d"));
      var cap = el("figcaption", "askquote__by");
      cap.textContent = (m.quote.author || "A Google user") + " \u00b7 Google review";
      fig.appendChild(cap);
      body.appendChild(fig);
    }

    var actions = el("div", "askitem__actions");

    var cta = el("a", "askitem__cta", "Explore \u2192");
    cta.href = m.url;
    actions.appendChild(cta);

    /* The site's own device shortlist, not a second one. app.js listens for
       [data-card-save] on the document, so a card rendered here behaves
       exactly like a card rendered anywhere else — same key, same analytics
       event, and a listing saved here shows up on /saved/. */
    if (m.id) {
      var save = el("button", "askitem__save", "Save");
      save.type = "button";
      save.setAttribute("data-card-save", String(m.id));
      save.setAttribute("aria-pressed", isSaved(m.id) ? "true" : "false");
      save.setAttribute("aria-label", "Save " + m.title);
      if (isSaved(m.id)) save.textContent = "Saved";
      /* app.js owns the storage and flips aria-pressed from its own document
         listener; this only mirrors the result into the word, so the state
         never rides on the fill colour alone. Deferred a tick because both
         listeners answer the same click and app.js runs second. */
      save.addEventListener("click", function () {
        setTimeout(function () {
          save.textContent = save.getAttribute("aria-pressed") === "true" ? "Saved" : "Save";
        }, 0);
      });
      actions.appendChild(save);
    }

    body.appendChild(actions);

    li.appendChild(body);
    return li;
  }

  var map = null;
  var pins = null;

  /* Second to the list, and it stays second: the answer is the places, and
     this is that answer opening out into the city. Only drawn when Leaflet
     actually loaded and something has coordinates — a blank grey rectangle
     is worse than no map. */
  function drawMap(matches) {
    if (!mapEl || typeof window.L === "undefined") return;

    var placed = matches.filter(function (m) { return m.lat !== null && m.lng !== null; });
    if (!placed.length) { mapWrap.hidden = true; return; }

    mapWrap.hidden = false;

    if (!map) {
      map = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true });
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      }).addTo(map);
      pins = L.layerGroup().addTo(map);
    }
    pins.clearLayers();

    var pts = [];
    placed.forEach(function (m) {
      var mk = L.circleMarker([m.lat, m.lng], {
        radius: 8, weight: 2, color: "#0E3B38", fillColor: "#7FA48E", fillOpacity: .9
      });
      /* Escaped, because a business name is content and this is innerHTML. */
      mk.bindPopup(
        '<strong>' + esc(m.title) + "</strong><br>" +
        esc([m.cat, m.suburb].filter(Boolean).join(" \u00b7 ")) +
        '<br><a href="' + esc(m.url) + '">View</a>'
      );
      mk.addTo(pins);
      pts.push([m.lat, m.lng]);
    });

    /* Unanimated: fitBounds in the same tick as a fresh map silently does
       nothing when it animates, which left pins off-screen on the mood map. */
    map.invalidateSize();
    map.fitBounds(pts, { padding: [30, 30], maxZoom: 14, animate: false });
  }

  function esc(t) {
    return String(t).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  /* ---- carry the reading over to the week planner ----------------------- */

  function planHref() {
    var q = [];
    if (prefs.effort !== "any") q.push("effort=" + prefs.effort);
    if (prefs.social !== "any") q.push("social=" + prefs.social);
    if (prefs.budget !== "any") q.push("budget=" + prefs.budget);
    if (prefs.beginner) q.push("new=1");
    if (prefs.skip_spirit) q.push("skipspirit=1");
    return planLink.getAttribute("href").split("?")[0] + (q.length ? "?" + q.join("&") : "");
  }

  function render(d) {
    prefs = d.understood;
    names = d.names || {};
    listEl.textContent = "";

    if (prefs.health) {
      readEl.hidden = true;
      healthEl.hidden = false;
      if (vibeEl) vibeEl.hidden = true;
      if (mapWrap) mapWrap.hidden = true;
      if (planEl) planEl.hidden = true;
      feel("quiet");
      setStatus("");
      return;
    }
    healthEl.hidden = true;

    drawChips();

    if (!d.matches.length) {
      if (mapWrap) mapWrap.hidden = true;
      if (planEl) planEl.hidden = true;
      drawVibes();          // the way out of an empty result is to loosen one
      feel("empty");
      setStatus("Nothing in the directory matches all of that. Loosen one of the chips above and it will fill.");
      return;
    }

    feel("happy");
    d.matches.forEach(function (m, i) { listEl.appendChild(card(m, i)); });
    drawVibes();
    drawMap(d.matches);
    if (planEl) {
      planEl.hidden = false;
      planLink.setAttribute("href", planHref());
    }
    reveal();

    var n = d.matches.length;
    setStatus(n + (n === 1 ? " place" : " places")
      + (prefs.by === "chips" ? "" : (prefs.note ? "  ·  read as: " + prefs.note : "")));
  }

  /* On a phone the hero fills the screen and the answer lands below it, so
     without this a search looks like nothing happened. Only when the results
     are actually off-screen, so a second search does not yank a list the
     reader is already looking at. */
  function reveal() {
    var top = out.getBoundingClientRect().top;
    if (top >= 0 && top < window.innerHeight * 0.6) return;
    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    out.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
  }

  /* -------------------------------------------------------------- events -- */

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    ask(input.value);
  });

  /* Enter sends, Shift+Enter breaks the line — the convention everywhere
     else a person types a sentence into a box. */
  /* One row until it needs more, then it grows to fit and stops at a third
     of the viewport so the button never leaves the screen. */
  function autogrow() {
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, window.innerHeight * 0.33) + "px";
  }
  input.addEventListener("input", function () {
    if (guideEl && !guideEl.hidden) { guideEl.hidden = true; guideStep = -1; }
  });
  input.addEventListener("input", autogrow);
  window.addEventListener("resize", autogrow);
  autogrow();

  feel("idle");

  /*
   * Arriving from the front page band, which is a plain GET form carrying
   * the sentence and nothing else. The reading happens here, once — that is
   * the whole point of handing the text over rather than extracting it
   * there, where it would have spent one of the visitor's three daily
   * readings before they ever saw this page.
   *
   * The value is put in the box first so the visitor can see what was
   * carried across and edit it, rather than watching results appear for a
   * sentence they can no longer read.
   */
  (function seedFromUrl() {
    var q;
    try { q = new URLSearchParams(window.location.search).get("q"); } catch (e) { return; }
    if (!q) return;
    input.value = q.slice(0, 400);
    autogrow();
    ask(input.value);
  })();

  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      ask(input.value);
    }
  });

  input.addEventListener("focus", function () {
    if (oriaEl.getAttribute("data-state") === "idle") feel("listening");
  });
  input.addEventListener("blur", function () {
    if (oriaEl.getAttribute("data-state") === "listening" && !input.value.trim()) feel("idle");
  });

  if (noIdea) {
    noIdea.addEventListener("click", function () {
      if (guideEl.hidden) { startGuide(); } else { guideEl.hidden = true; guideStep = -1; feel("idle"); }
    });
  }
  if (guideBack) {
    guideBack.addEventListener("click", function () {
      if (guideStep > 0) { guideStep -= 1; drawGuide(); }
    });
  }

  root.querySelectorAll("[data-ask-example]").forEach(function (b) {
    b.addEventListener("click", function () {
      input.value = b.getAttribute("data-ask-example");
      autogrow();
      input.focus();
      ask(input.value);
    });
  });
})();
