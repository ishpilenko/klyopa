# Доработка главной страницы и UI — Инструкции для Claude Code

> Этот файл содержит конкретные задачи по исправлению текущих проблем и добавлению функциональности. 
> Работай по порядку секций — они отсортированы по приоритету.
> Скриншот текущего состояния: загляни в контекст разговора для визуальной референсии.

---

## Приоритет 1: Исправления текущих проблем

### 1.1 Блок быстрых ссылок (Tools shortcuts)

**Проблема:** Текст в карточках обрезается многоточием ("Investment Cal...", "BTC to USD" с "Live Bitcoin price co...", "Fear & Greed In..."). Иконки разнородные и мелкие. Карточки не показывают hover — непонятно, что кликабельные. Вторая строка (Crypto Glossary) висит одиноко слева.

**Решение:**

```twig
{# templates/frontend/home/_tools_shortcuts.html.twig #}

<section class="tools-shortcuts">
    <div class="tools-shortcuts-grid">
        {% for tool in tools %}
        <a href="{{ tool.url }}" class="tool-shortcut-card">
            <div class="tool-shortcut-icon">
                {# Единообразные SVG-иконки, 24x24, одного стиля (outline) #}
                {{ tool.icon|raw }}
            </div>
            <div class="tool-shortcut-content">
                <span class="tool-shortcut-name">{{ tool.name }}</span>
                {# Описание короткое, без обрезки. Макс 40 символов #}
                <span class="tool-shortcut-desc">{{ tool.shortDesc }}</span>
            </div>
        </a>
        {% endfor %}
    </div>
</section>
```

```css
.tools-shortcuts {
    padding: var(--space-6) 0;
    border-bottom: 1px solid var(--color-border-light);
}

.tools-shortcuts-grid {
    display: flex;
    gap: var(--space-3);
    overflow-x: auto;          /* горизонтальный скролл на мобилке */
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    padding-bottom: var(--space-2);  /* место для скроллбара */
    scrollbar-width: none;     /* скрыть скроллбар Firefox */
}
.tools-shortcuts-grid::-webkit-scrollbar { display: none; }

.tool-shortcut-card {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border-light);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: inherit;
    white-space: nowrap;       /* не переносить текст */
    scroll-snap-align: start;
    flex-shrink: 0;            /* не сжиматься */
    transition: all var(--transition-fast);
}

.tool-shortcut-card:hover {
    border-color: var(--color-accent);
    background: var(--color-accent-light);
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.tool-shortcut-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-bg-secondary);
    border-radius: var(--radius-md);
    color: var(--color-accent);
    flex-shrink: 0;
}

.tool-shortcut-icon svg {
    width: 20px;
    height: 20px;
}

.tool-shortcut-content {
    display: flex;
    flex-direction: column;
}

.tool-shortcut-name {
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-text-primary);
}

.tool-shortcut-desc {
    font-size: var(--text-xs);
    color: var(--color-text-tertiary);
}
```

**Данные для карточек (контроллер):**

```php
$tools = [
    ['name' => 'Converter', 'shortDesc' => 'BTC, ETH & 100+ coins', 'url' => '/tools/converter', 'icon' => '<svg>...arrows-exchange...</svg>'],
    ['name' => 'Live Prices', 'shortDesc' => 'Top 200 coins', 'url' => '/prices', 'icon' => '<svg>...chart-line...</svg>'],
    ['name' => 'Profit Calculator', 'shortDesc' => 'Calculate P&L', 'url' => '/tools/crypto-profit-calculator', 'icon' => '<svg>...calculator...</svg>'],
    ['name' => 'DCA Calculator', 'shortDesc' => 'Dollar cost averaging', 'url' => '/tools/dca-calculator', 'icon' => '<svg>...trending-up...</svg>'],
    ['name' => 'Fear & Greed', 'shortDesc' => 'Market sentiment', 'url' => '/fear-greed-index', 'icon' => '<svg>...gauge...</svg>'],
    ['name' => 'Glossary', 'shortDesc' => knowledge_count ~ ' terms', 'url' => '/glossary', 'icon' => '<svg>...book-open...</svg>'],
];
```

### 1.2 Ticker цен в header

**Проблема:** Бейджи монет слипаются, плохая читабельность, на мобилке всё сжимается.

**Решение:** Сделать ticker горизонтально скроллируемым с автопрокруткой (CSS animation, pauseable on hover).

