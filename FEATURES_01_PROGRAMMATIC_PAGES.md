# Программные SEO-страницы — Инструкции для Claude Code

> Этот файл описывает реализацию автоматически генерируемых страниц: конвертер валют, страницы цен монет, исторический калькулятор инвестиций. Эти страницы покрывают миллионы long-tail запросов при минимуме ручной работы.

## Общие требования

- Все страницы серверно рендерятся через Twig (SSR критичен для SEO)
- Данные о ценах — из CoinGecko API v3 (бесплатный тир, без ключа для базовых запросов)
- Кэширование ответов API в Redis (TTL 60–300 сек в зависимости от типа данных)
- Каждая страница имеет уникальный meta title, description, canonical URL, JSON-LD schema
- Все страницы привязаны к site_id через SiteContext

## Источник данных: CoinGecko API

### Базовый URL
```
https://api.coingecko.com/api/v3/
```

### Используемые эндпоинты

```
GET /coins/list                          — список всех монет (id, symbol, name)
GET /simple/price?ids=bitcoin&vs_currencies=usd,eur  — текущие цены
GET /coins/{id}                          — полная информация о монете
GET /coins/{id}/market_chart?vs_currency=usd&days=365 — исторические данные
GET /simple/supported_vs_currencies      — список поддерживаемых фиатных валют
GET /coins/markets?vs_currency=usd&order=market_cap_desc&per_page=200  — топ монет
```

### Rate limits (бесплатный тир)
- 10-30 запросов в минуту
- Обязательно кэшировать в Redis

### Сервис для работы с API

```php
// src/Service/CoinGecko/CoinGeckoClient.php

class CoinGeckoClient
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';
    private const CACHE_TTL_PRICE = 60;        // 1 мин для текущих цен
    private const CACHE_TTL_COIN_INFO = 3600;   // 1 час для метаданных монеты
    private const CACHE_TTL_HISTORY = 86400;     // 24 часа для исторических данных
    private const CACHE_TTL_COIN_LIST = 86400;   // 24 часа для списка монет

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    // Методы:
    // getPrice(string $coinId, string $vsCurrency): ?float
    // getCoinInfo(string $coinId): ?array
    // getMarketChart(string $coinId, string $vsCurrency, int $days): ?array
    // getTopCoins(string $vsCurrency, int $perPage = 200): array
    // getCoinList(): array — полный список монет (id, symbol, name)
    // getSupportedCurrencies(): array — список фиатных валют
}
```

**Важно:** Все HTTP-запросы оборачивать в try-catch, при ошибке — возвращать кэшированные данные (stale-while-revalidate подход). Логировать ошибки, но не ломать страницу.

---

## 1. Конвертер криптовалют (X to Y)

### URL-структура
```
/convert/{from}-to-{to}          — например /convert/btc-to-usd
/convert/{from}-to-{to}/{amount} — например /convert/btc-to-usd/1.5
/tools/converter                 — общая страница конвертера
```

### Роутинг

```php
// src/Controller/Frontend/ConverterController.php

#[Route('/convert/{from}-to-{to}/{amount}', name: 'converter_pair', defaults: ['amount' => '1'])]
public function convertPair(string $from, string $to, string $amount): Response

#[Route('/tools/converter', name: 'converter_index')]
public function index(): Response
```

### Логика

1. Нормализовать `$from` и `$to` в lowercase
2. Найти coinId по символу через CoinGeckoClient::getCoinList()
3. Получить текущий курс через CoinGeckoClient::getPrice()
4. Если `$to` — тоже крипта, получить оба курса в USD и пересчитать
5. Рассчитать конвертацию
6. Вернуть данные в Twig-шаблон

### Twig-шаблон: `templates/frontend/converter/pair.html.twig`

