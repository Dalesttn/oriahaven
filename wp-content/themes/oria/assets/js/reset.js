/* The Micro Reset: progress, timers and the sticky bar.
 *
 * The page is complete without this file. Every stage is already on screen
 * and readable; this adds the parts that only make sense once somebody is
 * actually walking -- where they are up to, a silent countdown they can
 * ignore, and picking up where they left off when the screen locks and they
 * come back ten minutes later.
 *
 * Two things it deliberately never does. It does not gate a stage behind
 * the one before it: somebody can read the lot, skip three, or start in the
 * middle, because a walk is not a form. And the word they choose at the end
 * stays in their own browser -- it is never posted anywhere and never goes
 * to analytics, which is the whole reason it is safe to ask for.
 */
(function () {
  "use strict";

  var root = document.querySelector("[data-reset]");
  if (!root) return;

  var KEY = "oria_reset_" + (root.dataset.resetSlug || root.dataset.reset || "x");
  var stages = Array.prototype.slice.call(root.querySelectorAll(".rstage"));
  if (!stages.length) return;

  var bar = root.querySelector("[data-reset-bar]");
  var barText = root.querySelector("[data-reset-bar-text]");
  var prog = root.querySelector("[data-reset-progress]");
  var progText = root.querySelector("[data-reset-progress-text]");
  var doneBox = root.querySelector("[data-reset-complete]");

  var state = read();

  /* localStorage throws in a private window and returns nothing in a
     preview, so every read is a try and every failure is simply "no
     progress yet" rather than a broken page. */
  function read() {
    try {
      var raw = window.localStorage.getItem(KEY);
      var v = raw ? JSON.parse(raw) : null;
      if (v && typeof v === "object") {
        return { done: Array.isArray(v.done) ? v.done : [], word: v.word || "", at: v.at || 0 };
      }
    } catch (e) {}
    return { done: [], word: "", at: 0 };
  }

  function write() {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(state));
    } catch (e) {}
  }

  function track(name, params) {
    try {
      window.dataLayer = window.dataLayer || [];
      var p = params || {};
      p.event = name;
      p.reset_slug = root.dataset.resetSlug || "";
      window.dataLayer.push(p);
    } catch (e) {}
  }

  function isDone(n) {
    return state.done.indexOf(n) > -1;
  }

  /* The first stage that is not finished -- where "continue" goes. */
  function next() {
    for (var i = 0; i < stages.length; i++) {
      if (!isDone(i + 1)) return i + 1;
    }
    return 0;
  }

  function paint() {
    var count = state.done.length;
    var total = stages.length;

    stages.forEach(function (li) {
      var n = Number(li.dataset.stage);
      li.classList.toggle("is-done", isDone(n));
      var btn = li.querySelector("[data-reset-done]");
      if (btn) {
        btn.setAttribute("aria-pressed", isDone(n) ? "true" : "false");
        btn.textContent = isDone(n) ? btn.dataset.undo : btn.dataset.do;
      }
    });

    var started = count > 0;
    if (prog) {
      prog.hidden = !started;
      if (progText) {
        progText.textContent = count + " of " + total + " done";
      }
    }

    var up = next();
    if (bar) {
      // The bar is for somebody mid-walk; it has no job before they start
      // or once they have finished.
      bar.hidden = !started || !up;
      if (barText && up) {
        barText.textContent = "Stage " + up + " of " + total;
      }
    }

    if (doneBox) doneBox.hidden = !!up || !started;
  }

  stages.forEach(function (li) {
    var n = Number(li.dataset.stage);
    var btn = li.querySelector("[data-reset-done]");
    if (btn) {
      btn.dataset.do = btn.textContent.trim();
      btn.dataset.undo = "Not yet";
      btn.addEventListener("click", function () {
        if (isDone(n)) {
          state.done = state.done.filter(function (x) { return x !== n; });
        } else {
          state.done.push(n);
          track("micro_reset_stage_complete", { stage: n });
          if (state.done.length === stages.length) {
            track("micro_reset_complete", {});
          }
        }
        state.at = Date.now();
        write();
        paint();
      });
    }

    // The private word. Stored, never sent.
    li.querySelectorAll("[data-reset-word]").forEach(function (opt) {
      opt.addEventListener("click", function () {
        var word = opt.dataset.resetWord || "";
        var same = state.word === word;
        state.word = same ? "" : word;
        write();
        li.querySelectorAll("[data-reset-word]").forEach(function (o) {
          o.setAttribute("aria-pressed", !same && o === opt ? "true" : "false");
        });
      });
      if (state.word && opt.dataset.resetWord === state.word) {
        opt.setAttribute("aria-pressed", "true");
      }
    });
  });

  root.querySelectorAll("[data-reset-begin]").forEach(function (b) {
    b.addEventListener("click", function () {
      track("micro_reset_start", {});
    });
  });

  var restart = root.querySelector("[data-reset-restart]");
  if (restart) {
    restart.addEventListener("click", function () {
      state = { done: [], word: "", at: 0 };
      write();
      paint();
      track("micro_reset_restart", {});
      var first = stages[0];
      if (first) first.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }

  var cont = root.querySelector("[data-reset-continue]");
  if (cont) {
    cont.addEventListener("click", function () {
      var up = next();
      var li = up ? root.querySelector('.rstage[data-stage="' + up + '"]') : null;
      if (li) li.scrollIntoView({ behavior: "smooth", block: "start" });
      track("micro_reset_resume", { stage: up });
    });
  }

  var save = root.querySelector("[data-reset-save]");
  if (save) {
    save.addEventListener("click", function () {
      try {
        var k = "oria_saved_journeys";
        var list = JSON.parse(window.localStorage.getItem(k) || "[]");
        var slug = root.dataset.resetSlug;
        if (list.indexOf(slug) === -1) list.push(slug);
        window.localStorage.setItem(k, JSON.stringify(list));
        save.textContent = "Saved";
        save.disabled = true;
      } catch (e) {
        save.textContent = "Could not save on this device";
      }
      track("micro_reset_save", {});
    });
  }

  /* --- timers ----------------------------------------------------------
     Silent, skippable, and honest about being optional. Nothing is unlocked
     by finishing one and nothing is lost by ending it early. */
  root.querySelectorAll("[data-timer]").forEach(function (box) {
    var total = Number(box.dataset.timer) || 0;
    if (!total) return;

    var face = box.querySelector("[data-timer-face]");
    var start = box.querySelector("[data-timer-start]");
    var pause = box.querySelector("[data-timer-pause]");
    var end = box.querySelector("[data-timer-end]");
    var left = total;
    var tick = null;

    function show() {
      if (!face) return;
      var m = Math.floor(left / 60);
      var s = left % 60;
      face.textContent = m + ":" + (s < 10 ? "0" : "") + s;
    }

    function stop() {
      if (tick) window.clearInterval(tick);
      tick = null;
      if (start) start.hidden = false;
      if (pause) pause.hidden = true;
    }

    function run() {
      if (tick) return;
      if (start) start.hidden = true;
      if (pause) pause.hidden = false;
      if (end) end.hidden = false;
      track("micro_reset_timer_start", {});
      tick = window.setInterval(function () {
        left -= 1;
        if (left <= 0) {
          left = 0;
          show();
          stop();
          box.classList.add("is-done");
          if (face) face.setAttribute("aria-live", "polite");
          return;
        }
        show();
      }, 1000);
    }

    if (start) start.addEventListener("click", run);
    if (pause) pause.addEventListener("click", stop);
    if (end) {
      end.addEventListener("click", function () {
        stop();
        left = total;
        show();
        box.classList.remove("is-done");
        end.hidden = true;
      });
    }
    show();
  });

  paint();
  track("micro_reset_view", {});
})();
