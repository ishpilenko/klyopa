/**
 * Tool Guard — Cloudflare Turnstile captcha gate for calculators and forms.
 *
 * Controlled by window.CAPTCHA_CONFIG injected from base.html.twig.
 * When CAPTCHA_ENABLED = false, this script does nothing.
 *
 * Tool usage: counts submissions in sessionStorage, shows captcha after threshold.
 * Newsletter: counts newsletter submits in sessionStorage, captcha after threshold.
 */
(function () {
    'use strict';

    var cfg = window.CAPTCHA_CONFIG || {};
    if (!cfg.enabled) return;

    var SITEKEY           = cfg.sitekey || '';
    var TOOLS_THRESHOLD   = cfg.toolsThreshold  || 10;
    var NL_THRESHOLD      = cfg.nlThreshold     || 1;   // newsletter
    var VERIFIED_TTL_MS   = 30 * 60 * 1000;             // 30 min verified window

    // ── SessionStorage helpers ────────────────────────────────────────────────

    function getStore(key) {
        try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
    }
    function setStore(key, val) {
        try { sessionStorage.setItem(key, JSON.stringify(val)); } catch {}
    }

    function getCount(key) {
        var d = getStore(key);
        return (d && typeof d.count === 'number') ? d.count : 0;
    }
    function incCount(key) {
        setStore(key, { count: getCount(key) + 1 });
    }

    function isVerified(key) {
        var d = getStore(key + '_verified');
        return d && d.until && Date.now() < d.until;
    }
    function markVerified(key) {
        setStore(key + '_verified', { until: Date.now() + VERIFIED_TTL_MS });
    }

    // ── Turnstile loader ──────────────────────────────────────────────────────

    var turnstileLoaded = false;
    var turnstileCallbacks = [];

    function loadTurnstile(cb) {
        if (typeof turnstile !== 'undefined') { cb(); return; }
        turnstileCallbacks.push(cb);
        if (turnstileLoaded) return;
        turnstileLoaded = true;
        var s = document.createElement('script');
        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        s.onload = function () {
            turnstileCallbacks.forEach(function (fn) { fn(); });
            turnstileCallbacks = [];
        };
        document.head.appendChild(s);
    }

    // ── Modal ─────────────────────────────────────────────────────────────────

    function showCaptchaModal(onSuccess, onDismiss) {
        var overlay = document.createElement('div');
        overlay.id = 'captcha-overlay';
        overlay.style.cssText = [
            'position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:600',
            'display:flex;align-items:center;justify-content:center'
        ].join(';');

        overlay.innerHTML = [
            '<div style="background:#fff;border-radius:12px;padding:2rem;max-width:380px;',
            'width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.2)">',
            '<div style="font-size:2rem;margin-bottom:.75rem">🔒</div>',
            '<h3 style="margin:0 0 .5rem;font-size:1.1rem">Quick verification</h3>',
            '<p style="margin:0 0 1.25rem;font-size:.875rem;color:#6b7280">',
            'To keep our free tools available for everyone,<br>please confirm you\'re human.</p>',
            '<div id="captcha-widget"></div>',
            '<button id="captcha-cancel" style="margin-top:1rem;background:none;border:none;',
            'cursor:pointer;color:#9ca3af;font-size:.8rem;text-decoration:underline">Cancel</button>',
            '</div>'
        ].join('');

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        document.getElementById('captcha-cancel').addEventListener('click', function () {
            cleanup();
            if (onDismiss) onDismiss();
        });

        function cleanup() {
            overlay.remove();
            document.body.style.overflow = '';
        }

        loadTurnstile(function () {
            turnstile.render('#captcha-widget', {
                sitekey: SITEKEY,
                theme: 'light',
                callback: function (token) {
                    cleanup();
                    onSuccess(token);
                },
                'error-callback': function () {
                    cleanup();
                    if (onDismiss) onDismiss();
                },
                'expired-callback': function () {
                    cleanup();
                    if (onDismiss) onDismiss();
                }
            });
        });
    }

    // ── Tool forms (GET calculators) ──────────────────────────────────────────

    var toolForms = document.querySelectorAll(
        '.investment-form, #tool-form, [data-captcha-guard="tool"]'
    );

    toolForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var storeKey = 'tool_uses';

            incCount(storeKey);
            var count = getCount(storeKey);

            if (count <= TOOLS_THRESHOLD || isVerified(storeKey)) {
                return; // allow
            }

            e.preventDefault();

            showCaptchaModal(
                function (token) {
                    markVerified(storeKey);
                    // inject token then resubmit
                    var inp = form.querySelector('[name="cf_token"]');
                    if (!inp) {
                        inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'cf_token';
                        form.appendChild(inp);
                    }
                    inp.value = token;
                    form.submit();
                },
                null
            );
        });
    });

    // ── Newsletter form ───────────────────────────────────────────────────────

    var nlForms = document.querySelectorAll(
        '.newsletter-form, [data-captcha-guard="newsletter"]'
    );

    nlForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var storeKey  = 'nl_submits';
            var count     = getCount(storeKey);
            var needsCaptcha = count >= NL_THRESHOLD && !isVerified(storeKey);

            function doSubmit(token) {
                incCount(storeKey);

                var emailEl   = form.querySelector('[name="email"]');
                var csrfEl    = form.querySelector('[name="_token"]');
                var tokenEl   = form.querySelector('[name="cf-turnstile-response"]');

                if (!tokenEl) {
                    tokenEl = document.createElement('input');
                    tokenEl.type = 'hidden';
                    tokenEl.name = 'cf-turnstile-response';
                    form.appendChild(tokenEl);
                }
                tokenEl.value = token || '';

                var body = new URLSearchParams({
                    email:                   emailEl  ? emailEl.value  : '',
                    _token:                  csrfEl   ? csrfEl.value   : '',
                    'cf-turnstile-response': token || ''
                });

                var btn    = form.querySelector('button[type="submit"]');
                var status = form.querySelector('.nl-status');
                if (btn) btn.disabled = true;

                fetch('/newsletter/subscribe', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    body.toString()
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (status) {
                            status.textContent = data.message || 'You\'re subscribed! 🎉';
                            status.style.color = 'var(--color-positive)';
                        }
                        form.reset();
                        markVerified(storeKey);
                    } else {
                        if (status) {
                            status.textContent = data.error || 'Something went wrong.';
                            status.style.color = 'var(--color-negative)';
                        }
                        // If server requested captcha despite our count being low
                        if (data.captcha_required && btn) btn.disabled = false;
                    }
                })
                .catch(function () {
                    if (status) {
                        status.textContent = 'Network error. Please try again.';
                        status.style.color = 'var(--color-negative)';
                    }
                })
                .finally(function () {
                    if (btn) btn.disabled = false;
                });
            }

            if (needsCaptcha) {
                showCaptchaModal(doSubmit, null);
            } else {
                doSubmit('');
            }
        });
    });

})();
