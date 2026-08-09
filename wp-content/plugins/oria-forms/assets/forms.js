/* Oria Forms — business-name lookup.
 *
 * Turns a text input carrying data-oform-lookup into a combobox over
 * published listings: type two characters, pick from the list, and the
 * matching listing travels with the submission so a claim arrives already
 * paired to its listing. Typing a name that isn't listed still works —
 * this only ever assists, never blocks. */
(function () {
  "use strict";

  var CFG = window.ORIA_FORMS || {};
  if (!CFG.search) return;

  function debounce(fn, wait) {
    var t;
    return function () {
      var args = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, wait);
    };
  }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function init(input) {
    var panel = input.parentNode.querySelector("[data-oform-lookup-panel]");
    var note = input.parentNode.querySelector("[data-oform-lookup-note]");
    // The hidden partner that carries the chosen listing to the server.
    var ref = input.form ? input.form.querySelector('input[name="listing_ref"]') : null;
    if (!panel) return;

    var items = [];
    var active = -1;
    var lastQuery = "";

    function closeList() {
      panel.hidden = true;
      panel.innerHTML = "";
      items = [];
      active = -1;
      input.setAttribute("aria-expanded", "false");
    }

    function showNote(text, kind) {
      if (!note) return;
      if (!text) { note.hidden = true; note.textContent = ""; return; }
      note.textContent = text;
      note.className = "oform-lookup__note" + (kind ? " is-" + kind : "");
      note.hidden = false;
    }

    function choose(i) {
      var item = items[i];
      if (!item) return;
      input.value = item.name;
      if (ref) ref.value = item.name + " — " + item.url;
      closeList();
      showNote(item.claimed ? CFG.claimed : CFG.matched, item.claimed ? "warn" : "ok");
    }

    function paint(results) {
      items = results;
      if (!results.length) { closeList(); return; }
      panel.innerHTML = results.map(function (r, i) {
        return '<span class="oform-lookup__item" role="option" id="oform-opt-' + i +
          '" data-i="' + i + '" aria-selected="false">' +
          '<b>' + esc(r.name) + "</b>" +
          (r.where ? '<em>' + esc(r.where) + "</em>" : "") +
          (r.claimed ? '<i class="oform-lookup__flag">claimed</i>' : "") +
          "</span>";
      }).join("");
      panel.hidden = false;
      panel.setAttribute("role", "listbox");
      input.setAttribute("aria-expanded", "true");
      active = -1;
    }

    function highlight(next) {
      var opts = panel.querySelectorAll(".oform-lookup__item");
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

    var search = debounce(function () {
      var q = input.value.trim();
      if (q.length < 2) { closeList(); return; }
      if (q === lastQuery) return;
      lastQuery = q;
      var headers = { Accept: "application/json" };
      if (CFG.nonce) headers["X-WP-Nonce"] = CFG.nonce;
      fetch(CFG.search + "?q=" + encodeURIComponent(q), { headers: headers, credentials: "same-origin" })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (rows) {
          // A late response for an abandoned query must not reopen the list.
          if (input.value.trim() !== q) return;
          paint(Array.isArray(rows) ? rows : []);
        })
        .catch(function () { closeList(); });
    }, 180);

    input.addEventListener("input", function () {
      // Typing after a pick means the pairing no longer holds.
      if (ref) ref.value = "";
      showNote("");
      search();
    });

    input.addEventListener("keydown", function (e) {
      if (panel.hidden) return;
      if (e.key === "ArrowDown") { e.preventDefault(); highlight(active + 1); }
      else if (e.key === "ArrowUp") { e.preventDefault(); highlight(active - 1); }
      else if (e.key === "Enter" && active > -1) { e.preventDefault(); choose(active); }
      else if (e.key === "Escape") { closeList(); }
    });

    // mousedown, not click: blur would close the list first.
    panel.addEventListener("mousedown", function (e) {
      var el = e.target.closest(".oform-lookup__item");
      if (!el) return;
      e.preventDefault();
      choose(Number(el.dataset.i));
    });

    input.addEventListener("blur", function () { setTimeout(closeList, 120); });
  }

  document.addEventListener("DOMContentLoaded", function () {
    Array.prototype.forEach.call(
      document.querySelectorAll("[data-oform-lookup]"),
      init
    );
  });
})();
