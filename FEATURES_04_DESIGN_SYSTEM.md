# Дизайн-система и UX — Инструкции для Claude Code

> Этот файл описывает дизайн-систему, UI-компоненты, паттерны удержания и UX-требования для крипто-сайта. Все решения подчинены двум целям: доверие (YMYL-ниша) и удержание (поведенческие метрики для SEO).

## Дизайн-философия

Визуальный стиль: **финтеховый минимализм**. Ориентиры — NerdWallet, Bloomberg, Stripe. Не «крипто-яркость» (неон, градиенты, космос), а чистота, читабельность, профессионализм. Сайт должен выглядеть как продукт, которому можно доверить финансовые решения.

Ключевые принципы:
- Контент — главный герой. Дизайн обслуживает текст, а не конкурирует с ним
- Белое пространство — не пустота, а дыхание
- Один акцентный цвет, остальное — нейтральные тона
- Анимации — функциональные, не декоративные
- Mobile-first во всём

---

## CSS-переменные и токены

Все значения хранятся в CSS custom properties. Два режима: light (default) и dark.

```css
/* templates/base_styles.html.twig или assets/css/variables.css */

:root {
    /* === Цветовая палитра === */
    
    /* Нейтральные */
    --color-bg-primary: #ffffff;
    --color-bg-secondary: #f8f9fb;
    --color-bg-tertiary: #f1f3f5;
    --color-bg-elevated: #ffffff;
    --color-border: #e2e5e9;
    --color-border-light: #eef0f3;
    
    /* Текст */
    --color-text-primary: #1a1d23;
    --color-text-secondary: #5c6370;
    --color-text-tertiary: #8b919a;
    --color-text-inverse: #ffffff;
    
    /* Акцент — один основной цвет бренда */
    --color-accent: #2563eb;           /* синий — доверие, финансы */
    --color-accent-hover: #1d4ed8;
    --color-accent-light: #eff6ff;
    --color-accent-subtle: #dbeafe;
    
    /* Семантические */
    --color-positive: #16a34a;
    --color-positive-bg: #f0fdf4;
    --color-negative: #dc2626;
    --color-negative-bg: #fef2f2;
    --color-warning: #d97706;
    --color-warning-bg: #fffbeb;
    
    /* === Типографика === */
    
    /* 
     * System font stack для body — мгновенная загрузка, 
     * отличная читабельность на всех платформах.
     * Кастомный шрифт ТОЛЬКО для H1 заголовков (опционально).
     */
    --font-body: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 
                 'Helvetica Neue', Arial, sans-serif;
    --font-heading: var(--font-body);  /* по умолчанию = body */
    --font-mono: 'SF Mono', SFMono-Regular, Consolas, 'Liberation Mono', 
                 Menlo, monospace;
    
    /* Размеры текста */
    --text-xs: 0.75rem;      /* 12px — мета, подписи */
    --text-sm: 0.875rem;     /* 14px — вторичный текст */
    --text-base: 1rem;       /* 16px — базовый на мобилке */
    --text-lg: 1.125rem;     /* 18px — базовый на десктопе */
    --text-xl: 1.25rem;      /* 20px */
    --text-2xl: 1.5rem;      /* 24px — H3 */
    --text-3xl: 1.875rem;    /* 30px — H2 */
    --text-4xl: 2.25rem;     /* 36px — H1 */
    --text-5xl: 3rem;        /* 48px — hero */
    
    --leading-tight: 1.25;
    --leading-normal: 1.6;
    --leading-relaxed: 1.75;
    
    /* === Отступы === */
    --space-1: 0.25rem;    /* 4px */
    --space-2: 0.5rem;     /* 8px */
    --space-3: 0.75rem;    /* 12px */
    --space-4: 1rem;       /* 16px */
    --space-5: 1.25rem;    /* 20px */
    --space-6: 1.5rem;     /* 24px */
    --space-8: 2rem;       /* 32px */
    --space-10: 2.5rem;    /* 40px */
    --space-12: 3rem;      /* 48px */
    --space-16: 4rem;      /* 64px */
    
    /* === Размеры === */
    --max-width-content: 720px;     /* текст статей */
    --max-width-page: 1200px;       /* общий контейнер */
    --max-width-wide: 1400px;       /* таблицы, листинги */
    
    /* === Радиусы === */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-full: 9999px;
    
    /* === Тени === */
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
    --shadow-elevated: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
    
    /* === Переходы === */
    --transition-fast: 150ms ease;
    --transition-normal: 250ms ease;
    --transition-slow: 350ms ease;
    
    /* === Z-index scale === */
    --z-dropdown: 100;
    --z-sticky: 200;
    --z-header: 300;
    --z-overlay: 400;
    --z-modal: 500;
}

/* === Dark mode === */
[data-theme="dark"] {
    --color-bg-primary: #0f1117;
    --color-bg-secondary: #161922;
    --color-bg-tertiary: #1e2230;
    --color-bg-elevated: #1e2230;
    --color-border: #2a2f3e;
    --color-border-light: #232838;
    
    --color-text-primary: #e8eaed;
    --color-text-secondary: #9ca3af;
    --color-text-tertiary: #6b7280;
    
    --color-accent: #3b82f6;
    --color-accent-hover: #60a5fa;
    --color-accent-light: #1e293b;
    --color-accent-subtle: #1e3a5f;
    
    --color-positive-bg: #052e16;
    --color-negative-bg: #450a0a;
    
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.4);
}
```

