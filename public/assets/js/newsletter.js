(function () {
    'use strict';

    document.querySelectorAll('.newsletter-widget').forEach(function (widget) {
        var url      = widget.dataset.url || '/newsletter/subscribe';
        var source   = widget.dataset.source || 'homepage';
        var emailEl  = widget.querySelector('.nl-email');
        var submitEl = widget.querySelector('.nl-submit');
        var statusEl = widget.querySelector('.nl-status');

        if (!emailEl || !submitEl) return;

        function showStatus(msg, ok) {
            if (statusEl) {
                statusEl.textContent = msg;
                statusEl.style.color = ok ? '#16a34a' : '#dc2626';
            }
        }

        function doSubmit(cfToken) {
            var email = emailEl.value.trim();
            if (!email) { emailEl.focus(); return; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showStatus('Please enter a valid email address.', false);
                return;
            }

            submitEl.disabled = true;
            submitEl.textContent = 'Subscribing…';

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email:                   email,
                    source:                  source,
                    'cf-turnstile-response': cfToken || '',
                }),
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (res.ok || res.data.message) {
                    showStatus(res.data.message || 'Check your email to confirm! 🎉', true);
                    emailEl.value = '';
                } else if (res.data.captcha_required) {
                    showStatus('Please complete the captcha to continue.', false);
                    submitEl.disabled = false;
                    submitEl.textContent = 'Subscribe Free';
                    // Trigger captcha via tool-guard if available
                    if (window.CAPTCHA_CONFIG && window.CAPTCHA_CONFIG.enabled) {
                        showStatus('One more step: ', false);
                    }
                } else {
                    showStatus(res.data.error || 'Something went wrong.', false);
                    submitEl.disabled = false;
                    submitEl.textContent = 'Subscribe Free';
                }
            })
            .catch(function () {
                showStatus('Network error. Please try again.', false);
                submitEl.disabled = false;
                submitEl.textContent = 'Subscribe Free';
            });
        }

        submitEl.addEventListener('click', function () { doSubmit(''); });
        emailEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') doSubmit(''); });
    });
})();
