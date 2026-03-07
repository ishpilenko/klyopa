# MultiSite SEO Platform — Инструкции для Claude Code

## Обзор проекта

Мультисайт платформа для информационных/новостных порталов с заработком на SEO-трафике. Единая кодовая база обслуживает множество сайтов разных вертикалей (crypto, finance, gambling и др.). Контент генерируется и публикуется автоматически через AI-пайплайн (n8n + Claude API).

## Стек технологий

- **PHP 8.4** + **Symfony 7.x** (latest stable)
- **MySQL 8.x** (единая БД, мультисайт через `site_id`)
- **Redis** (кэш, сессии, очереди Symfony Messenger)
- **Docker** (nginx + php-fpm + mysql + redis + n8n)
- **Twig** (серверный рендеринг, никаких SPA-фреймворков на фронте)
- **Vanilla JS** (минимум, только для интерактивных инструментов)
- **n8n** (автоматизация контент-пайплайна)

## Структура директорий проекта

```
multisite-platform/
├── docker/
│   ├── nginx/
│   │   ├── nginx.conf
│   │   └── sites/              # шаблон конфигурации для мультидоменов
│   ├── php/
│   │   └── Dockerfile          # PHP 8.4-fpm + extensions
│   ├── mysql/
│   │   └── my.cnf
│   └── n8n/
│       └── Dockerfile
├── docker-compose.yml
├── docker-compose.override.yml  # dev overrides (xdebug, adminer)
├── src/
│   ├── Entity/                  # Doctrine entities
│   ├── Repository/              # Doctrine repositories
│   ├── EventSubscriber/
│   │   └── SiteResolverSubscriber.php
│   ├── Service/
│   │   ├── SiteContext.php       # текущий сайт (injectable service)
│   │   ├── SeoManager.php
│   │   ├── ContentGenerator.php
│   │   ├── InternalLinker.php
│   │   └── ToolRenderer.php
│   ├── Controller/
│   │   ├── Frontend/            # публичные страницы
│   │   ├── Admin/               # админ-панель
│   │   └── Api/                 # REST API для n8n
│   ├── Twig/
│   │   └── Extension/           # кастомные Twig-расширения (schema, seo)
│   └── Command/                 # CLI-команды (sitemap, cache warmup)
├── templates/
│   ├── base.html.twig           # базовый layout
│   ├── themes/                  # темы оформления по вертикалям
│   │   ├── crypto/
│   │   ├── finance/
│   │   └── gambling/
│   ├── frontend/
│   │   ├── article/
│   │   ├── category/
│   │   ├── tool/
│   │   └── home.html.twig
│   ├── admin/
│   └── components/              # переиспользуемые Twig-компоненты
├── public/
│   ├── index.php
│   └── assets/                  # скомпилированные CSS/JS
├── assets/                      # исходники CSS/JS (Webpack Encore)
├── config/
│   ├── packages/
│   ├── routes/
│   └── services.yaml
├── migrations/                  # Doctrine миграции
├── tests/
├── n8n-workflows/               # экспортированные JSON-воркфлоу n8n
├── CLAUDE.md                    # ← этот файл
└── docs/
    ├── ARCHITECTURE.md
    ├── DATABASE.md
    ├── API.md
    ├── SEO.md
    └── CONTENT_PIPELINE.md
```

## Архитектурные принципы

### Мультисайт через SiteContext

Каждый запрос проходит через `SiteResolverSubscriber` (kernel.request event, priority 255), который:

1. Извлекает Host из запроса
2. Находит `Site` entity по домену (с Redis-кэшированием)
3. Устанавливает `SiteContext` — injectable сервис, доступный везде