---

## Базовые стили

```css
/* assets/css/base.css */

*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html {
    font-size: 16px;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    scroll-behavior: smooth;
}

body {
    font-family: var(--font-body);
    font-size: var(--text-base);
    line-height: var(--leading-normal);
    color: var(--color-text-primary);
    background-color: var(--color-bg-primary);
}

/* Десктоп: увеличенный базовый размер для чтения */
@media (min-width: 768px) {
    body { font-size: var(--text-lg); }
}

/* Ссылки */
a {
    color: var(--color-accent);
    text-decoration: none;
    transition: color var(--transition-fast);
}
a:hover {
    color: var(--color-accent-hover);
    text-decoration: underline;
}

/* Изображения */
img {
    max-width: 100%;
    height: auto;
    display: block;
}

/* Input'ы — предотвращение zoom на iOS */
input, select, textarea {
    font-size: 16px; /* КРИТИЧНО: < 16px вызывает zoom на iOS */
    font-family: var(--font-body);
}
```

---

## Компоненты

### Header (sticky, collapsible)

```html
<!-- templates/components/header.html.twig -->
<header class="site-header" id="site-header">
    <div class="header-inner">
        <a href="{{ path('home') }}" class="header-logo">
            {# SVG логотип, не img — мгновенный рендеринг #}
            <svg>...</svg>
            <span class="header-logo-text">{{ site.name }}</span>
        </a>
        
        <nav class="header-nav" id="header-nav">
            <a href="{{ path('price_index') }}" class="header-nav-link">Prices</a>
            <a href="{{ path('article_category', {slug: 'news'}) }}" class="header-nav-link">News</a>
            <a href="{{ path('tools_index') }}" class="header-nav-link">Tools</a>
            <a href="{{ path('glossary_index') }}" class="header-nav-link">Learn</a>
            <a href="{{ path('article_category', {slug: 'reviews'}) }}" class="header-nav-link">Reviews</a>
        </nav>
        
        <div class="header-actions">
            <button class="header-search-btn" id="search-toggle" aria-label="Search">
                {# SVG search icon #}
            </button>
            <button class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
                {# SVG sun/moon icon #}
            </button>
            <button class="header-burger" id="burger-toggle" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
    
    {# Полноэкранный поиск — появляется при клике #}
    <div class="search-overlay" id="search-overlay" hidden>
        <input type="search" placeholder="Search articles, coins, tools..." 
               id="search-input" autocomplete="off">
        <div class="search-results" id="search-results"></div>
    </div>
</header>
```

