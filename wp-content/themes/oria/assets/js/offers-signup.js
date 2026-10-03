/* Offers signup: submit in place, remember a signup, and show the
   after-click prompt once someone has gone through to an offer. The form
   works without any of this (it posts to admin-post). */
(function () {
  "use strict";
  var KEY = "oria_sub";
  var done = false;
  try { done = localStorage.getItem(KEY) === "1"; } catch (e) { /* private mode */ }

  function markDone() {
    done = true;
    try { localStorage.setItem(KEY, "1"); } catch (e) { /* ignore */ }
  }

  function thanks(box, after) {
    box.innerHTML = '<p class="osub__done"><strong>You’re on the list.</strong> ' +
      (after ? "We’ll send the next offers this week." : "The next offers email comes out this week.") + "</p>";
  }

  /* Someone already on the list does not need to see the form again. */
  if (done) {
    document.querySelectorAll("[data-osub]").forEach(function (box) {
      if (box.classList.contains("osub--after")) box.remove(); else box.hidden = true;
    });
    return;
  }

  document.addEventListener("submit", function (e) {
    var form = e.target.closest && e.target.closest("[data-osub-form]");
    if (!form || !window.ORIA_SUB || !window.fetch) return;
    e.preventDefault();
    var box = form.closest("[data-osub]");
    var btn = form.querySelector("button[type=submit]");
    var err = form.querySelector(".osub__err");
    var fd = new FormData(form);
    if (btn) { btn.disabled = true; btn.textContent = "Sending…"; }
    fetch(ORIA_SUB.url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        email: fd.get("sub_email"),
        interest: fd.get("sub_interest"),
        suburb: fd.get("sub_suburb"),
        source: fd.get("sub_source"),
        listing: parseInt(fd.get("sub_listing"), 10) || 0,
        ts: parseInt(fd.get("oform_ts"), 10) || 0,
        website: fd.get("oform_website")
      })
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.ok) {
        markDone();
        thanks(box, box.classList.contains("osub--after"));
        if (window.dataLayer) window.dataLayer.push({ event: "offers_signup", signup_source: fd.get("sub_source"), signup_interest: fd.get("sub_interest") });
        document.querySelectorAll("[data-osub]").forEach(function (other) { if (other !== box) other.hidden = true; });
        return;
      }
      throw new Error(j && j.state || "error");
    }).catch(function () {
      if (btn) { btn.disabled = false; btn.textContent = "Send me offers"; }
      if (!err) {
        err = document.createElement("p");
        err.className = "osub__err";
        err.setAttribute("role", "alert");
        form.appendChild(err);
      }
      err.textContent = "That didn’t go through — check the address and try again.";
    });
  });

  /* After a click through to an offer, the moment of most interest: reveal
     the collapsed prompt beside that offer (or the first one on the page). */
  document.addEventListener("click", function (e) {
    var a = e.target.closest && e.target.closest('[data-oria-track="offer"]');
    if (!a || done) return;
    var scope = a.closest(".oh-offer-card, .oh-offers, .xp-decide__offers") || document;
    var box = scope.querySelector(".osub--after") || document.querySelector(".osub--after");
    if (!box || !box.hidden) return;
    box.hidden = false;
    box.classList.add("is-shown");
    var input = box.querySelector("input[type=email]");
    if (input && window.matchMedia && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      box.scrollIntoView({ block: "nearest", behavior: "smooth" });
    }
  });
})();