```html
<div class="price-ticker">
    <div class="price-ticker-track" id="price-ticker-track">
        {% for coin in tickerCoins %}
        <a href="{{ path('price_show', {slug: coin.slug}) }}" class="ticker-item">
            <img src="{{ coin.iconUrl }}" alt="" width="16" height="16" class="ticker-icon">
            <span class="ticker-symbol">{{ coin.symbol }}</span>
            <span class="ticker-price">${{ coin.price|number_format(2) }}</span>
            <span class="ticker-change {{ coin.change24h >= 0 ? 'positive' : 'negative' }}">
                {{ coin.change24h >= 0 ? '+' : '' }}{{ coin.change24h|number_format(2) }}%
            </span>
        </a>
        {% endfor %}
        {# Дублируем для бесшовной прокрутки #}
        {% for coin in tickerCoins %}
        <a href="{{ path('price_show', {slug: coin.slug}) }}" class="ticker-item" aria-hidden="true">
            <img src="{{ coin.iconUrl }}" alt="" width="16" height="16" class="ticker-icon">
            <span class="ticker-symbol">{{ coin.symbol }}</span>
            <span class="ticker-price">${{ coin.price|number_format(2) }}</span>
            <span class="ticker-change {{ coin.change24h >= 0 ? 'positive' : 'negative' }}">
                {{ coin.change24h >= 0 ? '+' : '' }}{{ coin.change24h|number_format(2) }}%
            </span>
        </a>
        {% endfor %}
    </div>
</div>
```

```css
.price-ticker {
    background: var(--color-bg-primary);
    border-bottom: 1px solid var(--color-border-light);
    overflow: hidden;
    padding: var(--space-2) 0;
}

.price-ticker-track {
    display: flex;
    gap: var(--space-5);
    animation: ticker-scroll 40s linear infinite;
    width: max-content;
}

.price-ticker:hover .price-ticker-track {
    animation-play-state: paused;
}

@keyframes ticker-scroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); } /* -50% потому что контент дублирован */
}

.ticker-item {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    text-decoration: none;
    color: inherit;
    font-size: var(--text-sm);
    white-space: nowrap;
    padding: var(--space-1) 0;
}

.ticker-item:hover {
    text-decoration: none;
}

.ticker-icon {
    width: 16px;
    height: 16px;
    border-radius: var(--radius-full);
}

.ticker-symbol {
    font-weight: 600;
    color: var(--color-text-primary);
}

.ticker-price {
    color: var(--color-text-secondary);
}

.ticker-change {
    font-weight: 500;
    font-size: var(--text-xs);
}
.ticker-change.positive { color: var(--color-positive); }
.ticker-change.negative { color: var(--color-negative); }
```

### 1.3 Карточки статей — изображения

**Проблема:** Бежевые placeholder-изображения выглядят как баг.

**Решение:** Генерировать CSS-based placeholder с градиентом и категорией, пока нет реальных изображений.

```twig
{# templates/components/article_card.html.twig #}

<a href="{{ path('article_show', {...}) }}" class="article-card">
    <div class="article-card-visual">
        {% if article.featuredImage %}
            <img src="{{ article.featuredImage.path }}" 
                 alt="{{ article.title }}"
                 loading="lazy" width="400" height="225"
                 class="article-card-image">
        {% else %}
            {# CSS placeholder вместо пустой картинки #}
            <div class="article-card-placeholder" 
                 data-category="{{ article.category.slug }}">
                <span class="article-card-placeholder-label">
                    {{ article.category.name }}
                </span>
            </div>
        {% endif %}
    </div>
    <div class="article-card-body">
        <span class="article-card-category">{{ article.category.name }}</span>
        <h3 class="article-card-title">{{ article.title }}</h3>
        <p class="article-card-excerpt">{{ article.excerpt|u.truncate(120, '...') }}</p>
        <div class="article-card-meta">
            <time datetime="{{ article.publishedAt|date('Y-m-d') }}">
                {{ article.publishedAt|date('M d, Y') }}
            </time>
            <span>{{ article.readingTimeMinutes }} min read</span>
        </div>
    </div>
</a>
```