```css
.site-header {
    position: sticky;
    top: 0;
    z-index: var(--z-header);
    background: var(--color-bg-primary);
    border-bottom: 1px solid var(--color-border-light);
    transition: transform var(--transition-normal);
}

/* Скрытие при скролле вниз, появление при скролле вверх */
.site-header.header-hidden {
    transform: translateY(-100%);
}

.header-inner {
    max-width: var(--max-width-wide);
    margin: 0 auto;
    padding: var(--space-3) var(--space-4);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-6);
}

.header-nav {
    display: none; /* скрыта на мобилке */
    gap: var(--space-1);
}
@media (min-width: 768px) {
    .header-nav { display: flex; }
    .header-burger { display: none; }
}

.header-nav-link {
    padding: var(--space-2) var(--space-3);
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    font-weight: 500;
    border-radius: var(--radius-md);
    transition: all var(--transition-fast);
}
.header-nav-link:hover,
.header-nav-link.active {
    color: var(--color-text-primary);
    background: var(--color-bg-secondary);
    text-decoration: none;
}
```

```javascript
// assets/js/header.js

// Скрытие/показ header при скролле
let lastScrollY = 0;
const header = document.getElementById('site-header');

window.addEventListener('scroll', () => {
    const currentScrollY = window.scrollY;
    if (currentScrollY > lastScrollY && currentScrollY > 80) {
        header.classList.add('header-hidden');
    } else {
        header.classList.remove('header-hidden');
    }
    lastScrollY = currentScrollY;
}, { passive: true });
```

### Dark Mode Toggle

```javascript
// assets/js/theme.js

function getTheme() {
    // Читаем из cookie (не localStorage — доступен в Twig для SSR)
    const match = document.cookie.match(/theme=(light|dark)/);
    if (match) return match[1];
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.cookie = `theme=${theme};path=/;max-age=31536000;SameSite=Lax`;
}

// Инициализация (вызывать inline в <head> для предотвращения FOUC)
setTheme(getTheme());

// Toggle
document.getElementById('theme-toggle')?.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme');
    setTheme(current === 'dark' ? 'light' : 'dark');
});
```

**ВАЖНО:** Inline-скрипт определения темы размещать в `<head>` ДО загрузки CSS, чтобы избежать мерцания (FOUC):

```html
<head>
    <script>
        (function(){
            var m=document.cookie.match(/theme=(light|dark)/);
            var t=m?m[1]:(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
            document.documentElement.setAttribute('data-theme',t);
        })();
    </script>
    <link rel="stylesheet" href="...">
</head>
```

### Article Layout

