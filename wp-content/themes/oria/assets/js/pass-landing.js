/* Oria Pass landing page: the example credit mix and the sticky bar.
 *
 * Both are enhancements. Without this file the mix shows its default
 * example as plain text, and the waitlist button in the hero is the way
 * to the form. Analytics are not here: app.js binds data-oria-event.
 */
(function () {
  "use strict";

  var root = document.querySelector(".oria-pl");
  if (!root) return;

  /* The example mix. Local arithmetic only: a total and what is left of
     the month's example credits. Never money, never a real balance, and
     never below zero -- a choice that would overshoot is refused with a
     reason, while anything chosen can always be removed. */
  var mix = root.querySelector("[data-pl-mix]");
  if (mix) {
    var budget = parseInt(mix.getAttribute("data-budget"), 10) || 0;
    var choices = Array.prototype.slice.call(mix.querySelectorAll("[data-cost]"));
    var total = mix.querySelector("[data-pl-total]");
    var left = mix.querySelector("[data-pl-left]");
    var limit = mix.querySelector("[data-pl-limit]");
    var hint = mix.querySelector("[data-pl-mix-hint]");

    var cost = function (b) { return parseInt(b.getAttribute("data-cost"), 10) || 0; };
    var on = function (b) { return b.getAttribute("aria-pressed") === "true"; };
    var used = function () {
      return choices.reduce(function (sum, b) { return sum + (on(b) ? cost(b) : 0); }, 0);
    };

    var update = function (message) {
      var u = used();
      total.textContent = u + " of " + budget + " example credits";
      left.textContent = " · " + Math.max(0, budget - u) + " remaining";
      choices.forEach(function (b) {
        var blocked = !on(b) && u + cost(b) > budget;
        if (blocked) b.setAttribute("aria-disabled", "true");
        else b.removeAttribute("aria-disabled");
      });
      limit.textContent = message || "";
    };

    choices.forEach(function (b) {
      b.disabled = false;
      b.addEventListener("click", function () {
        if (!on(b) && used() + cost(b) > budget) {
          update("That would go over the " + budget + " example credits. Remove an activity first.");
          return;
        }
        b.setAttribute("aria-pressed", on(b) ? "false" : "true");
        update("");
      });
    });
    if (hint) hint.hidden = false;
    update("");
  }

  /* The sticky bar: shown once the hero's button has gone, hidden while the
     form is on screen, with room reserved so it never covers the footer. */
  var bar = root.querySelector("[data-pl-sticky]");
  var hero = root.querySelector("[data-pl-hero-cta]");
  var join = root.querySelector("[data-pl-join]");
  if (bar && hero && "IntersectionObserver" in window) {
    var heroSeen = true;
    var joinSeen = false;
    var sync = function () {
      bar.hidden = heroSeen || joinSeen;
      root.classList.toggle("has-sticky", !bar.hidden);
    };
    new IntersectionObserver(function (en) {
      heroSeen = en[0].isIntersecting;
      sync();
    }).observe(hero);
    if (join) {
      new IntersectionObserver(function (en) {
        joinSeen = en[0].isIntersecting;
        sync();
      }).observe(join);
    }
  }

  /* After a successful signup, move focus to the confirmation so a screen
     reader announces it rather than the top of the page. */
  var done = root.querySelector("[data-pl-done]");
  if (done) done.focus();
})();