```css
.article-card-visual {
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: var(--radius-md) var(--radius-md) 0 0;
}

.article-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform var(--transition-slow);
}

.article-card:hover .article-card-image {
    transform: scale(1.03);
}

/* CSS placeholder по категориям — разные градиенты */
.article-card-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.article-card-placeholder[data-category="bitcoin-news"],
.article-card-placeholder[data-category="bitcoin"] {
    background: linear-gradient(135deg, #f7931a 0%, #c76b0a 100%);
}

.article-card-placeholder[data-category="ethereum-news"],
.article-card-placeholder[data-category="ethereum"] {
    background: linear-gradient(135deg, #627eea 0%, #3c4fa0 100%);
}

.article-card-placeholder[data-category="defi-news"],
.article-card-placeholder[data-category="defi"] {
    background: linear-gradient(135deg, #00d395 0%, #007a56 100%);
}

.article-card-placeholder[data-category="market-analysis"] {
    background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
}

.article-card-placeholder[data-category="regulation"] {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
}

/* Default fallback */
.article-card-placeholder {
    background: linear-gradient(135deg, #475569 0%, #1e293b 100%);
}

.article-card-placeholder-label {
    font-size: var(--text-sm);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.5);
}
```

### 1.4 Sidebar — убрать пустой рекламный блок

**Проблема:** Пустой серый прямоугольник с "Advertisement" убивает доверие.

**Решение:** Убрать его полностью. Заменить полезными виджетами.

```twig
{# templates/frontend/home/_sidebar.html.twig #}

{# 1. Categories — с количеством статей и иконкой #}
<div class="sidebar-widget">
    <h3 class="sidebar-widget-title">Categories</h3>
    <nav class="sidebar-categories">
        {% for category in categories %}
        <a href="{{ path('article_category', {slug: category.slug}) }}" 
           class="sidebar-category-link">
            <span>{{ category.name }}</span>
            <span class="sidebar-category-count">{{ category.articleCount }}</span>
        </a>
        {% endfor %}
    </nav>
</div>

{# 2. Trending — Most Read за неделю #}
<div class="sidebar-widget">
    <h3 class="sidebar-widget-title">Trending</h3>
    <ol class="sidebar-trending">
        {% for i, article in trendingArticles %}
        <li class="sidebar-trending-item">
            <span class="sidebar-trending-num">{{ i + 1 }}</span>
            <a href="{{ path('article_show', {...}) }}" class="sidebar-trending-link">
                {{ article.title }}
            </a>
        </li>
        {% endfor %}
    </ol>
</div>

{# 3. Crypto Tools (вместо Advertisement) #}
<div class="sidebar-widget">
    <h3 class="sidebar-widget-title">Crypto Tools</h3>
    <nav class="sidebar-tools-list">
        <a href="{{ path('price_index') }}">Live Crypto Prices</a>
        <a href="{{ path('converter_index') }}">Crypto Converter</a>
        <a href="{{ path('tool_profit_calculator') }}">Profit Calculator</a>
        <a href="{{ path('tool_dca_calculator') }}">DCA Calculator</a>
        <a href="{{ path('fear_greed_index') }}">Fear & Greed Index</a>
    </nav>
</div>

{# 4. Newsletter signup (вместо Ad placeholder) #}
<div class="sidebar-widget sidebar-newsletter">
    <h3 class="sidebar-widget-title">Daily Crypto Brief</h3>
    <p class="sidebar-newsletter-desc">
        Market highlights & analysis, every morning.
    </p>
    <div class="sidebar-newsletter-form">
        <input type="email" placeholder="Your email" class="tool-input">
        <button class="tool-btn" style="margin-top: var(--space-2);">Subscribe</button>
    </div>
    <p class="sidebar-newsletter-note">Free. No spam. Unsubscribe anytime.</p>
</div>
```

```css
.sidebar-widget {
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border-light);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    margin-bottom: var(--space-5);
}

.sidebar-widget-title {
    font-size: var(--text-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-tertiary);
    margin-bottom: var(--space-4);
    padding-bottom: var(--space-3);
    border-bottom: 1px solid var(--color-border-light);
}

/* Categories с счётчиком */
.sidebar-category-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-2) 0;
    color: var(--color-text-primary);
    text-decoration: none;
    font-size: var(--text-sm);
    border-bottom: 1px solid var(--color-border-light);
    transition: color var(--transition-fast);
}

.sidebar-category-link:last-child { border-bottom: none; }
.sidebar-category-link:hover { color: var(--color-accent); text-decoration: none; }

.sidebar-category-count {
    font-size: var(--text-xs);
    color: var(--color-text-tertiary);
    background: var(--color-bg-secondary);
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-full);
}

/* Trending — нумерованный список */
.sidebar-trending {
    list-style: none;
    padding: 0;
    counter-reset: none;
}

.sidebar-trending-item {
    display: flex;
    gap: var(--space-3);
    padding: var(--space-3) 0;
    border-bottom: 1px solid var(--color-border-light);
}

.sidebar-trending-item:last-child { border-bottom: none; }

.sidebar-trending-num {
    font-size: var(--text-2xl);
    font-weight: 800;
    color: var(--color-border);
    line-height: 1;
    min-width: 28px;
}

.sidebar-trending-link {
    font-size: var(--text-sm);
    font-weight: 500;
    color: var(--color-text-primary);
    text-decoration: none;
    line-height: var(--leading-tight);
}

.sidebar-trending-link:hover {
    color: var(--color-accent);
    text-decoration: none;
}

/* Newsletter */
.sidebar-newsletter {
    background: var(--color-accent-light);
    border-color: var(--color-accent-subtle);
}

.sidebar-newsletter-desc {
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    margin-bottom: var(--space-3);
}

.sidebar-newsletter-note {
    font-size: var(--text-xs);
    color: var(--color-text-tertiary);
    margin-top: var(--space-2);
}
```