```html
<!-- templates/frontend/article/show.html.twig -->
<article class="article-page">
    {# Breadcrumbs #}
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <ol>
            <li><a href="{{ path('home') }}">Home</a></li>
            <li><a href="{{ path('article_category', {slug: article.category.slug}) }}">{{ article.category.name }}</a></li>
            <li aria-current="page">{{ article.title }}</li>
        </ol>
    </nav>

    {# Header #}
    <header class="article-header">
        <div class="article-meta-top">
            <span class="article-category-badge">{{ article.category.name }}</span>
            <time datetime="{{ article.publishedAt|date('Y-m-d') }}">
                {{ article.publishedAt|date('M d, Y') }}
            </time>
            {% if article.contentUpdatedAt %}
                <span class="article-updated">
                    Updated {{ article.contentUpdatedAt|date('M d, Y') }}
                </span>
            {% endif %}
        </div>
        <h1 class="article-title">{{ article.title }}</h1>
        <p class="article-excerpt">{{ article.excerpt }}</p>
        <div class="article-meta-bottom">
            <div class="article-author">
                <span class="article-author-name">{{ article.authorName }}</span>
            </div>
            <span class="article-reading-time">{{ article.readingTimeMinutes }} min read</span>
        </div>
    </header>

    {# Content area: sidebar + article body #}
    <div class="article-layout">
        {# Table of Contents — sticky sidebar на десктопе #}
        <aside class="article-sidebar" id="article-sidebar">
            <nav class="toc" id="toc" aria-label="Table of contents">
                <h2 class="toc-title">Contents</h2>
                {# Генерируется JS из H2/H3 в контенте #}
                <ol class="toc-list" id="toc-list"></ol>
            </nav>
            
            {# Мини-виджет цены (если статья про монету) #}
            {% if relatedCoin %}
            <div class="sidebar-widget price-widget">
                <span class="price-widget-name">{{ relatedCoin.name }}</span>
                <span class="price-widget-price">${{ relatedCoin.price|number_format(2) }}</span>
                <span class="price-widget-change {{ relatedCoin.change24h >= 0 ? 'positive' : 'negative' }}">
                    {{ relatedCoin.change24h >= 0 ? '+' : '' }}{{ relatedCoin.change24h|number_format(2) }}%
                </span>
            </div>
            {% endif %}
        </aside>

        {# Article body #}
        <div class="article-body" id="article-body">
            {{ article.content|raw }}
        </div>
    </div>

    {# Tags #}
    <div class="article-tags">
        {% for tag in article.tags %}
            <a href="{{ path('tag_show', {slug: tag.slug}) }}" class="tag-badge">{{ tag.name }}</a>
        {% endfor %}
    </div>

    {# Related articles #}
    <section class="related-articles">
        <h2>Related Articles</h2>
        <div class="related-articles-grid">
            {% for related in relatedArticles %}
                {{ include('components/article_card.html.twig', {article: related}) }}
            {% endfor %}
        </div>
    </section>
</article>

{# "Read next" sticky bar #}
<div class="read-next-bar" id="read-next-bar" hidden>
    <span class="read-next-label">Next</span>
    <a href="{{ path('article_show', {category: nextArticle.category.slug, slug: nextArticle.slug}) }}" 
       class="read-next-link">{{ nextArticle.title }}</a>
</div>
```

```css
/* Article layout */
.article-page {
    max-width: var(--max-width-page);
    margin: 0 auto;
    padding: var(--space-6) var(--space-4);
}

.article-header {
    max-width: var(--max-width-content);
    margin-bottom: var(--space-10);
}

.article-title {
    font-family: var(--font-heading);
    font-size: var(--text-4xl);
    line-height: var(--leading-tight);
    font-weight: 700;
    margin: var(--space-4) 0 var(--space-4);
    color: var(--color-text-primary);
    letter-spacing: -0.02em;
}

.article-excerpt {
    font-size: var(--text-xl);
    color: var(--color-text-secondary);
    line-height: var(--leading-relaxed);
}

/* Двухколоночный layout: sidebar + body */
.article-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-8);
}

@media (min-width: 1024px) {
    .article-layout {
        grid-template-columns: 220px 1fr;
        gap: var(--space-12);
    }
}

/* Sidebar: sticky ToC */
.article-sidebar {
    display: none;
}
@media (min-width: 1024px) {
    .article-sidebar {
        display: block;
        position: sticky;
        top: 80px; /* высота header + gap */
        max-height: calc(100vh - 100px);
        overflow-y: auto;
    }
}

/* Table of Contents */
.toc-title {
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-tertiary);
    margin-bottom: var(--space-3);
}

.toc-list {
    list-style: none;
    border-left: 2px solid var(--color-border-light);
    padding-left: var(--space-4);
}

.toc-list li {
    margin-bottom: var(--space-2);
}

.toc-list a {
    font-size: var(--text-sm);
    color: var(--color-text-tertiary);
    transition: all var(--transition-fast);
    display: block;
    padding: var(--space-1) 0;
}

.toc-list a:hover,
.toc-list a.toc-active {
    color: var(--color-accent);
    text-decoration: none;
}

/* Article body — типографика для длинного чтения */
.article-body {
    max-width: var(--max-width-content);
    line-height: var(--leading-relaxed);
}

.article-body h2 {
    font-size: var(--text-3xl);
    font-weight: 700;
    margin: var(--space-12) 0 var(--space-4);
    padding-top: var(--space-4);
    color: var(--color-text-primary);
    letter-spacing: -0.01em;
}

.article-body h3 {
    font-size: var(--text-2xl);
    font-weight: 600;
    margin: var(--space-8) 0 var(--space-3);
    color: var(--color-text-primary);
}

.article-body p {
    margin-bottom: var(--space-5);
}

.article-body ul, .article-body ol {
    margin-bottom: var(--space-5);
    padding-left: var(--space-6);
}

.article-body li {
    margin-bottom: var(--space-2);
}

.article-body blockquote {
    border-left: 3px solid var(--color-accent);
    padding-left: var(--space-5);
    margin: var(--space-6) 0;
    color: var(--color-text-secondary);
    font-style: italic;
}

.article-body table {
    width: 100%;
    border-collapse: collapse;
    margin: var(--space-6) 0;
    font-size: var(--text-sm);
}

.article-body th, .article-body td {
    padding: var(--space-3) var(--space-4);
    text-align: left;
    border-bottom: 1px solid var(--color-border-light);
}

.article-body th {
    background: var(--color-bg-secondary);
    font-weight: 600;
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--color-text-secondary);
}

/* Inline CTA блок внутри статьи */
.article-body .inline-cta {
    background: var(--color-accent-light);
    border-left: 4px solid var(--color-accent);
    padding: var(--space-4) var(--space-5);
    margin: var(--space-6) 0;
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
}

.article-body .inline-cta a {
    font-weight: 600;
}
```