Структура страницы:
```
[H1] Convert {FromName} ({FROM}) to {ToName} ({TO})
[Конвертер-виджет]
  - Input: amount (editable, default из URL)
  - From: {FROM} с иконкой
  - To: {TO} с иконкой
  - Result: calculated amount
  - Кнопка "Swap" (меняет from/to)
  - Last updated: timestamp
[Таблица популярных сумм]
  - 1 BTC = X USD
  - 5 BTC = X USD
  - 10 BTC = X USD
  - 100 BTC = X USD
  - 1000 BTC = X USD
[Обратная конвертация]
  - 1 USD = X BTC
  - 100 USD = X BTC
  - 1000 USD = X BTC
[FAQ секция — 3-5 вопросов, JSON-LD FAQPage]
  - "How to convert {FROM} to {TO}?"
  - "What is the current {FROM} to {TO} exchange rate?"
  - "Where can I exchange {FROM} for {TO}?"
[Ссылки на связанные конвертации — internal linking]
```

### JS-функционал (vanilla JS, inline или отдельный файл)

```javascript
// Пересчёт при изменении amount (без перезагрузки страницы)
// Rate уже передан через data-атрибут, JS просто умножает
// При "Swap" — redirect на /convert/{to}-to-{from}/{amount}
```

### SEO-мета

```html
<title>{Amount} {FromName} to {ToName} — Convert {FROM} to {TO} | {SiteName}</title>
<meta name="description" content="Convert {FromName} ({FROM}) to {ToName} ({TO}). 
1 {FROM} = {Rate} {TO}. Real-time exchange rate, conversion table, and FAQ.">
<link rel="canonical" href="https://{domain}/convert/{from}-to-{to}">
```

### JSON-LD Schema

```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What is the current BTC to USD exchange rate?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "1 BTC is currently worth {rate} USD."
      }
    }
  ]
}
```

### Генерация страниц (Data Seeding)

Создай Symfony Command для заполнения начальных данных:

```php
// src/Command/SeedConverterPagesCommand.php
// bin/console app:seed:converter-pages --site-id=1

// Логика:
// 1. Получить топ-100 монет из CoinGecko
// 2. Список фиатных валют: USD, EUR, GBP, JPY, AUD, CAD, CHF, CNY, RUB, BRL
// 3. Для каждой монеты × каждый фиат = создать запись в таблице converter_pages
//    (или просто сгенерировать sitemap-записи, если страницы динамические)
// 4. Дополнительно: топ-20 крипто × топ-20 крипто = крипто-крипто пары
```

**Всего: ~1200-1500 уникальных URL** только от конвертера.

### Sitemap

Все конвертерные страницы включаются в `sitemap-converter.xml`:
```xml
<url>
  <loc>https://domain.com/convert/btc-to-usd</loc>
  <changefreq>hourly</changefreq>
  <priority>0.7</priority>
</url>
```

---

## 2. Страницы цен монет

### URL-структура
```
/price/{coin-slug}    — например /price/bitcoin, /price/ethereum
/prices               — общий листинг (топ-200 по market cap)
```

### Роутинг

```php
// src/Controller/Frontend/PriceController.php

#[Route('/price/{slug}', name: 'price_show')]
public function show(string $slug): Response

#[Route('/prices', name: 'price_index')]
public function index(): Response
```

### Entity: CoinPage

```php
// src/Entity/CoinPage.php
// Хранит статические данные о монетах для быстрого рендеринга

class CoinPage
{
    private int $id;
    private Site $site;
    private string $coinGeckoId;     // "bitcoin"
    private string $symbol;           // "BTC"
    private string $name;             // "Bitcoin"
    private string $slug;             // "bitcoin"
    private ?string $description;     // краткое описание (AI-генерация)
    private ?string $faqJson;         // JSON с FAQ (AI-генерация)
    private ?string $imageUrl;        // URL логотипа
    private bool $isActive = true;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
}
```

### Логика контроллера

1. Найти CoinPage по slug
2. Получить текущие данные из CoinGeckoClient::getCoinInfo() (кэш 1-5 мин)
3. Получить market chart за 7 дней для мини-графика
4. Передать в Twig

### Twig-шаблон: `templates/frontend/price/show.html.twig`