---

## Приоритет 2: Новые секции на главной

### 2.1 Featured Article (Hero)

Вставить перед блоком tools shortcuts. Одна крупная статья + 4 заголовка рядом.

```twig
{# templates/frontend/home/_hero.html.twig #}

<section class="home-hero">
    <div class="home-hero-grid">
        {# Главная статья — крупная #}
        <a href="{{ path('article_show', {...}) }}" class="hero-featured">
            <div class="hero-featured-visual">
                {% if featuredArticle.featuredImage %}
                    <img src="{{ featuredArticle.featuredImage.path }}" 
                         alt="{{ featuredArticle.title }}"
                         width="800" height="450">
                {% else %}
                    <div class="article-card-placeholder" 
                         data-category="{{ featuredArticle.category.slug }}">
                        <span class="article-card-placeholder-label">
                            {{ featuredArticle.category.name }}
                        </span>
                    </div>
                {% endif %}
            </div>
            <div class="hero-featured-body">
                <span class="article-card-category">{{ featuredArticle.category.name }}</span>
                <h2 class="hero-featured-title">{{ featuredArticle.title }}</h2>
                <p class="hero-featured-excerpt">{{ featuredArticle.excerpt|u.truncate(180, '...') }}</p>
                <div class="article-card-meta">
                    <time>{{ featuredArticle.publishedAt|date('M d, Y') }}</time>
                    <span>{{ featuredArticle.readingTimeMinutes }} min read</span>
                </div>
            </div>
        </a>

        {# Боковой список — 4-5 заголовков #}
        <div class="hero-sidebar-list">
            {% for article in sidebarArticles %}
            <a href="{{ path('article_show', {...}) }}" class="hero-sidebar-item">
                <span class="hero-sidebar-category">{{ article.category.name }}</span>
                <h3 class="hero-sidebar-title">{{ article.title }}</h3>
                <time class="hero-sidebar-time">{{ article.publishedAt|date('M d') }}</time>
            </a>
            {% endfor %}
        </div>
    </div>
</section>
```

```css
.home-hero {
    padding: var(--space-6) 0;
    border-bottom: 1px solid var(--color-border-light);
}

.home-hero-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-5);
}

@media (min-width: 768px) {
    .home-hero-grid {
        grid-template-columns: 3fr 2fr;
        gap: var(--space-6);
    }
}

/* Featured article */
.hero-featured {
    display: block;
    text-decoration: none;
    color: inherit;
}

.hero-featured:hover { text-decoration: none; }

.hero-featured-visual {
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-4);
}

.hero-featured-visual img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform var(--transition-slow);
}

.hero-featured:hover img {
    transform: scale(1.02);
}

.hero-featured-title {
    font-size: var(--text-3xl);
    font-weight: 700;
    line-height: var(--leading-tight);
    margin: var(--space-2) 0 var(--space-3);
    color: var(--color-text-primary);
    letter-spacing: -0.01em;
}

.hero-featured-excerpt {
    font-size: var(--text-base);
    color: var(--color-text-secondary);
    line-height: var(--leading-normal);
    margin-bottom: var(--space-3);
}

/* Sidebar list */
.hero-sidebar-list {
    display: flex;
    flex-direction: column;
}

.hero-sidebar-item {
    display: block;
    padding: var(--space-4) 0;
    border-bottom: 1px solid var(--color-border-light);
    text-decoration: none;
    color: inherit;
    transition: background var(--transition-fast);
}

.hero-sidebar-item:first-child { padding-top: 0; }
.hero-sidebar-item:last-child { border-bottom: none; }
.hero-sidebar-item:hover { text-decoration: none; }

.hero-sidebar-category {
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-accent);
}

.hero-sidebar-title {
    font-size: var(--text-base);
    font-weight: 600;
    line-height: var(--leading-tight);
    margin: var(--space-1) 0;
    color: var(--color-text-primary);
    transition: color var(--transition-fast);
}

.hero-sidebar-item:hover .hero-sidebar-title {
    color: var(--color-accent);
}

.hero-sidebar-time {
    font-size: var(--text-xs);
    color: var(--color-text-tertiary);
}
```