### Article Card (для листингов и related)

```html
<!-- templates/components/article_card.html.twig -->
<a href="{{ path('article_show', {category: article.category.slug, slug: article.slug}) }}" 
   class="article-card">
    {% if article.featuredImage %}
        <img src="{{ article.featuredImage.path }}" 
             alt="{{ article.title }}"
             loading="lazy"
             width="400" height="225"
             class="article-card-image">
    {% endif %}
    <div class="article-card-body">
        <span class="article-card-category">{{ article.category.name }}</span>
        <h3 class="article-card-title">{{ article.title }}</h3>
        <p class="article-card-excerpt">{{ article.excerpt|u.truncate(120, '...') }}</p>
        <time class="article-card-date" datetime="{{ article.publishedAt|date('Y-m-d') }}">
            {{ article.publishedAt|date('M d, Y') }}
        </time>
    </div>
</a>
```

```css
.article-card {
    display: block;
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all var(--transition-normal);
    text-decoration: none;
    color: inherit;
}

.article-card:hover {
    border-color: var(--color-border);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
    text-decoration: none;
}

.article-card-image {
    width: 100%;
    height: auto;
    aspect-ratio: 16 / 9;
    object-fit: cover;
}

.article-card-body {
    padding: var(--space-4) var(--space-5);
}

.article-card-category {
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-accent);
}

.article-card-title {
    font-size: var(--text-lg);
    font-weight: 600;
    line-height: var(--leading-tight);
    margin: var(--space-2) 0;
    color: var(--color-text-primary);
}

.article-card-excerpt {
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    margin-bottom: var(--space-3);
}

.article-card-date {
    font-size: var(--text-xs);
    color: var(--color-text-tertiary);
}

/* Grid для карточек */
.related-articles-grid,
.articles-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-5);
}

@media (min-width: 640px) {
    .articles-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
    .articles-grid { grid-template-columns: repeat(3, 1fr); }
}
```

### Tool Widget (общий для калькуляторов)

