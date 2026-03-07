// Auto-generate Table of Contents from article H2/H3 headings
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var body = document.getElementById('article-body');
        var tocList = document.getElementById('toc-list');
        if (!body || !tocList) return;

        var headings = body.querySelectorAll('h2, h3');
        if (headings.length < 3) {
            var sidebar = document.getElementById('article-toc-sidebar');
            if (sidebar) sidebar.hidden = true;
            return;
        }

        headings.forEach(function (heading, i) {
            var id = heading.id || ('section-' + i);
            heading.id = id;

            var li = document.createElement('li');
            if (heading.tagName === 'H3') li.classList.add('toc-h3');

            var a = document.createElement('a');
            a.href = '#' + id;
            a.textContent = heading.textContent;
            a.dataset.target = id;

            li.appendChild(a);
            tocList.appendChild(li);
        });

        // Highlight current section on scroll
        var tocLinks = tocList.querySelectorAll('a');
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    tocLinks.forEach(function (l) { l.classList.remove('toc-active'); });
                    var active = tocList.querySelector('a[data-target="' + entry.target.id + '"]');
                    if (active) active.classList.add('toc-active');
                }
            });
        }, { rootMargin: '-80px 0px -70% 0px' });

        headings.forEach(function (h) { observer.observe(h); });
    });
})();