### 2.2 Market Overview (мини-таблица топ-10 монет)

Вставить после tools shortcuts, перед Latest Articles.

```twig
{# templates/frontend/home/_market_overview.html.twig #}

<section class="market-overview">
    <div class="section-header">
        <h2 class="section-title">Market Overview</h2>
        <a href="{{ path('price_index') }}" class="section-link">View all →</a>
    </div>
    
    <div class="market-table-wrapper">
        <table class="market-table">
            <thead>
                <tr>
                    <th class="market-th-rank">#</th>
                    <th class="market-th-name">Name</th>
                    <th class="market-th-price">Price</th>
                    <th class="market-th-change">24h</th>
                    <th class="market-th-change">7d</th>
                    <th class="market-th-cap">Market Cap</th>
                    <th class="market-th-chart">7d Chart</th>
                </tr>
            </thead>
            <tbody>
                {% for coin in topCoins %}
                <tr class="market-row" onclick="window.location='{{ path('price_show', {slug: coin.slug}) }}'">
                    <td class="market-rank">{{ coin.rank }}</td>
                    <td class="market-name">
                        <img src="{{ coin.iconUrl }}" alt="" width="24" height="24" 
                             class="market-coin-icon" loading="lazy">
                        <span class="market-coin-name">{{ coin.name }}</span>
                        <span class="market-coin-symbol">{{ coin.symbol }}</span>
                    </td>
                    <td class="market-price">${{ coin.price|number_format(2) }}</td>
                    <td class="market-change {{ coin.change24h >= 0 ? 'positive' : 'negative' }}">
                        {{ coin.change24h >= 0 ? '+' : '' }}{{ coin.change24h|number_format(2) }}%
                    </td>
                    <td class="market-change {{ coin.change7d >= 0 ? 'positive' : 'negative' }}">
                        {{ coin.change7d >= 0 ? '+' : '' }}{{ coin.change7d|number_format(2) }}%
                    </td>
                    <td class="market-cap">${{ coin.marketCap|abbreviate }}</td>
                    <td class="market-sparkline">
                        {# Мини-SVG sparkline из 7-дневных данных #}
                        {{ include('components/sparkline.html.twig', {data: coin.sparkline7d, positive: coin.change7d >= 0}) }}
                    </td>
                </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>
</section>
```

```css
.market-overview {
    padding: var(--space-8) 0;
    border-bottom: 1px solid var(--color-border-light);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: var(--space-5);
}

.section-title {
    font-size: var(--text-2xl);
    font-weight: 700;
}

.section-link {
    font-size: var(--text-sm);
    font-weight: 500;
}

.market-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.market-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--text-sm);
}

.market-table th {
    text-align: left;
    padding: var(--space-3) var(--space-3);
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-tertiary);
    border-bottom: 2px solid var(--color-border-light);
    white-space: nowrap;
}

.market-table th.market-th-price,
.market-table th.market-th-change,
.market-table th.market-th-cap {
    text-align: right;
}

.market-row {
    cursor: pointer;
    transition: background var(--transition-fast);
}

.market-row:hover {
    background: var(--color-bg-secondary);
}

.market-row td {
    padding: var(--space-3);
    border-bottom: 1px solid var(--color-border-light);
    white-space: nowrap;
}

.market-rank {
    color: var(--color-text-tertiary);
    font-weight: 500;
    width: 30px;
}

.market-name {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-weight: 600;
}

.market-coin-icon {
    border-radius: var(--radius-full);
}

.market-coin-symbol {
    color: var(--color-text-tertiary);
    font-weight: 400;
    text-transform: uppercase;
    font-size: var(--text-xs);
}

.market-price {
    text-align: right;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.market-change {
    text-align: right;
    font-weight: 500;
    font-variant-numeric: tabular-nums;
}

.market-change.positive { color: var(--color-positive); }
.market-change.negative { color: var(--color-negative); }

.market-cap {
    text-align: right;
    color: var(--color-text-secondary);
    font-variant-numeric: tabular-nums;
}

.market-sparkline {
    width: 100px;
    padding: var(--space-1) 0;
}

/* Скрыть некоторые колонки на мобилке */
@media (max-width: 640px) {
    .market-th-change:nth-child(5),
    .market-row td:nth-child(5),
    .market-th-cap,
    .market-row td:nth-child(6),
    .market-th-chart,
    .market-row td:nth-child(7) {
        display: none;
    }
}
```