```php
// Все репозитории фильтруют по текущему сайту
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private SiteContext $siteContext,
    ) {
        parent::__construct($registry, Article::class);
    }

    public function findPublished(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

### Doctrine Filters (глобальный уровень)

Дополнительно использовать Doctrine SQL Filter для автоматической фильтрации по `site_id` на уровне всех SELECT-запросов:

```php
class SiteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass->implementsInterface(SiteAwareInterface::class)) {
            return '';
        }
        return sprintf('%s.site_id = %s', $targetTableAlias, $this->getParameter('siteId'));
    }
}
```

### Кэширование

Три уровня:
1. **Full page cache** — Redis, ключ = `fpc:{site_id}:{url_hash}`, TTL 5-15 мин для статей, 1 мин для главной. Инвалидация при обновлении контента через Symfony Events.
2. **Fragment cache** — для сайдбаров, меню, виджетов. Twig cache через `HttpCache` или кастомные Twig-расширения.
3. **Query cache** — Doctrine result cache через Redis для тяжёлых запросов.

## Схема базы данных

### Ключевые таблицы

```sql
-- Сайты
CREATE TABLE sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    vertical ENUM('crypto', 'finance', 'gambling', 'general') NOT NULL,
    theme VARCHAR(50) NOT NULL DEFAULT 'default',
    locale VARCHAR(10) NOT NULL DEFAULT 'en',
    default_meta_title VARCHAR(255),
    default_meta_description TEXT,
    analytics_id VARCHAR(50),          -- Google Analytics ID
    search_console_id VARCHAR(255),    -- для API-интеграции
    settings JSON,                      -- произвольные настройки
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_domain (domain),
    INDEX idx_vertical (vertical)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Категории
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    meta_title VARCHAR(255),
    meta_description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_site_slug (site_id, slug),
    INDEX idx_site_active (site_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Статьи
CREATE TABLE articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) NOT NULL,
    excerpt TEXT,
    content LONGTEXT NOT NULL,          -- HTML-контент
    status ENUM('draft', 'review', 'published', 'archived') NOT NULL DEFAULT 'draft',
    schema_type ENUM('Article', 'NewsArticle', 'BlogPosting', 'HowTo', 'FAQPage') NOT NULL DEFAULT 'Article',
    meta_title VARCHAR(255),
    meta_description VARCHAR(320),      -- Google snippet limit
    featured_image_id INT UNSIGNED DEFAULT NULL,
    author_name VARCHAR(255),
    source_url VARCHAR(2048),           -- исходник для AI-контента
    is_ai_generated BOOLEAN NOT NULL DEFAULT FALSE,
    is_evergreen BOOLEAN NOT NULL DEFAULT FALSE,
    word_count INT UNSIGNED DEFAULT 0,
    reading_time_minutes TINYINT UNSIGNED DEFAULT 0,
    published_at DATETIME DEFAULT NULL,
    content_updated_at DATETIME DEFAULT NULL,  -- для отслеживания freshness
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_site_slug (site_id, slug),
    INDEX idx_site_status_published (site_id, status, published_at),
    INDEX idx_site_category (site_id, category_id),
    FULLTEXT INDEX ft_title_content (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Теги
CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_site_slug (site_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_tags (
    article_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Медиафайлы
CREATE TABLE media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    path VARCHAR(500) NOT NULL,
    alt_text VARCHAR(255),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Интерактивные инструменты (калькуляторы и пр.)
CREATE TABLE tools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    type ENUM('calculator', 'converter', 'probability', 'comparison', 'checker') NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    config JSON NOT NULL,               -- конфигурация: поля, формулы, labels
    meta_title VARCHAR(255),
    meta_description VARCHAR(320),
    schema_type VARCHAR(50) DEFAULT 'SoftwareApplication',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_site_slug (site_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Редиректы (для SEO-миграций)
CREATE TABLE redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    source_path VARCHAR(2048) NOT NULL,
    target_path VARCHAR(2048) NOT NULL,
    status_code SMALLINT NOT NULL DEFAULT 301,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_source (site_id, source_path(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Очередь генерации контента
CREATE TABLE content_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    topic VARCHAR(500) NOT NULL,
    keywords JSON,                       -- целевые ключевые слова
    target_category_id INT UNSIGNED DEFAULT NULL,
    target_word_count INT UNSIGNED DEFAULT 1500,
    prompt_template VARCHAR(100),        -- какой шаблон промпта использовать
    status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    result_article_id INT UNSIGNED DEFAULT NULL,
    error_message TEXT,
    priority TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    processed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (target_category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (result_article_id) REFERENCES articles(id) ON DELETE SET NULL,
    INDEX idx_status_priority (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Внутренние ссылки (для управления перелинковкой)
CREATE TABLE internal_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_article_id INT UNSIGNED NOT NULL,
    target_article_id INT UNSIGNED NOT NULL,
    anchor_text VARCHAR(255) NOT NULL,
    is_auto_generated BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (source_article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (target_article_id) REFERENCES articles(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_source_target (source_article_id, target_article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## REST API для n8n

Все эндпоинты требуют Bearer-токен в заголовке `Authorization`.

### Эндпоинты

```
POST   /api/v1/articles              — создать статью (draft)
PUT    /api/v1/articles/{id}         — обновить статью
PATCH  /api/v1/articles/{id}/publish — опубликовать
GET    /api/v1/articles/{id}         — получить статью
GET    /api/v1/articles              — список (фильтры: site_id, status, category)

GET    /api/v1/queue                 — получить задачи из очереди (status=pending)
PATCH  /api/v1/queue/{id}            — обновить статус задачи
POST   /api/v1/queue                 — добавить задачу в очередь

POST   /api/v1/media                 — загрузить медиафайл
GET    /api/v1/sites                 — список сайтов
GET    /api/v1/categories            — список категорий (фильтр: site_id)

POST   /api/v1/tools/suggest-links   — получить рекомендации по внутренним ссылкам для текста
```

### Формат запроса на создание статьи

```json
{
    "site_id": 1,
    "category_id": 3,
    "title": "Bitcoin Price Analysis: Key Levels to Watch",
    "slug": "bitcoin-price-analysis-key-levels",
    "content": "<h2>...</h2><p>...</p>",
    "excerpt": "...",
    "meta_title": "Bitcoin Price Analysis 2025 | Key Support & Resistance",
    "meta_description": "Technical analysis of Bitcoin price...",
    "schema_type": "NewsArticle",
    "tags": ["bitcoin", "price-analysis", "technical-analysis"],
    "is_ai_generated": true,
    "source_url": "https://...",
    "status": "draft"
}
```

## SEO-требования

### URL-структура

```
/{category-slug}/{article-slug}        — статья
/{category-slug}/                      — листинг категории
/tools/{tool-slug}                     — инструмент
/sitemap.xml                           — индексный sitemap
/sitemap-articles-{page}.xml           — постраничные sitemap статей
/sitemap-categories.xml                — sitemap категорий
/sitemap-tools.xml                     — sitemap инструментов
/robots.txt                            — динамический (по site_id)
```

Без дат в URL. Slug — lowercase, только латиница и дефисы.

### Обязательные мета-теги для каждой страницы

- `<title>` — уникальный, до 60 символов
- `<meta name="description">` — уникальный, до 155 символов
- `<link rel="canonical" href="...">` — абсолютный URL
- Open Graph: og:title, og:description, og:image, og:type, og:url
- Twitter Card: twitter:card, twitter:title, twitter:description, twitter:image
- JSON-LD schema markup (тип зависит от страницы)

### Schema Markup (JSON-LD)

Каждая статья должна иметь JSON-LD блок. Тип определяется полем `schema_type`:

```json
{
    "@context": "https://schema.org",
    "@type": "NewsArticle",
    "headline": "...",
    "description": "...",
    "image": "...",
    "datePublished": "...",
    "dateModified": "...",
    "author": { "@type": "Organization", "name": "..." },
    "publisher": { "@type": "Organization", "name": "...", "logo": { ... } },
    "mainEntityOfPage": { "@type": "WebPage", "@id": "..." }
}
```

### Производительность

Цель — PageSpeed Insights 90+ (mobile). Средства:
- Серверный рендеринг (SSR через Twig), без JS-фреймворков
- Full page cache в Redis (5-15 мин TTL)
- Lazy loading для изображений (`loading="lazy"`)
- Критический CSS inline, остальное — async
- Сжатие gzip/brotli на nginx
- Оптимизация изображений (WebP, srcset)
- Preconnect к внешним ресурсам

## Docker-конфигурация

### docker-compose.yml — целевая структура

```yaml
services:
  nginx:
    image: nginx:1.27-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf
      - ./docker/nginx/sites:/etc/nginx/sites
      - ./public:/var/www/public:ro
    depends_on:
      - php

  php:
    build: ./docker/php
    volumes:
      - .:/var/www
    environment:
      DATABASE_URL: "mysql://app:secret@mysql:3306/multisite?charset=utf8mb4"
      REDIS_URL: "redis://redis:6379"
      APP_ENV: "${APP_ENV:-dev}"
      APP_SECRET: "${APP_SECRET}"
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.4
    environment:
      MYSQL_ROOT_PASSWORD: "${MYSQL_ROOT_PASSWORD:-root}"
      MYSQL_DATABASE: multisite
      MYSQL_USER: app
      MYSQL_PASSWORD: "${MYSQL_PASSWORD:-secret}"
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/custom.cnf
    ports:
      - "3306:3306"

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

  n8n:
    image: n8nio/n8n:latest
    ports:
      - "5678:5678"
    environment:
      N8N_BASIC_AUTH_ACTIVE: "true"
      N8N_BASIC_AUTH_USER: "${N8N_USER:-admin}"
      N8N_BASIC_AUTH_PASSWORD: "${N8N_PASSWORD:-admin}"
      WEBHOOK_URL: "http://n8n:5678"
    volumes:
      - n8n_data:/home/node/.n8n

volumes:
  mysql_data:
  redis_data:
  n8n_data:
```

### PHP Dockerfile — необходимые расширения

```dockerfile
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    icu-dev libzip-dev libpng-dev libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql intl opcache zip gd bcmath

RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Redis extension
RUN apk add --no-cache autoconf g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
```

## Правила разработки

### Код

- Следовать Symfony Best Practices
- Строгая типизация: `declare(strict_types=1)` в каждом файле
- PHP 8.4 features: property hooks, asymmetric visibility, где уместно
- Все entity реализуют `SiteAwareInterface` (кроме `Site`)
- Использовать Symfony Attributes для роутинга и валидации
- Events/Subscribers вместо прямых зависимостей между модулями
- Все публичные методы сервисов должны иметь return types
- Комитить и пушить изменения в репозиторий git@github.com:ishpilenko/klyopa.git , тэгировать значимые изменения

### Именование

- Entity: единственное число (`Article`, `Site`, `Category`)
- Таблицы: множественное число (`articles`, `sites`, `categories`)
- URL slugs: kebab-case
- PHP: PSR-12, camelCase для методов и свойств
- Twig-шаблоны: snake_case для имён файлов
- Конфиг: snake_case для параметров

### Безопасность

- API авторизация через Bearer-токены (хранятся в `api_tokens` таблице, привязаны к site_id)
- CSRF-защита для админ-форм
- Rate limiting на API (Symfony RateLimiter)
- Санитизация HTML-контента статей (HTMLPurifier)
- Prepared statements везде (Doctrine делает по умолчанию)
- Content Security Policy headers

### Тестирование

- PHPUnit для юнит-тестов сервисов
- Symfony WebTestCase для функциональных тестов контроллеров
- Фикстуры через DoctrineFixturesBundle (отдельные для каждой вертикали)
- Минимальное покрытие для MVP: SiteResolver, SEO-генерация, API endpoints

## Порядок реализации (фазы)

### Фаза 1: Каркас (начать с этого)
1. `symfony new multisite-platform --webapp`
2. Docker-конфигурация (все сервисы)
3. Doctrine entities + миграции (все таблицы выше)
4. SiteResolverSubscriber + SiteContext service
5. Doctrine SiteFilter
6. Базовый DataFixtures (2-3 сайта, категории)
7. Проверка: запрос по разным доменам возвращает разные site_id

### Фаза 2: Контент + Админка
1. ArticleController (frontend): листинг, деталь, категория
2. Twig-шаблоны: base layout, article, category listing, home
3. Админ-панель (EasyAdmin Bundle или кастом через Symfony UX)
4. CRUD для: sites, categories, articles, tags, media
5. Загрузка и оптимизация изображений (Imagine Bundle)
6. Webpack Encore для ассетов

### Фаза 3: SEO
1. SeoManager service (meta tags, canonical, OG)
2. SchemaMarkup Twig extension (JSON-LD генерация)
3. SitemapController (динамическая генерация XML)
4. RobotsController
5. BreadcrumbService
6. InternalLinker service (автоперелинковка)
7. RedirectSubscriber (обработка 301 редиректов)
8. Full page cache (Redis + EventSubscriber для инвалидации)

### Фаза 4: API + n8n
1. ApiController с Bearer-auth
2. Все REST-эндпоинты (см. выше)
3. ContentQueue entity + management
4. n8n workflows (JSON-файлы в `n8n-workflows/`):
   - topic-discovery.json (RSS + trends → queue)
   - content-generation.json (queue → Claude API → draft)
   - post-processing.json (links, meta, image → publish)
   - content-refresh.json (old articles → update)
5. Промпт-шаблоны для каждой вертикали (хранить в `config/prompts/`)

### Фаза 5: Инструменты
1. ToolController (frontend рендеринг)
2. Tool JSON config schema и валидация
3. JS-движок для калькуляторов (vanilla JS, server-side config)
4. Первые инструменты:
   - Finance: compound interest calculator, loan calculator
   - Crypto: profit/loss calculator, DCA calculator
   - Gambling: odds converter, probability calculator, expected value calc
5. Schema markup SoftwareApplication для инструментов

## n8n Workflows — описание логики

### 1. Topic Discovery (каждые 4 часа)
```
Cron Trigger → [
  HTTP Request (RSS feeds по вертикали) →
  Extract trending topics →
  Deduplicate vs existing articles (API call) →
  POST /api/v1/queue (новые темы)
]
```

### 2. Content Generation (каждые 30 мин, если есть pending)
```
Cron Trigger →
GET /api/v1/queue?status=pending&limit=3 →
For Each: [
  PATCH /api/v1/queue/{id} (status=processing) →
  Claude API call (промпт из шаблона + topic + keywords) →
  Parse response →
  POST /api/v1/articles (status=draft, is_ai_generated=true) →
  PATCH /api/v1/queue/{id} (status=completed, result_article_id)
]
```

### 3. Post-Processing (каждый час)
```
Cron Trigger →
GET /api/v1/articles?status=draft&is_ai_generated=true →
For Each: [
  POST /api/v1/tools/suggest-links (получить рекомендации) →
  Вставить внутренние ссылки в content →
  Сгенерировать meta_title/description (если пустые) →
  PUT /api/v1/articles/{id} →
  PATCH /api/v1/articles/{id}/publish (если auto_publish=true)
]
```

### 4. Content Refresh (раз в неделю)
```
Cron Trigger →
GET /api/v1/articles?status=published&older_than=30d&is_evergreen=true →
For Each: [
  Claude API call (обнови/дополни статью) →
  PUT /api/v1/articles/{id} (обновлённый content) →
  Update content_updated_at
]
```

## Промпт-шаблоны для AI-генерации

Хранить в `config/prompts/{vertical}/` как YAML:

```yaml
# config/prompts/crypto/news_article.yaml
system: |
  You are a professional crypto journalist writing for {site_name}.
  Write in a factual, analytical tone. Avoid hype language.
  Include specific data points, prices, and percentages where relevant.
  Structure with H2 and H3 subheadings. Target {word_count} words.
  Include a TL;DR section at the top.
  Write in {locale} language.

user: |
  Write an article about: {topic}
  Target keywords: {keywords}
  Category: {category_name}

  Requirements:
  - SEO-optimized H1 title (include primary keyword)
  - 3-5 H2 sections
  - At least 2 data points or statistics
  - Conclusion with forward-looking statement
  - Do NOT include meta title/description (generated separately)
```

## Конфигурация вертикалей

Настройки вертикали хранятся в `sites.settings` JSON:

```json
{
    "rss_feeds": [
        "https://cointelegraph.com/rss",
        "https://decrypt.co/feed"
    ],
    "auto_publish": false,
    "default_word_count": 1500,
    "prompt_template": "crypto/news_article",
    "content_refresh_days": 30,
    "max_daily_articles": 10,
    "internal_links_per_article": 5,
    "monetization": {
        "ad_network": "mediavine",
        "ad_positions": ["after_intro", "mid_content", "before_conclusion"]
    }
}
```

## Критически важные замечания

1. **Не использовать JavaScript-фреймворки на фронте** — серверный рендеринг через Twig критичен для SEO и скорости. JS только для интерактивных инструментов.
2. **Redis обязателен** — без кэширования production не выдержит SEO-трафик.
3. **Slug уникальность** — slug уникален в пределах site_id, не глобально.
4. **Каждый SQL-запрос должен фильтровать по site_id** — утечка данных между сайтами = critical bug.
5. **HTML-контент статей очищать через HTMLPurifier** — AI может генерировать невалидный/небезопасный HTML.
6. **Sitemap пагинация** — не более 50,000 URL на файл, разбивать на sitemap index.
7. **Миграции** — писать и up, и down. Не удалять старые миграции.
