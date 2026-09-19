/*
 * v4 front page behaviour. Everything here is an enhancement: without it
 * the page is complete -- the Calm picture, a plain Ask Oria form, every
 * link crawlable.
 *
 *  - Mood chips and the three styled dropdowns (What would help? / Where
 *    are you? / When?): the choices become the sentence Ask Oria receives
 *    on submit. Chips swap the hero picture and stay in step with the
 *    first dropdown.
 *    Only Calm loads with the page; the rest arrive once it is idle, or on
 *    the first tap of their chip.
 *  - Perth Reset: hovering or focusing a stop shows its picture.
 *  - Four ways in (phones): previous / next buttons for the swipe row.
 */
(function () {
  "use strict";

  /* --- The concierge: chips, dropdowns and the Ask Oria sentence ------ */
  var hero = document.querySelector("[data-xh-hero]");
  if (hero) {
    var chips = hero.querySelectorAll("[data-feel]");
    var pics = hero.querySelectorAll("[data-feel-pic]");
    var form = hero.querySelector("[data-xh-concierge]");
    var help = hero.querySelector("select[data-xh-help]");
    var where = hero.querySelector("select[data-xh-where]");
    var when = hero.querySelector("select[data-xh-when]");
    var q = hero.querySelector("[data-xh-q]");
    var say = chips.length ? chips[0].getAttribute("data-feel-say") : "";
    var dds = {};

    // The sentence Ask Oria gets: what would help, where, when --
    // "Somewhere calm to switch off in Fremantle & South this weekend".
    var compose = function () {
      var parts = [say];
      if (where && where.value) parts.push("in " + where.value);
      if (when && when.value && when.value !== "any time") parts.push(when.value);
      if (q) q.value = parts.join(" ");
    };
    if (form) form.addEventListener("submit", compose);

    var load = function (img) {
      if (!img || !img.dataset.src) return;
      if (img.dataset.srcset) img.srcset = img.dataset.srcset;
      img.src = img.dataset.src;
      delete img.dataset.src;
      delete img.dataset.srcset;
    };
    var loadAll = function () { pics.forEach(load); };
    // After the hero has painted, fetch the rest quietly.
    window.addEventListener("load", function () {
      if ("requestIdleCallback" in window) {
        window.requestIdleCallback(loadAll, { timeout: 4000 });
      } else {
        setTimeout(loadAll, 2000);
      }
    });

    var pickFeel = function (feel) {
      chips.forEach(function (c) {
        var on = c.getAttribute("data-feel") === feel;
        c.setAttribute("aria-pressed", on ? "true" : "false");
        if (on) say = c.getAttribute("data-feel-say") || say;
      });
      pics.forEach(function (p) {
        var on = p.getAttribute("data-feel-pic") === feel;
        if (on) load(p);
        p.classList.toggle("is-on", on);
      });
      if (dds.help) dds.help.set(feel, true);
      compose();
    };
    chips.forEach(function (chip) {
      chip.addEventListener("click", function () { pickFeel(chip.getAttribute("data-feel")); });
    });

    /*
     * The styled dropdowns: a button that opens a listbox. Arrow keys move,
     * Enter or Space chooses, Escape closes, Home and End jump. The native
     * <select> stays in the page, hidden, as the single source of the value.
     */
    var openDd = null;
    hero.querySelectorAll("[data-xh-dd]").forEach(function (box) {
      var kind = box.getAttribute("data-xh-dd");
      var btn = box.querySelector(".xh-dd__btn");
      var list = box.querySelector(".xh-dd__list");
      var value = box.querySelector(".xh-dd__value");
      var native = box.querySelector(".xh-dd__native");
      var opts = Array.prototype.slice.call(box.querySelectorAll(".xh-dd__opt"));
      var active = Math.max(0, native.selectedIndex);
      if (!btn || !list || !native) return;

      btn.hidden = false;
      native.hidden = true;
      box.classList.add("is-enhanced");

      var mark = function (i) {
        active = i;
        opts.forEach(function (o, j) { o.classList.toggle("is-active", j === i); });
        list.setAttribute("aria-activedescendant", opts[i].id);
        opts[i].scrollIntoView({ block: "nearest" });
      };
      var close = function (focusBtn) {
        list.hidden = true;
        btn.setAttribute("aria-expanded", "false");
        box.classList.remove("is-open");
        if (openDd === api) openDd = null;
        if (focusBtn) btn.focus();
      };
      var open = function () {
        if (openDd && openDd !== api) openDd.close(false);
        list.hidden = false;
        btn.setAttribute("aria-expanded", "true");
        box.classList.add("is-open");
        openDd = api;
        mark(Math.max(0, native.selectedIndex));
        list.focus({ preventScroll: true });
      };
      var set = function (val, quiet) {
        var i = opts.findIndex(function (o) { return o.getAttribute("data-value") === val; });
        if (i < 0) return;
        opts.forEach(function (o, j) { o.setAttribute("aria-selected", j === i ? "true" : "false"); });
        value.textContent = opts[i].querySelector(".xh-dd__name").textContent;
        native.selectedIndex = i;
        value.classList.remove("is-updated");
        void value.offsetWidth; // restart the small settle animation
        value.classList.add("is-updated");
        if (!quiet) native.dispatchEvent(new Event("change", { bubbles: true }));
      };
      var choose = function (i) {
        set(opts[i].getAttribute("data-value"), false);
        close(true);
      };
      var api = { close: close, set: set };
      dds[kind] = api;

      btn.addEventListener("click", function () { if (list.hidden) open(); else close(true); });
      btn.addEventListener("keydown", function (e) {
        if (e.key === "ArrowDown" || e.key === "ArrowUp") { e.preventDefault(); open(); }
      });
      list.addEventListener("keydown", function (e) {
        if (e.key === "ArrowDown") { e.preventDefault(); mark(Math.min(opts.length - 1, active + 1)); }
        else if (e.key === "ArrowUp") { e.preventDefault(); mark(Math.max(0, active - 1)); }
        else if (e.key === "Home") { e.preventDefault(); mark(0); }
        else if (e.key === "End") { e.preventDefault(); mark(opts.length - 1); }
        else if (e.key === "Enter" || e.key === " ") { e.preventDefault(); choose(active); }
        else if (e.key === "Escape") { e.preventDefault(); close(true); }
        else if (e.key === "Tab") { close(false); }
      });
      opts.forEach(function (o, i) {
        o.addEventListener("mousemove", function () { if (active !== i) mark(i); });
        o.addEventListener("click", function () { choose(i); });
      });
      native.addEventListener("change", function () {
        if (kind === "help") pickFeel(native.value);
        else compose();
      });
    });
    document.addEventListener("click", function (e) {
      if (openDd && !e.target.closest(".xh-dd.is-open")) openDd.close(false);
    });
  }

  /* --- Perth Reset: each stop's own picture ---------------------------- */
  var reset = document.querySelector("[data-xh-reset]");
  if (reset) {
    var img = reset.querySelector("[data-reset-img]");
    var credit = reset.querySelector("[data-reset-credit]");
    var stops = reset.querySelectorAll(".xh-stop[data-stop-img]");
    var show = function (stop) {
      if (!img || img.getAttribute("src") === stop.dataset.stopImg) return;
      reset.querySelectorAll(".xh-stop").forEach(function (s) { s.classList.toggle("is-active", s === stop); });
      img.classList.add("is-swapping");
      var next = new Image();
      next.onload = function () {
        img.src = stop.dataset.stopImg;
        if (credit) credit.textContent = stop.dataset.stopCredit || "";
        img.classList.remove("is-swapping");
      };
      next.src = stop.dataset.stopImg;
    };
    stops.forEach(function (stop) {
      stop.addEventListener("mouseenter", function () { show(stop); });
      stop.addEventListener("focusin", function () { show(stop); });
    });
  }

  /* --- Four ways in: swipe-row buttons --------------------------------- */
  var track = document.getElementById("xh-worlds-track");
  if (track) {
    var step = function (dir) {
      var card = track.querySelector(".xh-world");
      var by = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
      track.scrollBy({ left: dir * by, behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
    };
    document.querySelectorAll("[data-scroll-prev]").forEach(function (b) { b.addEventListener("click", function () { step(-1); }); });
    document.querySelectorAll("[data-scroll-next]").forEach(function (b) { b.addEventListener("click", function () { step(1); }); });
  }

  /* --- A few places worth knowing: the sets take turns ----------------- */
  /* Every set is in the markup (the page is cached whole); the first shows
     without scripting. Here: start on a random set, move on every 8s while
     the section is on screen, never while it is hovered or holds focus,
     and never by itself with reduced motion. Previous / Pause / Next, and
     the next set's pictures are fetched before it is due. */
  var rot = document.querySelector("[data-xh-rot]");
  if (rot) {
    var sets = Array.prototype.slice.call(rot.querySelectorAll("[data-xh-set]"));
    var ctl = rot.querySelector("[data-xh-rot-ctl]");
    if (sets.length > 1 && ctl) {
      var still = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      var count = rot.querySelector("[data-xh-rot-count]");
      var pauseBtn = rot.querySelector("[data-xh-rot-pause]");
      var icon = rot.querySelector("[data-xh-rot-icon]");
      var cur = 0, timer = null, paused = still, hovered = false, seen = false;
      function warm(i) {
        sets[i].querySelectorAll("img[loading=lazy]").forEach(function (img) { img.loading = "eager"; });
      }
      function show(i, fromUser) {
        cur = (i + sets.length) % sets.length;
        sets.forEach(function (s, k) {
          s.hidden = k !== cur;
          s.classList.toggle("is-in", k === cur && !still);
        });
        if (count) count.textContent = (cur + 1) + " / " + sets.length;
        warm((cur + 1) % sets.length);
        if (fromUser) schedule();
      }
      function paintPause() {
        pauseBtn.setAttribute("aria-pressed", paused ? "true" : "false");
        pauseBtn.setAttribute("aria-label", paused ? "Play" : "Pause");
        if (icon) icon.innerHTML = paused ? "&#9654;" : "&#10074;&#10074;";
      }
      function schedule() {
        window.clearTimeout(timer);
        if (paused || hovered || !seen) return;
        timer = window.setTimeout(function () { show(cur + 1, false); schedule(); }, 8000);
      }
      ctl.hidden = false;
      rot.classList.add("is-live");
      show(Math.floor(Math.random() * sets.length), false);
      paintPause();
      rot.querySelector("[data-xh-rot-prev]").addEventListener("click", function () { show(cur - 1, true); });
      rot.querySelector("[data-xh-rot-next]").addEventListener("click", function () { show(cur + 1, true); });
      pauseBtn.addEventListener("click", function () { paused = !paused; paintPause(); schedule(); });
      rot.addEventListener("mouseenter", function () { hovered = true; schedule(); });
      rot.addEventListener("mouseleave", function () { hovered = false; schedule(); });
      // Keyboard focus only: a mouse click on Next leaves focus on the button,
      // and that should not stop the turns for good.
      rot.addEventListener("focusin", function (e) {
        var kb = true;
        try { kb = e.target.matches(":focus-visible"); } catch (err) { kb = true; }
        if (kb) { hovered = true; schedule(); }
      });
      rot.addEventListener("focusout", function (e) { if (!rot.contains(e.relatedTarget)) { hovered = false; schedule(); } });
      if ("IntersectionObserver" in window) {
        // "On screen" = reaching the middle half of the viewport. A ratio
        // threshold never fires: the section is taller than most screens.
        new IntersectionObserver(function (en) { seen = en[0].isIntersecting; schedule(); }, { rootMargin: "-25% 0px -25% 0px", threshold: 0 }).observe(rot);
      } else {
        seen = true; schedule();
      }
    }
  }
})();