### Sparkline компонент (мини-SVG график)

```twig
{# templates/components/sparkline.html.twig #}
{# data: массив из ~168 точек (7 дней × 24 часа) #}
{# positive: bool — зелёный или красный цвет #}

{% set width = 100 %}
{% set height = 32 %}
{% set min = data|min %}
{% set max = data|max %}
{% set range = max - min > 0 ? max - min : 1 %}
{% set step = width / (data|length - 1) %}
{% set color = positive ? 'var(--color-positive)' : 'var(--color-negative)' %}

{% set points = '' %}
{% for i, val in data %}
    {% set x = i * step %}
    {% set y = height - ((val - min) / range * (height - 4)) - 2 %}
    {% set points = points ~ x ~ ',' ~ y ~ ' ' %}
{% endfor %}

<svg width="{{ width }}" height="{{ height }}" viewBox="0 0 {{ width }} {{ height }}" 
     class="sparkline-svg" preserveAspectRatio="none">
    <polyline fill="none" stroke="{{ color }}" stroke-width="1.5" 
              stroke-linecap="round" stroke-linejoin="round"
              points="{{ points|trim }}"/>
</svg>
```

---

## Приоритет 3: Новая структура Latest Articles

### 2.3 Больше контента в видимой области

Вместо двух карточек в ряд — featured + компактный список.

```twig
{# templates/frontend/home/_latest_articles.html.twig #}

<section class="latest-articles">
    <div class="section-header">
        <h2 class="section-title">Latest Articles</h2>
        <a href="{{ path('article_index') }}" class="section-link">View all →</a>
    </div>

    <div class="articles-layout">
        {# Main column #}
        <div class="articles-main">
            {# Первые 2 — крупные карточки с картинками #}
            <div class="articles-featured-grid">
                {% for article in articles[:2] %}
                    {{ include('components/article_card.html.twig', {article: article}) }}
                {% endfor %}
            </div>
            
            {# Остальные — компактные горизонтальные карточки #}
            <div class="articles-compact-list">
                {% for article in articles[2:6] %}
                <a href="{{ path('article_show', {...}) }}" class="article-compact">
                    <div class="article-compact-body">
                        <span class="article-card-category">{{ article.category.name }}</span>
                        <h3 class="article-compact-title">{{ article.title }}</h3>
                        <div class="article-card-meta">
                            <time>{{ article.publishedAt|date('M d, Y') }}</time>
                            <span>{{ article.readingTimeMinutes }} min read</span>
                        </div>
                    </div>
                </a>
                {% endfor %}
            </div>
        </div>

        {# Sidebar #}
        <aside class="articles-sidebar">
            {{ include('frontend/home/_sidebar.html.twig') }}
        </aside>
    </div>
</section>
```

```css
.articles-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-8);
}

@media (min-width: 1024px) {
    .articles-layout {
        grid-template-columns: 1fr 300px;
    }
}

.articles-featured-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-5);
    margin-bottom: var(--space-6);
}

@media (min-width: 640px) {
    .articles-featured-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Компактная карточка — горизонтальная */
.article-compact {
    display: flex;
    gap: var(--space-4);
    padding: var(--space-4) 0;
    border-bottom: 1px solid var(--color-border-light);
    text-decoration: none;
    color: inherit;
    transition: background var(--transition-fast);
}

.article-compact:last-child { border-bottom: none; }
.article-compact:hover { text-decoration: none; }

.article-compact-title {
    font-size: var(--text-base);
    font-weight: 600;
    line-height: var(--leading-tight);
    margin: var(--space-1) 0 var(--space-2);
    color: var(--color-text-primary);
    transition: color var(--transition-fast);
}

.article-compact:hover .article-compact-title {
    color: var(--color-accent);
}
```

---

## Приоритет 4: Footer и Search

### Footer

