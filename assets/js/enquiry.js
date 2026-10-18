/* AJAX enquiry forms: any <form data-enquiry data-source="..."> posts to /enquiry. */
(function () {
  var loadedAt = Date.now();

  function setStatus(form, msg, ok) {
    var box = form.querySelector('.enq-status');
    if (!box) return;
    box.textContent = msg;
    box.className = 'enq-status ' + (ok ? 'is-ok' : 'is-err');
  }

  document.querySelectorAll('form[data-enquiry]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      var label = btn ? btn.innerHTML : '';
      var data = new FormData(form);
      data.append('source', form.getAttribute('data-source') || 'website');
      data.append('page', location.pathname);
      data.append('elapsed', String(Date.now() - loadedAt));
      if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }
      setStatus(form, '', true);

      fetch('/enquiry', { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Something went wrong.' }; }); })
        .then(function (res) {
          if (res.ok) {
            setStatus(form, res.message, true);
            form.reset();
            if (typeof gtag === 'function') { gtag('event', 'generate_lead', { event_category: 'enquiry', event_label: data.get('source') }); }
            var overlay = document.getElementById('bookingOverlay');
            if (overlay && overlay.contains(form)) {
              setTimeout(function () { overlay.classList.remove('active'); document.body.style.overflow = ''; setStatus(form, '', true); }, 2600);
            }
          } else {
            setStatus(form, res.message || 'Please check the form and try again.', false);
          }
        })
        .catch(function () { setStatus(form, 'Network error. Please call or WhatsApp us instead.', false); })
        .then(function () { if (btn) { btn.disabled = false; btn.innerHTML = label; } });
    });
  });
})();
