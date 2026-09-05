/**
 * Builds a seven-day plan from preferences and real listings.
 *
 * Every reason it prints describes a room — how long, how many people, what
 * it costs. None of them says a session helps, eases or improves anything,
 * because the data cannot support that and the site's own rules forbid it.
 */
(function () {
  "use strict";

  var root = document.getElementById("planner");
  if (!root) return;

  function readJSON(sel, fallback) {
    var el = root.querySelector(sel);
    if (!el) return fallback;
    try { return JSON.parse(el.textContent); } catch (e) { return fallback; }
  }

  var ROWS = readJSON("[data-plan-rows]", []);
  var WEEK = readJSON("[data-plan-week]", []);
  var PALETTE = readJSON("[data-plan-palette]", {});

  var weekEl = root.querySelector("[data-week]");
  var sumEl = root.querySelector("[data-plan-summary]");

  var pref = { effort: "any", social: "any", budget: "any", spirit: true, beginner: false };

  /* Arriving from /ask/ with a reading already made. Only values this file
     already understands are accepted -- anything else in the query string is
     ignored rather than trusted, since a URL is typed by anyone. */
  (function seedFromUrl() {
    var q;
    try { q = new URLSearchParams(window.location.search); } catch (e) { return; }
    var oneOf = function (name, allowed) {
      var v = q.get(name);
      return allowed.indexOf(v) > -1 ? v : null;
    };
    var e = oneOf("effort", ["gentle", "active"]);
    var s = oneOf("social", ["quiet", "people"]);
    var b = oneOf("budget", ["mid", "free"]);
    if (e) pref.effort = e;
    if (s) pref.social = s;
    if (b) pref.budget = b;
    if (q.get("new") === "1") pref.beginner = true;
    if (q.get("skipspirit") === "1") pref.spirit = false;
  })();

  /* The controls have to show what was carried over, or the plan looks like
     it ignored the preferences the visitor just set next door. */
  function syncControls() {
    [["data-effort", "effort"], ["data-social", "social"], ["data-budget", "budget"]].forEach(function (pair) {
      root.querySelectorAll("[" + pair[0] + "]").forEach(function (btn) {
        btn.classList.toggle("is-on", btn.getAttribute(pair[0]) === pref[pair[1]]);
      });
    });
    var sk = root.querySelector("[data-skip-spirit]");
    if (sk) sk.checked = !pref.spirit;
    var nw = root.querySelector("[data-new]");
    if (nw) nw.checked = pref.beginner;
  }

  var BANDS = { "Free": 0, "$": 1, "$$": 2, "$$$": 3, "$$$$": 4 };

  /* ---------------------------------------------------------- matching -- */

  function allowed(r) {
    if (!pref.spirit && r.sp) return false;
    if (pref.beginner && !r.b) return false;

    if (pref.budget === "free" && String(r.pb).toLowerCase() !== "free") return false;
    if (pref.budget === "mid") {
      var b = BANDS[r.pb];
      if (b === undefined || b > 2) return false;   // unpriced is not "modest"
    }

    /* Effort and company use the DNA scores where they exist. A listing the
       registry cannot place is not excluded — it simply cannot be sorted by
       them, and 35% of the directory has no scores. Dropping those would
       quietly shrink the site by a third. */
    if (pref.effort === "gentle" && r.i !== null && r.i > 3) return false;
    if (pref.effort === "active" && r.i !== null && r.i < 3) return false;
    if (pref.social === "quiet" && r.s !== null && r.s > 3) return false;
    if (pref.social === "people" && r.s !== null && r.s < 3) return false;

    return true;
  }

  function score(r) {
    var s = 0;
    if (r.b) s += 1;                       // a beginner-friendly room is a safer pick
    if (r.km !== null) s -= r.km / 40;      // mild pull towards the middle
    if (r.pb) s += 0.4;                     // publishing a band is worth something
    return s;
  }

  /* --------------------------------------------------------- rendering -- */

  /* Bands only. See the note in the template: price_from means a session on
     one listing and a whole retreat on another, so a figure here would be
     confidently wrong rather than usefully precise. */
  function priceLine(r) {
    if (!r.pb) return "price not published";
    return r.pb === "Free" ? "free" : r.pb;
  }

  function pick(goals, used, n) {
    var out = [];
    var pool = ROWS.filter(function (r) {
      if (used[r.u]) return false;
      if (!allowed(r)) return false;
      for (var i = 0; i < goals.length; i++) {
        if (r.g.indexOf(goals[i]) > -1) return true;
      }
      return false;
    });
    pool.sort(function (a, b) { return score(b) - score(a); });
    pool.slice(0, n).forEach(function (r) { used[r.u] = true; out.push(r); });
    return out;
  }

  function tag(label) {
    var t = document.createElement("span");
    t.className = "wmtag";
    t.style.setProperty("--gf", PALETTE[label] || "#54707E");
    t.textContent = label;
    return t;
  }

  function build() {
    var used = {};
    var placed = 0;
    weekEl.textContent = "";

    WEEK.forEach(function (d) {
      var li = document.createElement("li");
      li.className = "day";

      var h = document.createElement("h3");
      h.className = "day__name";
      h.textContent = d.day;
      li.appendChild(h);

      if (!d.goals.length) {
        var rest = document.createElement("p");
        rest.className = "day__note";
        rest.textContent = d.note;
        li.appendChild(rest);
        li.classList.add("day--rest");
        weekEl.appendChild(li);
        return;
      }

      var picks = pick(d.goals, used, 3);

      var tags = document.createElement("span");
      tags.className = "wmtags";
      d.goals.forEach(function (g) { tags.appendChild(tag(g)); });
      li.appendChild(tags);

      var note = document.createElement("p");
      note.className = "day__note";
      note.textContent = d.note;
      li.appendChild(note);

      if (!picks.length) {
        var none = document.createElement("p");
        none.className = "day__none";
        none.textContent = "Nothing in the directory matches this day with those settings. Loosen one and it will fill.";
        li.appendChild(none);
      } else {
        placed += picks.length;
        var ul = document.createElement("ul");
        ul.className = "day__picks";
        picks.forEach(function (r) {
          var pli = document.createElement("li");

          var a = document.createElement("a");
          a.href = r.u;
          a.className = "day__link";
          a.textContent = r.t;
          pli.appendChild(a);

          var meta = document.createElement("span");
          meta.className = "day__meta";
          var bits = [];
          if (r.c) bits.push(r.c);
          if (r.sb) bits.push(r.sb);
          bits.push(priceLine(r));
          if (r.km !== null) bits.push(r.km + " km");
          meta.textContent = bits.join("  ·  ");
          pli.appendChild(meta);

          if (r.b) {
            var b = document.createElement("span");
            b.className = "wmap__flag";
            b.textContent = "Beginner friendly";
            pli.appendChild(b);
          }
          ul.appendChild(pli);
        });
        li.appendChild(ul);
      }

      weekEl.appendChild(li);
    });

    var said = [];
    if (pref.effort !== "any") said.push(pref.effort === "gentle" ? "gentle" : "active");
    if (pref.social !== "any") said.push(pref.social === "quiet" ? "quiet" : "with people");
    if (pref.budget === "free") said.push("free only");
    if (pref.budget === "mid") said.push("modest budget");
    if (!pref.spirit) said.push("no spiritual side");
    if (pref.beginner) said.push("beginner friendly");

    sumEl.textContent = placed + " places across six days"
      + (said.length ? "  ·  " + said.join(", ") : "")
      + (pref.beginner ? "  ·  only 25 listings are tagged beginner friendly, so this narrows hard" : "");
  }

  /* ------------------------------------------------------------ events -- */

  function seg(attr, key) {
    root.querySelectorAll("[" + attr + "]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        btn.parentNode.querySelectorAll(".segbtn").forEach(function (b) { b.classList.remove("is-on"); });
        btn.classList.add("is-on");
        pref[key] = btn.getAttribute(attr);
        build();
      });
    });
  }
  seg("data-effort", "effort");
  seg("data-social", "social");
  seg("data-budget", "budget");

  var skip = root.querySelector("[data-skip-spirit]");
  if (skip) skip.addEventListener("change", function () { pref.spirit = !skip.checked; build(); });

  var nw = root.querySelector("[data-new]");
  if (nw) nw.addEventListener("change", function () { pref.beginner = nw.checked; build(); });

  var go = root.querySelector("[data-plan-go]");
  if (go) {
    go.addEventListener("click", function () {
      build();
      if (window.innerWidth <= 900 && weekEl.firstChild) {
        var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        weekEl.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
      }
    });
  }

  syncControls();
  build();
})();