```
[H1] {CoinName} ({SYMBOL}) Price Today
[Текущая цена — крупно]
  - $XX,XXX.XX
  - Изменение за 24h: +X.XX% (зелёный/красный)
  - Изменение за 7d: +X.XX%
[Мини-график 7 дней — SVG или lightweight canvas]
[Ключевые метрики — таблица или grid]
  - Market Cap
  - 24h Trading Volume
  - Circulating Supply
  - Total Supply / Max Supply
  - All-Time High (дата)
  - All-Time Low (дата)
  - Market Cap Rank
[О монете — description из CoinPage.description]
  - 2-3 параграфа, AI-сгенерированные
[Конвертер — inline виджет]
  - 1 BTC = $XX,XXX
  - Ссылка на полную страницу конвертера
[FAQ — из CoinPage.faqJson, JSON-LD FAQPage]
  - "What is {CoinName}?"
  - "What is the highest price of {CoinName}?"
  - "Is {CoinName} a good investment?"
  - "How to buy {CoinName}?" (ссылка на /guides/how-to-buy-{slug})
[Последние новости о монете — 3-5 статей по тегу]
[Связанные монеты — internal linking]
```

### SEO-мета

```html
<title>{CoinName} Price Today ({SYMBOL}/USD) — Live Chart & Data | {SiteName}</title>
<meta name="description" content="{CoinName} ({SYMBOL}) price is ${price}. 
Market cap ${marketCap}. 24h volume ${volume}. Track {SYMBOL} price live.">
```

### JSON-LD Schema

Использовать `ExchangeRateSpecification` или generic `WebPage`:
```json
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "Bitcoin Price Today",
  "description": "...",
  "mainEntity": {
    "@type": "Thing",
    "name": "Bitcoin",
    "alternateName": "BTC"
  }
}
```

Плюс FAQPage schema для FAQ-блока.

### Command для сидинга

```php
// src/Command/SeedCoinPagesCommand.php
// bin/console app:seed:coin-pages --site-id=1 --count=200

// Логика:
// 1. Получить топ-200 монет из CoinGecko /coins/markets
// 2. Для каждой создать CoinPage entity с базовыми данными
// 3. description и faqJson оставить пустыми — заполнить отдельным AI-коммандом
```

```php
// src/Command/GenerateCoinDescriptionsCommand.php
// bin/console app:generate:coin-descriptions --site-id=1

// Для каждого CoinPage без description:
// 1. Составить промпт: "Write a 2-paragraph description of {CoinName} for a crypto news website..."
// 2. Вызвать Claude API (или через очередь)
// 3. Сохранить description + faqJson
```

### Cron-обновление данных

```php
// src/Command/UpdateCoinPricesCommand.php
// Запускать каждые 5 минут через cron
// Обновляет кэш в Redis для топ-200 монет одним batch-запросом
```

---

## 3. Исторический калькулятор «If I Invested»

### URL-структура
```
/tools/investment-calculator                                    — общая страница
/tools/investment-calculator/{coin}?amount=1000&date=2020-01-01 — с параметрами
```

### Роутинг

```php
// src/Controller/Frontend/InvestmentCalculatorController.php

#[Route('/tools/investment-calculator/{coin}', name: 'investment_calculator', defaults: ['coin' => 'bitcoin'])]
public function calculate(Request $request, string $coin): Response
```

### Логика

1. Параметры: coin (из URL), amount и date (из query string)
2. Получить текущую цену монеты
3. Получить историческую цену на указанную дату: 
   `GET /coins/{id}/history?date={dd-mm-yyyy}`
4. Рассчитать: 
   - amountOfCoins = investedAmount / historicalPrice
   - currentValue = amountOfCoins × currentPrice
   - profit = currentValue - investedAmount
   - percentReturn = (profit / investedAmount) × 100
5. Вернуть результат

### Twig-шаблон: `templates/frontend/tools/investment_calculator.html.twig`

```
[H1] If I Had Invested in {CoinName} — Investment Calculator
[Форма]
  - Coin: dropdown (топ-50 монет)
  - Amount invested: $1000 (default)
  - Date of investment: date picker
  - [Calculate] кнопка
[Результат (если есть)]
  - "If you had invested ${amount} in {CoinName} on {date}..."
  - You would have bought: X.XXXX {SYMBOL}
  - Current value: $XX,XXX.XX
  - Total return: +$XX,XXX.XX (+XXXX%)
  - Визуальный индикатор (зелёный/красный)
[График: историческая цена с отметкой точки входа]
[Таблица: pre-calculated популярные сценарии]
  - $100 invested in Bitcoin in 2015 → $XX,XXX today
  - $1000 invested in Ethereum in 2017 → $XX,XXX today
  - $500 invested in Solana in 2020 → $XX,XXX today
[FAQ]
  - "What if I invested $1000 in Bitcoin 5 years ago?"
  - "What is the best time to invest in crypto?"
[CTA: ссылки на How to Buy {Coin} страницы]
```