```twig
{# templates/components/footer.html.twig #}

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            {# Brand column #}
            <div class="footer-brand">
                <a href="{{ path('home') }}" class="footer-logo">{{ site.name }}</a>
                <p class="footer-tagline">In-depth cryptocurrency news, analysis, and tools.</p>
            </div>

            {# Navigation columns #}
            <div class="footer-column">
                <h4 class="footer-column-title">Content</h4>
                <nav class="footer-nav">
                    <a href="{{ path('article_category', {slug: 'bitcoin-news'}) }}">Bitcoin News</a>
                    <a href="{{ path('article_category', {slug: 'ethereum-news'}) }}">Ethereum News</a>
                    <a href="{{ path('article_category', {slug: 'defi-news'}) }}">DeFi News</a>
                    <a href="{{ path('article_category', {slug: 'market-analysis'}) }}">Market Analysis</a>
                    <a href="{{ path('glossary_index') }}">Crypto Glossary</a>
                </nav>
            </div>

            <div class="footer-column">
                <h4 class="footer-column-title">Tools</h4>
                <nav class="footer-nav">
                    <a href="{{ path('price_index') }}">Live Prices</a>
                    <a href="{{ path('converter_index') }}">Converter</a>
                    <a href="{{ path('tool_profit_calculator') }}">Profit Calculator</a>
                    <a href="{{ path('tool_dca_calculator') }}">DCA Calculator</a>
                    <a href="{{ path('fear_greed_index') }}">Fear & Greed Index</a>
                </nav>
            </div>

            <div class="footer-column">
                <h4 class="footer-column-title">Company</h4>
                <nav class="footer-nav">
                    <a href="/about">About</a>
                    <a href="/editorial-policy">Editorial Policy</a>
                    <a href="/privacy-policy">Privacy Policy</a>
                    <a href="/terms">Terms of Use</a>
                    <a href="/contact">Contact</a>
                    <a href="/sitemap.xml">Sitemap</a>
                </nav>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="footer-disclaimer">
                The information provided on {{ site.name }} is for educational purposes only 
                and should not be considered financial advice. Always do your own research 
                before making investment decisions.
            </p>
            <p class="footer-copyright">
                © {{ 'now'|date('Y') }} {{ site.name }}. All rights reserved.
            </p>
        </div>
    </div>
</footer>
```

```css
.site-footer {
    background: var(--color-bg-secondary);
    border-top: 1px solid var(--color-border-light);
    margin-top: var(--space-16);
    padding: var(--space-12) var(--space-4) var(--space-6);
}

.footer-inner {
    max-width: var(--max-width-wide);
    margin: 0 auto;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-8);
}

@media (min-width: 640px) {
    .footer-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 1024px) {
    .footer-grid { grid-template-columns: 2fr 1fr 1fr 1fr; }
}

.footer-logo {
    font-size: var(--text-xl);
    font-weight: 700;
    color: var(--color-text-primary);
    text-decoration: none;
}

.footer-tagline {
    font-size: var(--text-sm);
    color: var(--color-text-tertiary);
    margin-top: var(--space-2);
}

.footer-column-title {
    font-size: var(--text-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-tertiary);
    margin-bottom: var(--space-3);
}

.footer-nav {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.footer-nav a {
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    text-decoration: none;
    transition: color var(--transition-fast);
}

.footer-nav a:hover {
    color: var(--color-accent);
}

.footer-bottom {
    margin-top: var(--space-10);
    padding-top: var(--space-6);
    border-top: 1px solid var(--color-border);
}

.footer-disclaimer {
    font-size: var(--text-xs);
    color: var(--color-text-tertiary);
    line-height: var(--leading-relaxed);
    margin-bottom: var(--space-3);
    max-width: 700px;
}

.footer-copyright {
    font-size: var(--text-xs);
    color: var(--color-text-tertiary);
}
```

### Search Overlay

```twig
{# В header — кнопка поиска уже есть, нужен overlay #}
<div class="search-overlay" id="search-overlay" hidden>
    <div class="search-overlay-inner">
        <div class="search-input-wrapper">
            <svg class="search-input-icon">...</svg>
            <input type="search" 
                   id="search-input" 
                   placeholder="Search articles, coins, tools..."
                   autocomplete="off"
                   autofocus>
            <kbd class="search-kbd">ESC</kbd>
        </div>
        <div class="search-results" id="search-results">
            {# Заполняется через JS #}
        </div>
    </div>
</div>
```

