// Theme toggle (light/dark) with cookie persistence
(function () {
    'use strict';

    function updateIcon(btn, theme) {
        btn.textContent = theme === 'dark' ? '\u2600' : '\u263D';
        btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('theme-toggle');
        if (!btn) return;

        var current = document.documentElement.getAttribute('data-theme') || 'light';
        updateIcon(btn, current);

        btn.addEventListener('click', function () {
            var next = (document.documentElement.getAttribute('data-theme') || 'light') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            document.cookie = 'theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';
            updateIcon(btn, next);
        });
    });
})();