```css
.tool-widget {
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    margin: var(--space-6) 0;
    max-width: 560px;
    box-shadow: var(--shadow-sm);
}

.tool-widget-title {
    font-size: var(--text-sm);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-tertiary);
    margin-bottom: var(--space-4);
}

/* Form elements */
.tool-field {
    margin-bottom: var(--space-4);
}

.tool-label {
    display: block;
    font-size: var(--text-sm);
    font-weight: 500;
    color: var(--color-text-secondary);
    margin-bottom: var(--space-1);
}

.tool-input {
    width: 100%;
    padding: var(--space-3) var(--space-4);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-primary);
    color: var(--color-text-primary);
    transition: border-color var(--transition-fast);
}

.tool-input:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px var(--color-accent-subtle);
}

.tool-select {
    appearance: none;
    background-image: url("data:image/svg+xml,...chevron-down...");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.tool-btn {
    width: 100%;
    padding: var(--space-3) var(--space-5);
    background: var(--color-accent);
    color: var(--color-text-inverse);
    border: none;
    border-radius: var(--radius-md);
    font-size: var(--text-base);
    font-weight: 600;
    cursor: pointer;
    transition: background var(--transition-fast);
}

.tool-btn:hover {
    background: var(--color-accent-hover);
}

/* Результат калькулятора */
.tool-result {
    margin-top: var(--space-5);
    padding: var(--space-5);
    border-radius: var(--radius-md);
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border-light);
}

.tool-result-value {
    font-size: var(--text-3xl);
    font-weight: 700;
    letter-spacing: -0.02em;
}

.tool-result-value.positive { color: var(--color-positive); }
.tool-result-value.negative { color: var(--color-negative); }

.tool-result-label {
    font-size: var(--text-sm);
    color: var(--color-text-tertiary);
    margin-bottom: var(--space-1);
}

/* Анимация появления результата */
.tool-result[data-visible="true"] {
    animation: fadeSlideUp 0.3s ease forwards;
}

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
```

### Price Ticker (для страниц цен)

```css
/* Мигание при обновлении цены */
.price-value {
    transition: color var(--transition-fast);
}

.price-value.flash-green {
    color: var(--color-positive);
}

.price-value.flash-red {
    color: var(--color-negative);
}
```

```javascript
// assets/js/price-flash.js
function flashPrice(element, direction) {
    const cls = direction === 'up' ? 'flash-green' : 'flash-red';
    element.classList.add(cls);
    setTimeout(() => element.classList.remove(cls), 1000);
}
```

### Skeleton Loading

```css
/* Placeholder при загрузке данных */
.skeleton {
    background: linear-gradient(
        90deg, 
        var(--color-bg-secondary) 25%, 
        var(--color-bg-tertiary) 50%, 
        var(--color-bg-secondary) 75%
    );
    background-size: 200% 100%;
    animation: shimmer 1.5s ease infinite;
    border-radius: var(--radius-sm);
}

.skeleton-text { height: 1em; margin-bottom: 0.5em; }
.skeleton-text:last-child { width: 70%; }
.skeleton-heading { height: 1.5em; width: 60%; margin-bottom: 1em; }
.skeleton-price { height: 2.5em; width: 40%; }
.skeleton-chart { height: 200px; }

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
```

### Read Next Bar

```css
.read-next-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--color-bg-elevated);
    border-top: 1px solid var(--color-border);
    padding: var(--space-3) var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-3);
    z-index: var(--z-sticky);
    transform: translateY(100%);
    transition: transform var(--transition-normal);
    box-shadow: var(--shadow-lg);
}

.read-next-bar.visible {
    transform: translateY(0);
}

.read-next-label {
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
    color: var(--color-text-tertiary);
    white-space: nowrap;
}

.read-next-link {
    font-weight: 600;
    font-size: var(--text-sm);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
```

```javascript
// assets/js/read-next.js
// Показываем "Read next" когда пользователь прочитал > 75% статьи
const bar = document.getElementById('read-next-bar');
const body = document.getElementById('article-body');

if (bar && body) {
    const observer = new IntersectionObserver(
        ([entry]) => {
            // Показать, когда конец статьи виден
            if (entry.isIntersecting) {
                bar.hidden = false;
                bar.classList.add('visible');
            }
        },
        { rootMargin: '0px 0px -20% 0px' }
    );
    // Наблюдаем за последним элементом статьи
    const lastChild = body.lastElementChild;
    if (lastChild) observer.observe(lastChild);
}
```

### Table of Contents (auto-generated)