```javascript
// assets/js/search.js

const overlay = document.getElementById('search-overlay');
const input = document.getElementById('search-input');
const results = document.getElementById('search-results');

// Открыть: кнопка или Ctrl+K
document.getElementById('search-toggle')?.addEventListener('click', openSearch);
document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        openSearch();
    }
    if (e.key === 'Escape') closeSearch();
});

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

// Debounced search
let timer;
input?.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const q = input.value.trim();
        if (q.length < 2) { results.innerHTML = ''; return; }
        
        fetch(`/api/v1/search?q=${encodeURIComponent(q)}&limit=8`)
            .then(r => r.json())
            .then(data => renderResults(data))
            .catch(() => {});
    }, 250);
});

function renderResults(data) {
    if (!data.length) {
        results.innerHTML = '<div class="search-empty">No results found</div>';
        return;
    }
    results.innerHTML = data.map(item => `
        <a href="${item.url}" class="search-result-item">
            <span class="search-result-type">${item.type}</span>
            <span class="search-result-title">${item.title}</span>
        </a>
    `).join('');
}

// Закрыть при клике на backdrop
overlay?.addEventListener('click', (e) => {
    if (e.target === overlay) closeSearch();
});
```

**API эндпоинт:**

```php
// src/Controller/Api/SearchApiController.php

#[Route('/api/v1/search', name: 'api_search', methods: ['GET'])]
public function search(Request $request): JsonResponse
{
    $query = $request->query->get('q', '');
    $limit = min($request->query->getInt('limit', 8), 20);

    // Поиск по: articles (FULLTEXT), coin_pages (name, symbol), glossary_terms (term)
    // Объединить результаты, отсортировать по relevance
    // Каждый результат: {type: 'article'|'coin'|'term'|'tool', title: '...', url: '...'}

    return $this->json($results);
}
```

---

## Приоритет 5: Кастомная 404 страница

```twig
{# templates/bundles/TwigBundle/Exception/error404.html.twig #}

{% extends 'base.html.twig' %}

{% block title %}Page Not Found | {{ site.name }}{% endblock %}

{% block body %}
<div class="error-page">
    <h1 class="error-code">404</h1>
    <p class="error-message">This page doesn't exist or has been moved.</p>
    
    <div class="error-search">
        <input type="search" placeholder="Search for what you need..." 
               class="tool-input" id="error-search-input">
    </div>
    
    <div class="error-suggestions">
        <h2>Popular pages</h2>
        <div class="error-links">
            <a href="{{ path('price_index') }}">Live Crypto Prices</a>
            <a href="{{ path('article_category', {slug: 'bitcoin-news'}) }}">Bitcoin News</a>
            <a href="{{ path('converter_index') }}">Crypto Converter</a>
            <a href="{{ path('glossary_index') }}">Crypto Glossary</a>
            <a href="{{ path('tools_index') }}">All Tools</a>
        </div>
    </div>
</div>
{% endblock %}
```

---

## Структура главной страницы (итоговый порядок секций)

```
1. [Header] — логотип, навигация, поиск, dark mode toggle
2. [Price Ticker] — автопрокручивающаяся лента цен
3. [Hero] — featured article + 4 заголовка сбоку
4. [Tools Shortcuts] — горизонтальный скролл карточек инструментов
5. [Market Overview] — таблица топ-10 монет с sparkline
6. [Latest Articles + Sidebar]
   - Main: 2 крупные карточки + 4 компактных
   - Sidebar: Categories, Trending, Crypto Tools, Newsletter signup
7. [Footer] — навигация, disclaimer, copyright
```

---

## Порядок реализации в Claude Code

```
Шаг 1:  Исправь карточки инструментов (tools shortcuts).
         Убери обрезку текста, добавь hover, выровняй в один ряд со скроллом.

Шаг 2:  Исправь ticker — сделай автопрокрутку (CSS animation),
         паузу на hover, чистый вид без рамок.

Шаг 3:  Исправь карточки статей — CSS-placeholder вместо бежевых картинок,
         hover-эффект, мета внизу.

Шаг 4:  Замени sidebar: убери Advertisement, добавь Trending, Newsletter.
         Categories — добавь счётчик статей.

Шаг 5:  Добавь Hero-секцию (featured article + sidebar list).
         Поставь перед tools shortcuts.

Шаг 6:  Добавь Market Overview таблицу с sparkline.
         Поставь после tools shortcuts.

Шаг 7:  Перестрой Latest Articles: 2 featured + 4 compact + sidebar.

Шаг 8:  Добавь Footer с навигацией, disclaimer, copyright.

Шаг 9:  Добавь Search overlay (Ctrl+K / кнопка).
         Создай API эндпоинт /api/v1/search.

Шаг 10: Создай кастомную 404 страницу.

Шаг 11: Проверь всё на мобилке (375px ширина).
         Ticker скроллится, таблица скроллится, 
         карточки в одну колонку, sidebar под контентом.
```
