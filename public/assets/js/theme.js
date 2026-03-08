// Theme toggle — runs after DOM is ready (script has defer attribute)
(function () {
    'use strict';

    function readStored() {
        try { return localStorage.getItem('theme'); } catch (e) {}
        try { return (document.cookie.match(/theme=(light|dark)/) || [])[1] || null; } catch (e) {}
        return null;
    }

    function persist(t) {
        try { localStorage.setItem('theme', t); } catch (e) {}
        try { document.cookie = 'theme=' + t + ';path=/;max-age=31536000;SameSite=Lax'; } catch (e) {}
    }

    function applyIcon(btn, theme) {
        btn.textContent = theme === 'dark' ? '\u2600' : '\u263D';
        btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    }

    // Script is deferred → DOM is already parsed, no need for DOMContentLoaded
    var btn = document.getElementById('theme-toggle');
    if (!btn) return;

    var current = document.documentElement.getAttribute('data-theme') || readStored() || 'light';
    document.documentElement.setAttribute('data-theme', current);
    applyIcon(btn, current);

    btn.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        persist(next);
        applyIcon(btn, next);
    });
}());