```javascript
// assets/js/toc.js

function generateTOC() {
    const body = document.getElementById('article-body');
    const tocList = document.getElementById('toc-list');
    if (!body || !tocList) return;

    const headings = body.querySelectorAll('h2, h3');
    if (headings.length < 3) return; // не показывать ToC для коротких статей

    headings.forEach((heading, i) => {
        // Добавить id для якорной ссылки
        const id = heading.id || `section-${i}`;
        heading.id = id;

        const li = document.createElement('li');
        if (heading.tagName === 'H3') li.style.paddingLeft = '1rem';

        const a = document.createElement('a');
        a.href = `#${id}`;
        a.textContent = heading.textContent;
        a.dataset.target = id;

        li.appendChild(a);
        tocList.appendChild(li);
    });

    // Подсветка текущей секции при скролле
    const tocLinks = tocList.querySelectorAll('a');
    const observerOptions = { rootMargin: '-80px 0px -70% 0px' };

    const tocObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                tocLinks.forEach(link => link.classList.remove('toc-active'));
                const activeLink = tocList.querySelector(`a[data-target="${entry.target.id}"]`);
                if (activeLink) activeLink.classList.add('toc-active');
            }
        });
    }, observerOptions);

    headings.forEach(h => tocObserver.observe(h));
}

document.addEventListener('DOMContentLoaded', generateTOC);
```

---

## Паттерны удержания

### Inline CTA внутри статей

Twig Extension, который вставляет контекстные CTA при рендеринге article.content:

```php
// src/Twig/Extension/InlineCTAExtension.php

// При рендеринге контента статьи, после каждого 3-го H2:
// - Если упоминается монета → CTA на Price page + Calculator
// - Если упоминается биржа → CTA на Review
// - Если упоминается термин → CTA на Glossary

// HTML CTA-блока:
// <div class="inline-cta">
//   <strong>📊 Calculate your Bitcoin DCA returns</strong><br>
//   <a href="/tools/dca-calculator/bitcoin">Try our free DCA Calculator →</a>
// </div>
```

### Share-кнопка на результатах калькулятора

```html
<button class="share-result-btn" onclick="shareResult()">
    <svg>...</svg> Share Result
