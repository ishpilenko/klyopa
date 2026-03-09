(function () {
    'use strict';

    var overlay = document.getElementById('search-overlay');
    var input   = document.getElementById('search-input');
    var results = document.getElementById('search-results');
    var toggle  = document.getElementById('search-toggle');

    if (!overlay || !input || !results) return;

    function openSearch() {
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        input.focus();
    }

    function closeSearch() {
        overlay.hidden = true;
        document.body.style.overflow = '';
        input.value = '';
        results.innerHTML = '';
    }

    if (toggle) toggle.addEventListener('click', openSearch);

    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }
        if (e.key === 'Escape' && !overlay.hidden) closeSearch();
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeSearch();
    });

    var timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { results.innerHTML = ''; return; }

        timer = setTimeout(function () {
            fetch('/api/v1/search?q=' + encodeURIComponent(q) + '&limit=8')
                .then(function (r) { return r.json(); })
                .then(function (data) { renderResults(data); })
                .catch(function () {});
        }, 250);
    });

    function renderResults(data) {
        if (!data || data.length === 0) {
            results.innerHTML = '<div class="search-empty">No results found</div>';
            return;
        }
        results.innerHTML = data.map(function (item) {
            return '<a href="' + escHtml(item.url) + '" class="search-result-item">' +
                '<span class="search-result-type">' + escHtml(item.type) + '</span>' +
                '<span class="search-result-title">' + escHtml(item.title) + '</span>' +
                '</a>';
        }).join('');
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
