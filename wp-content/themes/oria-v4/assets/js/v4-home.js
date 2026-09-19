/*
 * v4 front page behaviour. Everything here is an enhancement: without it
 * the page is complete -- the Calm picture, a plain Ask Oria form, every
 * link crawlable.
 *
 *  - Mood chips: swap the hero picture and write the sentence into Ask Oria.
 *    Only Calm loads with the page; the rest arrive once it is idle, or on
 *    the first tap of their chip.
 *  - Perth Reset: hovering or focusing a stop shows its picture.
 *  - Four ways in (phones): previous / next buttons for the swipe row.
 */
(function () {
  "use strict";

  /* --- Mood chips ----------------------------------------------------- */
  var hero = document.querySelector("[data-xh-hero]");
  if (hero) {
    var chips = hero.querySelectorAll("[data-feel]");
    var pics = hero.querySelectorAll("[data-feel-pic]");
    var input = document.getElementById("xh-q");

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

    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        var feel = chip.getAttribute("data-feel");
        chips.forEach(function (c) { c.setAttribute("aria-pressed", c === chip ? "true" : "false"); });
        pics.forEach(function (p) {
          var on = p.getAttribute("data-feel-pic") === feel;
          if (on) load(p);
          p.classList.toggle("is-on", on);
        });
        if (input) {
          input.value = chip.getAttribute("data-feel-say") || "";
          input.classList.remove("is-updated");
          void input.offsetWidth; // restart the small settle animation
          input.classList.add("is-updated");
        }
      });
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
})();