</button>
```

```javascript
function shareResult() {
    const url = window.location.href; // URL уже содержит параметры расчёта
    
    if (navigator.share) {
        // Native share на мобилке
        navigator.share({ title: document.title, url });
    } else {
        // Копировать в буфер
        navigator.clipboard.writeText(url).then(() => {
            // Показать тост "Link copied!"
            showToast('Link copied to clipboard!');
        });
    }
}
```

### Mobile Bottom Nav (для страниц инструментов)

```html
<!-- Показывается только на /tools/* страницах, только на мобилке -->
<nav class="tools-bottom-nav" aria-label="Tools navigation">
    <a href="{{ path('tool_profit_calculator') }}" class="tools-bottom-nav-item {{ active == 'profit' ? 'active' : '' }}">
        <svg>...</svg>
        <span>Profit</span>
    </a>
    <a href="{{ path('tool_dca_calculator') }}" class="tools-bottom-nav-item {{ active == 'dca' ? 'active' : '' }}">
        <svg>...</svg>
        <span>DCA</span>
    </a>
    <a href="{{ path('converter_index') }}" class="tools-bottom-nav-item {{ active == 'convert' ? 'active' : '' }}">
        <svg>...</svg>
        <span>Convert</span>
    </a>
    <a href="{{ path('tool_tax_calculator') }}" class="tools-bottom-nav-item {{ active == 'tax' ? 'active' : '' }}">
        <svg>...</svg>
        <span>Tax</span>
    </a>
</nav>
```

```css
.tools-bottom-nav {
    display: flex;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--color-bg-elevated);
    border-top: 1px solid var(--color-border);
    z-index: var(--z-sticky);
    padding: var(--space-1) 0 env(safe-area-inset-bottom, 0);
}

@media (min-width: 768px) {
    .tools-bottom-nav { display: none; }
}

.tools-bottom-nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--space-2);
    font-size: var(--text-xs);
    color: var(--color-text-tertiary);
    text-decoration: none;
    gap: 2px;
}

.tools-bottom-nav-item.active {
    color: var(--color-accent);
}

.tools-bottom-nav-item svg {
    width: 20px;
    height: 20px;
}
```

---

## Антипаттерны — что НЕ делать

Ниже — список конкретных запретов. Claude Code не должен генерировать код с этими паттернами:

1. **Никаких модальных попапов при загрузке страницы.** Ни подписка, ни cookie-consent overlay (использовать ненавязчивый banner внизу).
2. **Никаких каруселей / слайдеров** на главной или в листингах. Только статичные grid-layouts.
3. **Никакого infinite scroll** для листингов статей. Использовать pagination с числовыми ссылками (SEO-friendly).
4. **Не более одного рекламного блока** above the fold (видимая область при загрузке).
5. **Никаких кастомных скроллбаров.** Нативные скроллбары — предсказуемый UX.
6. **Никаких tooltip'ов на мобилке** (hover не работает). Использовать inline-подписи.
7. **Никаких автопроигрывающихся видео или аудио.**
8. **Никакого parallax-скроллинга.** Негативно влияет на производительность.
9. **Не использовать `cursor: pointer`** на элементах, которые не являются ссылками или кнопками.
10. **Не скрывать контент за табами или аккордеонами** для SEO-важного текста — Google может проигнорировать скрытый контент.

---

## Performance-бюджет

Целевые метрики для PageSpeed Insights (mobile):

| Метрика | Цель |
|---------|------|
| First Contentful Paint | < 1.5s |
| Largest Contentful Paint | < 2.5s |
| Cumulative Layout Shift | < 0.1 |
| Total Blocking Time | < 200ms |
| Total JS size | < 50KB (gzip) |
| Total CSS size | < 30KB (gzip) |
| Total page weight | < 500KB (без изображений) |

Средства достижения:
- Критический CSS inline в `<head>` (всё, что видно без скролла)
- Остальной CSS — `<link rel="preload" as="style" onload="this.rel='stylesheet'">`
- JS — defer, в конце body, минимальный объём
- Изображения — WebP, srcset, lazy loading, explicit width/height (предотвращает CLS)
- Шрифты — system font stack (0 KB загрузки)
- Gzip/Brotli на nginx

---

## Порядок реализации в Claude Code

```
Шаг 1: Создай assets/css/variables.css с полной палитрой (light + dark).
        Создай assets/css/base.css с базовыми стилями.
        Создай inline theme detection script в base.html.twig <head>.

Шаг 2: Реализуй header компонент.
        Desktop: логотип + nav + search + theme toggle.
        Mobile: логотип + burger + search.
        JS: hide-on-scroll-down, show-on-scroll-up.
        JS: theme toggle с cookie.

Шаг 3: Реализуй article layout.
        Шаблон show.html.twig с двухколоночным grid.
        Sidebar: sticky ToC (auto-generated JS).
        Body: типографика для длинного чтения.
        Mobile: ToC как collapsible блок вверху.

Шаг 4: Реализуй article card компонент.
        Hover-эффект, responsive image, category badge.
        Grid layout для listings (1 → 2 → 3 колонки).

Шаг 5: Реализуй tool-widget CSS компонент.
        Все стили форм, input'ов, результатов.
        Skeleton loading для async-данных.
        Анимация появления результата.

Шаг 6: Read Next bar.
        Intersection Observer на конце статьи.
        Sticky bar с ссылкой на следующую статью.

Шаг 7: Inline CTA extension.
        Twig extension вставляющий контекстные CTA в article body.

Шаг 8: Mobile bottom nav для Tools.
        Fixed bottom nav с иконками.
        Показывается только на /tools/* и только < 768px.

Шаг 9: Оптимизация.
        Inline critical CSS.
        Defer non-critical CSS и JS.
        Проверить PageSpeed Insights — цель 90+ mobile.

Шаг 10: Финальная проверка.
         Все страницы проходят тест: header, footer, breadcrumbs,
         meta tags, JSON-LD, canonical, mobile responsive.
         Dark mode работает без мерцания (FOUC).
```
