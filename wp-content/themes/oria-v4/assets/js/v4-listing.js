/* ==========================================================================
   Oria Haven v4 -- listing page behaviour (single-listing.php).

   Enqueued by oria-v4/functions.php on single listings, deferred, footer.
   Everything here is an enhancement: without it the page shows every
   service card, the review form inline in two visible blocks, today's hours
   as the server saw them, and a permanently visible phone action bar.

   1. Hours today   -- today's line and "open now" in the site's timezone,
                       from Google's own week and periods (the HTML can be
                       served from a page cache on a later day).
   2. Services      -- four cards, the rest behind "Show all services".
   3. Review form   -- folded behind "Write a review": an inline panel on a
                       wide screen, a full-screen dialog sheet on a phone;
                       focus in, Escape out, focus back. Two steps.
   4. Phone bar     -- shown once the hero's actions scroll away; hidden at
                       the footer, over a form, and while the sheet is open.

   Save, compare, lightbox, star widget and map facade stay with the parent's
   app.js; nothing here touches their hooks.
   ========================================================================== */
(function () {
  "use strict";

  var doc = document;
  var root = doc.documentElement;
  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }

  /* --- 1. Hours today ------------------------------------------------- */

  function initHours() {
    var el = doc.getElementById("xp-hours-data");
    if (!el) return;
    var data;
    try { data = JSON.parse(el.textContent || "{}"); } catch (e) { return; }
    var week = data.week || [];
    var periods = data.periods || [];
    if (!week.length) return;

    var days = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
    var nowDay, nowMin;
    try {
      var parts = new Intl.DateTimeFormat("en-US", {
        timeZone: data.tz || undefined, weekday: "long", hour: "numeric", minute: "numeric", hourCycle: "h23"
      }).formatToParts(new Date());
      var got = {};
      parts.forEach(function (p) { got[p.type] = p.value; });
      nowDay = days.indexOf(got.weekday);
      nowMin = (parseInt(got.hour, 10) % 24) * 60 + parseInt(got.minute, 10);
    } catch (e) { return; }
    if (nowDay < 0 || isNaN(nowMin)) return;

    var todayName = days[nowDay];
    var todayLine = "";
    week.forEach(function (line) {
      if (!todayLine && line.toLowerCase().indexOf(todayName.toLowerCase()) === 0) {
        todayLine = line.replace(/^[^:]+:\s*/, "");
      }
    });

    function fmt(h, m) {
      var ap = h >= 12 && h < 24 ? "pm" : "am";
      var h12 = h % 12 === 0 ? 12 : h % 12;
      return h12 + (m ? ":" + (m < 10 ? "0" : "") + m : "") + " " + ap;
    }

    // Google periods: day 0 = Sunday; a 24/7 place is one open point with no close.
    var WEEK = 7 * 1440, now = nowDay * 1440 + nowMin, state = null, until = "", opensAt = null;
    if (periods.length) {
      state = "closed";
      periods.forEach(function (p) {
        var o = p.open, c = p.close;
        if (!o) return;
        if (!c) { state = "always"; return; }
        var a = (o.day || 0) * 1440 + (o.hour || 0) * 60 + (o.minute || 0);
        var b = (c.day || 0) * 1440 + (c.hour || 0) * 60 + (c.minute || 0);
        if (b <= a) b += WEEK;
        if ((now >= a && now < b) || (now + WEEK >= a && now + WEEK < b)) {
          if (state !== "always") { state = "open"; until = fmt(c.hour || 0, c.minute || 0); }
        } else if ((o.day || 0) === nowDay && a > now && (opensAt === null || a < opensAt.a)) {
          opensAt = { a: a, t: fmt(o.hour || 0, o.minute || 0) };
        }
      });
    }

    var shortTxt, longTxt;
    if (state === "always") {
      shortTxt = "Open 24 hours";
      longTxt = "Today: open 24 hours";
    } else if (state === "open") {
      shortTxt = "Open now · until " + until;
      longTxt = "Today: " + todayLine + " · open now";
    } else if (state === "closed") {
      shortTxt = opensAt ? "Closed now · opens " + opensAt.t : (/closed/i.test(todayLine) ? "Closed today" : "Closed for today");
      longTxt = "Today: " + (todayLine || "closed") + " · closed now";
    } else {
      shortTxt = todayLine ? "Today " + todayLine : "";
      longTxt = todayLine ? "Today: " + todayLine : "";
    }

    $$("[data-xp-today]").forEach(function (n) {
      if (!shortTxt) return;
      n.textContent = shortTxt;
      n.setAttribute("data-open", state === "open" || state === "always" ? "1" : "0");
    });
    $$("[data-xp-today-long]").forEach(function (n) { if (longTxt) n.textContent = longTxt; });
    $$("[data-xp-today-short]").forEach(function (n) {
      if (todayLine) n.textContent = state === "open" ? todayLine + " (open now)" : todayLine;
    });
    // The week list marks the right day even when the page was cached yesterday.
    $$(".xp-loc__week li").forEach(function (li) {
      li.classList.toggle("is-today", li.textContent.toLowerCase().indexOf(todayName.toLowerCase()) === 0);
    });
  }

  /* --- 2. Services: four, then "Show all services" ---------------------- */

  function initServices() {
    var list = $("[data-xp-svc]");
    var btn = $("[data-xp-svc-all]");
    if (!list || !btn) return;
    var extras = $$("[data-xp-extra]", list);
    if (!extras.length) return;
    var moreLabel = btn.textContent.trim();
    var lessLabel = btn.getAttribute("data-less") || "Show fewer services";

    function set(open) {
      extras.forEach(function (li) { li.hidden = !open; });
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      btn.textContent = open ? lessLabel : moreLabel;
    }
    set(false);
    btn.hidden = false;
    btn.addEventListener("click", function () {
      var open = btn.getAttribute("aria-expanded") !== "true";
      set(open);
      if (open) {
        var first = extras[0].querySelector("a");
        if (first) first.focus();
      }
    });
  }

  /* --- 3. Review form: inline panel / phone sheet, and its two steps ---- */

  var sheetOpen = false;
  var onSheetChange = function () {};

  function initReviewSteps(panel) {
    var form = $("[data-review-steps]", panel);
    if (!form) return;
    var s1 = $('[data-review-step="1"]', form);
    var s2 = $('[data-review-step="2"]', form);
    var nav = $("[data-review-nav]", form);
    var next = $("[data-review-next]", form);
    var back = $("[data-review-back]", form);
    if (!s1 || !s2 || !nav || !next) return;

    $$("[data-review-stepno]", form).forEach(function (n) { n.hidden = false; });
    nav.hidden = false;
    if (back) back.hidden = false;
    s2.hidden = true;

    function stepOneValid() {
      var fields = $$("input, select, textarea", s1);
      for (var i = 0; i < fields.length; i++) {
        var f = fields[i];
        if (f.willValidate && !f.checkValidity()) {
          if (f.reportValidity) f.reportValidity();
          try { f.focus(); } catch (e) { /* hidden select behind the star widget */ }
          return false;
        }
      }
      return true;
    }
    function toStep(n) {
      s1.hidden = n !== 1;
      s2.hidden = n !== 2;
      var head = $("[data-review-stepno]", n === 1 ? s1 : s2);
      if (n === 2 && head) {
        head.setAttribute("tabindex", "-1");
        head.focus();
      } else if (n === 1) {
        var first = $("select, input:not([type=hidden])", s1);
        if (first) try { first.focus(); } catch (e) { /* ignore */ }
      }
    }

    next.addEventListener("click", function () { if (stepOneValid()) toStep(2); });
    if (back) back.addEventListener("click", function () { toStep(1); });

    // Enter inside step 1 must not submit past a required field the visitor cannot see.
    form.addEventListener("submit", function (e) {
      if (!s2.hidden) return;
      var sub = e.submitter;
      if (sub && s1.contains(sub)) return; // a signed-in member sending from step 1
      e.preventDefault();
      if (stepOneValid()) toStep(2);
    });
  }

  function initReviews() {
    var panel = $("[data-xp-rvpanel]");
    var opener = $("[data-xp-rvopen]");
    if (!panel || !opener) return;
    var closer = $("[data-xp-rvclose]", panel);
    var title = doc.getElementById("write-review-title");
    var phone = window.matchMedia ? window.matchMedia("(max-width: 40rem)") : { matches: false };
    var isOpen = !panel.hidden;

    initReviewSteps(panel);

    function focusables() {
      return $$('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]):not([tabindex="-1"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', panel)
        .filter(function (n) { return n.offsetParent !== null || n === doc.activeElement; });
    }

    function open() {
      panel.hidden = false;
      isOpen = true;
      opener.setAttribute("aria-expanded", "true");
      if (phone.matches) {
        sheetOpen = true;
        panel.classList.add("is-sheet");
        panel.setAttribute("role", "dialog");
        panel.setAttribute("aria-modal", "true");
        if (title) panel.setAttribute("aria-labelledby", "write-review-title");
        root.classList.add("xp-sheet-open");
        panel.scrollTop = 0;
        onSheetChange();
      }
      var target = title || focusables()[0];
      if (target) {
        if (!target.hasAttribute("tabindex")) target.setAttribute("tabindex", "-1");
        target.focus(sheetOpen ? { preventScroll: true } : undefined);
        if (!sheetOpen) {
          panel.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
        }
      }
    }

    function close() {
      panel.hidden = true;
      isOpen = false;
      opener.setAttribute("aria-expanded", "false");
      if (sheetOpen) {
        sheetOpen = false;
        panel.classList.remove("is-sheet");
        panel.removeAttribute("role");
        panel.removeAttribute("aria-modal");
        panel.removeAttribute("aria-labelledby");
        root.classList.remove("xp-sheet-open");
        onSheetChange();
      }
      opener.focus();
    }

    opener.addEventListener("click", function () { if (isOpen) close(); else open(); });
    if (closer) closer.addEventListener("click", close);

    doc.addEventListener("keydown", function (e) {
      if (!isOpen) return;
      if (e.key === "Escape" && (sheetOpen || panel.contains(doc.activeElement))) {
        e.preventDefault();
        close();
        return;
      }
      // The sheet is modal: Tab cycles inside it.
      if (e.key === "Tab" && sheetOpen) {
        var f = focusables();
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && (doc.activeElement === first || !panel.contains(doc.activeElement))) {
          e.preventDefault(); last.focus();
        } else if (!e.shiftKey && (doc.activeElement === last || !panel.contains(doc.activeElement))) {
          e.preventDefault(); first.focus();
        }
      }
    });

    // Rotating a phone to landscape (or widening a window) turns the sheet back into the inline panel.
    if (phone.addEventListener) {
      phone.addEventListener("change", function () {
        if (sheetOpen && !phone.matches) {
          sheetOpen = false;
          panel.classList.remove("is-sheet");
          panel.removeAttribute("role");
          panel.removeAttribute("aria-modal");
          root.classList.remove("xp-sheet-open");
          onSheetChange();
        }
      });
    }

    // Back from Google sign-in (#write-review), or a link that asks for the form.
    if (!isOpen && location.hash === "#write-review") open();
    if (isOpen) opener.setAttribute("aria-expanded", "true");
  }

  /* --- 4. Phone action bar -------------------------------------------- */

  function initBar() {
    var bar = doc.getElementById("xp-bar");
    if (!bar || !("IntersectionObserver" in window)) return; // no observer: the bar just stays, as without script
    var decide = $("[data-xp-decide]") || $("[data-xp-hero]");
    var foot = $("footer.foot") || $("footer");
    var pastHero = false, footIn = false, formsIn = 0, typing = false;

    bar.classList.add("xp-bar--js");

    function update() {
      var on = pastHero && !footIn && !formsIn && !typing && !sheetOpen;
      bar.classList.toggle("is-on", on);
    }
    onSheetChange = update;

    if (decide) {
      new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          pastHero = !en.isIntersecting && en.boundingClientRect.top < 0;
        });
        update();
      }).observe(decide);
    }
    if (foot) {
      // Steps aside a little before the footer arrives.
      new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { footIn = en.isIntersecting; });
        update();
      }, { rootMargin: "0px 0px 160px 0px" }).observe(foot);
    }
    // Never sits over a form's fields or its submit button.
    var seen = new WeakMap();
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        var was = seen.get(en.target) || false;
        if (en.isIntersecting !== was) {
          formsIn += en.isIntersecting ? 1 : -1;
          seen.set(en.target, en.isIntersecting);
        }
      });
      formsIn = Math.max(0, formsIn);
      update();
    });
    $$(".xp-page form").forEach(function (f) { io.observe(f); });

    doc.addEventListener("focusin", function (e) {
      var t = e.target;
      typing = !!(t && t.matches && t.matches("input, textarea, select") && !bar.contains(t));
      update();
    });
    doc.addEventListener("focusout", function () {
      setTimeout(function () {
        var t = doc.activeElement;
        typing = !!(t && t.matches && t.matches("input, textarea, select") && !bar.contains(t));
        update();
      }, 0);
    });
    update();
  }

  /* The sand skeleton stops shimmering once its photo has arrived. */
  function initShots() {
    $$(".xp-hero__shot img").forEach(function (img) {
      var done = function () { img.parentNode.classList.add("is-loaded"); };
      if (img.complete && img.naturalWidth) done();
      else img.addEventListener("load", done, { once: true });
    });
  }

  function init() {
    initShots();
    initHours();
    initServices();
    initReviews();
    initBar();
  }

  if (doc.readyState === "loading") doc.addEventListener("DOMContentLoaded", init);
  else init();
})();
