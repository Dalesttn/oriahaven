/* Submit an event — the enhancements only.
 *
 * Everything here is progressive: the form is a plain HTML form that posts to
 * admin-post.php, and every control below starts life as a real input the
 * server already understands. With this file blocked the page still submits,
 * suburbs are still chosen from a native <select>, and the image still
 * uploads. Nothing here is load-bearing.
 */
(function () {
  "use strict";

  var form = document.querySelector("[data-submit-event]");
  if (!form) return;

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------------------------------------------------------- suburbs ---
     159 suburbs in a native select is a scroll, not a choice. This wraps
     the select in a combobox that filters as you type, and leaves the
     select itself in the DOM as the value the form posts -- so the server
     contract is untouched and a failure here degrades to the original
     control rather than to nothing. */
  function initSuburb() {
    var select = form.querySelector('select[name="suburb"]');
    if (!select || !window.matchMedia) return;

    var options = [];
    Array.prototype.forEach.call(select.querySelectorAll("option"), function (o) {
      if (!o.value) return;
      var group = o.parentElement.tagName === "OPTGROUP" ? o.parentElement.label : "";
      options.push({ value: o.value, label: o.textContent.trim(), group: group });
    });
    if (options.length < 20) return; // short list: the native control is better

    var wrap = document.createElement("div");
    wrap.className = "sev-combo";

    var input = document.createElement("input");
    input.type = "text";
    input.className = "input sev-combo__input";
    input.setAttribute("role", "combobox");
    input.setAttribute("aria-expanded", "false");
    input.setAttribute("aria-autocomplete", "list");
    input.setAttribute("autocomplete", "off");
    input.id = "suburbCombo";
    input.placeholder = select.getAttribute("data-placeholder") || "Choose a suburb";

    var list = document.createElement("ul");
    list.className = "sev-combo__list";
    list.id = "suburbComboList";
    list.setAttribute("role", "listbox");
    list.hidden = true;
    input.setAttribute("aria-controls", list.id);

    var status = document.createElement("span");
    status.className = "xp-vh";
    status.setAttribute("aria-live", "polite");

    // The select keeps the value and the label; it just stops being the
    // thing people poke at.
    select.classList.add("sev-combo__native");
    select.setAttribute("tabindex", "-1");
    select.setAttribute("aria-hidden", "true");

    var label = form.querySelector('[data-suburb-label]');
    if (label) label.setAttribute("for", input.id);

    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(input);
    wrap.appendChild(select);
    wrap.appendChild(list);
    wrap.appendChild(status);

    if (select.value) {
      var current = options.filter(function (o) { return o.value === select.value; })[0];
      if (current) input.value = current.label;
    }

    var matches = [];
    var active = -1;

    function close() {
      list.hidden = true;
      input.setAttribute("aria-expanded", "false");
      active = -1;
    }

    function choose(opt) {
      select.value = opt.value;
      input.value = opt.label;
      select.dispatchEvent(new Event("change", { bubbles: true }));
      close();
      input.focus();
    }

    function paint() {
      list.innerHTML = "";
      matches.slice(0, 40).forEach(function (o, i) {
        var li = document.createElement("li");
        li.className = "sev-combo__opt" + (i === active ? " is-active" : "");
        li.setAttribute("role", "option");
        li.id = "sevOpt" + i;
        li.setAttribute("aria-selected", i === active ? "true" : "false");
        li.innerHTML =
          '<span class="sev-combo__name"></span>' +
          (o.group ? '<span class="sev-combo__group"></span>' : "");
        li.querySelector(".sev-combo__name").textContent = o.label;
        if (o.group) li.querySelector(".sev-combo__group").textContent = o.group;
        li.addEventListener("mousedown", function (e) { e.preventDefault(); choose(o); });
        list.appendChild(li);
      });
      list.hidden = matches.length === 0;
      input.setAttribute("aria-expanded", matches.length ? "true" : "false");
      input.setAttribute("aria-activedescendant", active > -1 ? "sevOpt" + active : "");
      status.textContent = matches.length
        ? matches.length + (matches.length === 1 ? " suburb matches" : " suburbs match")
        : "No suburbs match";
    }

    function filter(term) {
      var q = term.trim().toLowerCase();
      matches = q
        ? options.filter(function (o) { return o.label.toLowerCase().indexOf(q) > -1; })
        : options.slice(0);
      active = -1;
      paint();
    }

    input.addEventListener("input", function () {
      // Typing invalidates any previous pick: the form must never post a
      // suburb the person can no longer see in the box.
      select.value = "";
      filter(input.value);
    });
    input.addEventListener("focus", function () { filter(input.value); });
    input.addEventListener("blur", function () {
      window.setTimeout(function () {
        close();
        // Nothing chosen: put back whatever the select still holds, so the
        // box never shows a suburb that was not actually selected.
        var current = options.filter(function (o) { return o.value === select.value; })[0];
        input.value = current ? current.label : "";
      }, 120);
    });

    input.addEventListener("keydown", function (e) {
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        e.preventDefault();
        if (list.hidden) filter(input.value);
        if (!matches.length) return;
        active += e.key === "ArrowDown" ? 1 : -1;
        if (active < 0) active = matches.length - 1;
        if (active >= Math.min(matches.length, 40)) active = 0;
        paint();
        var el = document.getElementById("sevOpt" + active);
        if (el) el.scrollIntoView({ block: "nearest" });
      } else if (e.key === "Enter") {
        if (!list.hidden && active > -1) { e.preventDefault(); choose(matches[active]); }
      } else if (e.key === "Escape") {
        close();
      }
    });
  }

  /* ------------------------------------------------------------ price ---
     Free and a price are the same question asked twice. Ticking free mutes
     the box rather than emptying it, so unticking gives back what was
     typed -- the server already prefers free when both arrive. */
  function initPrice() {
    var free = form.querySelector('input[name="free"]');
    var price = form.querySelector('input[name="price"]');
    if (!free || !price) return;

    function sync() {
      price.disabled = free.checked;
      price.closest(".field").classList.toggle("is-muted", free.checked);
    }
    free.addEventListener("change", sync);
    sync();
  }

  /* ------------------------------------------------------------ dates ---
     An end before a start is the one date mistake worth catching here.
     Overnight events are legitimate, so the finish TIME is never policed --
     only the end date, and only once a start exists. */
  function initDates() {
    var start = form.querySelector('input[name="start_date"]');
    var end = form.querySelector('input[name="end_date"]');
    if (!start || !end) return;

    start.addEventListener("change", function () {
      end.min = start.value || "";
      if (end.value && start.value && end.value < start.value) end.value = start.value;
    });
    if (start.value) end.min = start.value;
  }

  /* ------------------------------------------------------ description ---
     A guide, not a limit: the counter turns amber past the suggested
     length and never blocks a submission. */
  function initCounter() {
    var box = form.querySelector('textarea[name="description"]');
    var out = form.querySelector("[data-desc-count]");
    if (!box || !out) return;

    function count() {
      var words = box.value.trim() ? box.value.trim().split(/\s+/).length : 0;
      out.textContent = words === 1 ? "1 word" : words + " words";
      out.classList.toggle("is-over", words > 160);
    }
    box.addEventListener("input", count);
    count();
  }

  /* ----------------------------------------------------------- image ---
     The file input stays exactly as it was; this adds the filename, a
     preview and a way to change your mind. Type and size are checked here
     for speed and again on the server, which is the one that counts. */
  function initImage() {
    var input = form.querySelector('input[type="file"][name="image"]');
    var zone = form.querySelector("[data-image-zone]");
    if (!input || !zone) return;

    var preview = zone.querySelector("[data-image-preview]");
    var name = zone.querySelector("[data-image-name]");
    var remove = zone.querySelector("[data-image-remove]");
    var note = zone.querySelector("[data-image-note]");
    var OK = ["image/jpeg", "image/png", "image/webp"];
    var MAX = 5 * 1024 * 1024;

    function reset(message) {
      input.value = "";
      if (preview) { preview.hidden = true; preview.removeAttribute("src"); }
      if (name) name.textContent = "";
      if (remove) remove.hidden = true;
      zone.classList.remove("is-filled");
      if (note) note.textContent = message || "";
      note.classList.toggle("is-error", !!message);
    }

    input.addEventListener("change", function () {
      var file = input.files && input.files[0];
      if (!file) return reset("");

      if (OK.indexOf(file.type) === -1) {
        return reset("That file is not a JPEG, PNG or WebP.");
      }
      if (file.size > MAX) {
        return reset("That image is " + Math.round(file.size / 1048576 * 10) / 10 + "MB — the limit is 5MB.");
      }

      if (name) name.textContent = file.name;
      if (remove) remove.hidden = false;
      if (note) { note.textContent = ""; note.classList.remove("is-error"); }
      zone.classList.add("is-filled");

      if (preview && window.FileReader) {
        var reader = new FileReader();
        reader.onload = function (e) { preview.src = e.target.result; preview.hidden = false; };
        reader.readAsDataURL(file);
      }
    });

    if (remove) {
      remove.addEventListener("click", function () { reset(""); input.focus(); });
    }
  }

  /* ---------------------------------------------------------- sending ---
     One submission, not three. The button says what is happening and the
     form refuses a second attempt while the first is in flight. */
  function initSending() {
    var button = form.querySelector("[data-submit-button]");
    if (!button) return;
    var busy = false;

    form.addEventListener("submit", function () {
      // Let the browser's own validation run first: disabling too early
      // stops a required field ever reporting itself.
      if (!form.checkValidity || !form.checkValidity()) return;
      if (busy) return;
      busy = true;
      button.classList.add("is-busy");
      button.setAttribute("aria-busy", "true");
      var label = button.querySelector("[data-submit-label]");
      if (label) label.textContent = "Sending your event…";
    });
  }

  /* --------------------------------------------------------- errors ---
     The server sends back error codes; the template turns them into a
     summary. This only moves focus there, and only after an attempt. */
  function initErrorFocus() {
    var summary = document.querySelector("[data-error-summary]");
    if (!summary) return;
    summary.setAttribute("tabindex", "-1");
    summary.focus({ preventScroll: reduced });
    if (!reduced) summary.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  initSuburb();
  initPrice();
  initDates();
  initCounter();
  initImage();
  initSending();
  initErrorFocus();
})();
