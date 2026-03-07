// MultiSite Platform — App JS (vanilla)

document.addEventListener('DOMContentLoaded', () => {
    // Mobile nav toggle
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', nav.classList.contains('open'));
        });
    }

    // Lazy-load images that don't support native lazy loading
    if ('IntersectionObserver' in window) {
        const images = document.querySelectorAll('img[data-src]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        }, { rootMargin: '200px' });

        images.forEach(img => observer.observe(img));
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', (e) => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Active nav link based on current URL
    const currentPath = window.location.pathname;
    document.querySelectorAll('.site-nav a').forEach(link => {
        if (link.getAttribute('href') === currentPath || currentPath.startsWith(link.getAttribute('href') + '/')) {
            link.classList.add('active');
        }
    });
});
