// Show "Read Next" sticky bar when user reaches end of article
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var bar = document.getElementById('read-next-bar');
        var body = document.getElementById('article-body');
        if (!bar || !body) return;

        var lastChild = body.lastElementChild;
        if (!lastChild) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    bar.removeAttribute('hidden');
                    setTimeout(function () { bar.classList.add('visible'); }, 10);
                } else {
                    bar.classList.remove('visible');
                    setTimeout(function () { bar.setAttribute('hidden', ''); }, 300);
                }
            });
        }, { rootMargin: '0px 0px -20% 0px' });

        observer.observe(lastChild);
    });
})();