### JS-функционал

```javascript
// Форма отправляется через GET (для SEO — URL с параметрами индексируется)
// JS добавляет динамический пересчёт без перезагрузки (опционально)
// Исторические данные подгружаются через AJAX: GET /api/v1/coin-history/{id}?date=...
```

### Pre-calculated страницы для SEO

Создать статические страницы для самых популярных сценариев:

```
/tools/investment-calculator/bitcoin?amount=100&date=2015-01-01
/tools/investment-calculator/bitcoin?amount=1000&date=2017-01-01
/tools/investment-calculator/ethereum?amount=1000&date=2017-01-01
/tools/investment-calculator/solana?amount=1000&date=2020-01-01
```

Включить их в sitemap.

### Internal API эндпоинт (для JS и n8n)

```php
// src/Controller/Api/CoinHistoryApiController.php

#[Route('/api/v1/coin-history/{coinId}', name: 'api_coin_history', methods: ['GET'])]
public function getCoinHistory(string $coinId, Request $request): JsonResponse
// Параметры: ?date=2020-01-01&vs_currency=usd
// Ответ: { "coin": "bitcoin", "date": "2020-01-01", "price": 7200.15, "current_price": 95000.00 }
```

---

## Общая страница инструментов

### URL
```
/tools — листинг всех инструментов
```

### Шаблон

```
[H1] Free Crypto Tools & Calculators
[Grid карточек]
  - Crypto Converter — Convert between 100+ cryptocurrencies and fiat
  - Profit/Loss Calculator — Calculate your crypto trading profits
  - DCA Calculator — Plan your dollar cost averaging strategy
  - Investment Calculator — See what your crypto investment would be worth
  - Mining Calculator — Estimate your mining profitability
  - Tax Calculator — Estimate your crypto tax liability
[Каждая карточка: иконка, название, краткое описание, ссылка]
```

### SEO
```html
<title>Free Crypto Tools & Calculators | {SiteName}</title>
<meta name="description" content="Free cryptocurrency tools: converter, profit calculator, 
DCA calculator, mining calculator, and more. No signup required.">
```

---

## Миграции для новых Entity

```sql
-- Страницы монет
CREATE TABLE coin_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    coin_gecko_id VARCHAR(100) NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    faq_json JSON,
    image_url VARCHAR(500),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_site_slug (site_id, slug),
    UNIQUE KEY uniq_site_coingecko (site_id, coin_gecko_id),
    INDEX idx_site_active (site_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Таблица converter_pages не нужна — страницы конвертера полностью динамические, генерируются из данных CoinGecko API + кэша. Sitemap для конвертера генерируется из списка поддерживаемых монет.

---

## Порядок реализации в Claude Code

```
Шаг 1: Создай CoinGeckoClient сервис с кэшированием через Redis.
        Все эндпоинты API, retry-логика, stale-while-revalidate.

Шаг 2: Создай CoinPage entity, миграцию, repository.
        Fixtures: SeedCoinPagesCommand (топ-200 монет).

Шаг 3: Реализуй ConverterController + шаблон.
        Проверь: /convert/btc-to-usd показывает актуальный курс.
        Проверь: meta title, canonical, FAQ-блок.

Шаг 4: Реализуй PriceController + шаблон.
        Проверь: /price/bitcoin показывает цену, метрики, мини-график.
        
Шаг 5: Реализуй InvestmentCalculatorController + шаблон.
        Проверь: /tools/investment-calculator/bitcoin?amount=1000&date=2020-01-01

Шаг 6: Создай /tools index page со ссылками на все инструменты.

Шаг 7: Обнови SitemapController — добавь converter и price sitemaps.

Шаг 8: GenerateCoinDescriptionsCommand — AI-генерация описаний через Claude API.
```
