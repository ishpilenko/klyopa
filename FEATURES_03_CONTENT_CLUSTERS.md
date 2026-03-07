# Контентные кластеры и новостной поток — Инструкции для Claude Code

> Этот файл описывает реализацию контентных страниц: глоссарий, гайды «How to Buy», сравнения, обзоры бирж, Fear & Greed Index, новостной поток. Всё генерируется через AI-пайплайн.

## 1. Glossary — Словарь крипто-терминов

### URL-структура
```
/glossary             — индексная страница (алфавитный список)
/glossary/{term-slug} — отдельная страница термина
```

### Entity: GlossaryTerm

```php
// src/Entity/GlossaryTerm.php

class GlossaryTerm
{
    private int $id;
    private Site $site;
    private string $term;           // "Blockchain"
    private string $slug;           // "blockchain"
    private string $shortDefinition; // 1-2 предложения для индексной страницы
    private string $fullContent;     // HTML — развёрнутое объяснение (500-1000 слов)
    private ?string $faqJson;        // JSON массив вопросов-ответов
    private ?string $metaTitle;
    private ?string $metaDescription;
    private string $firstLetter;     // "B" — для алфавитной группировки
    private array $relatedTermSlugs = []; // JSON — ссылки на связанные термины
    private string $status = 'published';
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
}
```

### Миграция

```sql
CREATE TABLE glossary_terms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    term VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    short_definition TEXT NOT NULL,
    full_content LONGTEXT NOT NULL,
    faq_json JSON,
    meta_title VARCHAR(255),
    meta_description VARCHAR(320),
    first_letter CHAR(1) NOT NULL,
    related_term_slugs JSON,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_site_slug (site_id, slug),
    INDEX idx_site_letter (site_id, first_letter, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Контроллер

```php
// src/Controller/Frontend/GlossaryController.php

#[Route('/glossary', name: 'glossary_index')]
public function index(GlossaryTermRepository $repo): Response
{
    // Группировка по first_letter: ['A' => [...], 'B' => [...], ...]
    $termsByLetter = $repo->findAllGroupedByLetter();
    return $this->render('frontend/glossary/index.html.twig', [
        'termsByLetter' => $termsByLetter,
    ]);
}

#[Route('/glossary/{slug}', name: 'glossary_show')]
public function show(string $slug, GlossaryTermRepository $repo): Response
{
    $term = $repo->findBySlug($slug);
    $relatedTerms = $repo->findBySlugs($term->getRelatedTermSlugs());
    return $this->render('frontend/glossary/show.html.twig', [
        'term' => $term,
        'relatedTerms' => $relatedTerms,
    ]);
}
```

### Шаблон: glossary/show.html.twig

```
[H1] What is {Term}? — Crypto Glossary
[Short definition — выделена визуально]
[Full content — 500-1000 слов, с H2/H3 подзаголовками]
[Related Terms — карточки со ссылками на 3-5 связанных терминов]
[FAQ — JSON-LD FAQPage]
[Breadcrumbs: Home > Glossary > {Term}]
```

### SEO-мета

```html
<title>What is {Term}? Definition & Explanation | {SiteName} Glossary</title>
<meta name="description" content="{ShortDefinition} Learn about {term} in our crypto glossary.">
```

### AI-генерация — Command

```php
// src/Command/GenerateGlossaryCommand.php
// bin/console app:generate:glossary --site-id=1

// Шаг 1: Список терминов (hardcoded массив из 150-200 терминов)
$terms = [
    'Blockchain', 'Bitcoin', 'Ethereum', 'Smart Contract', 'DeFi',
    'NFT', 'Token', 'Altcoin', 'Staking', 'Mining', 'Wallet',
    'Private Key', 'Public Key', 'Gas Fee', 'Consensus Mechanism',
    'Proof of Work', 'Proof of Stake', 'Liquidity Pool', 'Yield Farming',
    'Impermanent Loss', 'DAO', 'Airdrop', 'ICO', 'IDO', 'Whitepaper',
    'Market Cap', 'Circulating Supply', 'HODL', 'FOMO', 'FUD',
    'Whale', 'Rug Pull', 'DEX', 'CEX', 'AMM', 'Oracle',
    'Layer 1', 'Layer 2', 'Sidechain', 'Bridge', 'Rollup',
    'Cold Wallet', 'Hot Wallet', 'Seed Phrase', 'Hash Rate',
    'Block Reward', 'Halving', 'Fork', 'Hard Fork', 'Soft Fork',
    'Metaverse', 'Web3', 'dApp', 'TVL', 'APY', 'APR',
    'Slippage', 'MEV', 'Flash Loan', 'Wrapped Token',
    // ... и т.д.
];

// Шаг 2: Для каждого термина — промпт в Claude API
$prompt = <<<PROMPT
Write a glossary entry for the crypto term "{$term}".

Return JSON:
{
  "shortDefinition": "1-2 sentence definition",
  "fullContent": "HTML content, 500-800 words. Include H2 subheadings. 
    Cover: what it is, how it works, why it matters, real-world example.",
  "faq": [
    {"question": "...", "answer": "..."},
    {"question": "...", "answer": "..."},
    {"question": "...", "answer": "..."}
  ],
  "relatedTerms": ["term-slug-1", "term-slug-2", "term-slug-3"],
  "metaTitle": "What is {Term}? ...",
  "metaDescription": "..."
}
PROMPT;

// Шаг 3: Парсить ответ, создать GlossaryTerm entity, сохранить
```

### Промпт-шаблон

```yaml
# config/prompts/crypto/glossary_term.yaml
system: |
  You are a crypto educator writing for {site_name}.
  Write clear, accurate definitions accessible to beginners.
  Include technical details for advanced readers.
  Do NOT give financial advice.
  Write in {locale} language.
  Return valid JSON only.

user: |
  Write a glossary entry for: {term}
  
  Return JSON with fields: shortDefinition, fullContent (HTML), 
  faq (array of question/answer), relatedTerms (array of slugs from: {available_slugs}),
  metaTitle, metaDescription.
```

---

## 2. Гайды «How to Buy [Coin]»

### URL-структура
```
/guides/how-to-buy-{coin-slug}  — /guides/how-to-buy-bitcoin
/guides                          — индекс гайдов
```

### Реализация

Эти страницы создаются как обычные статьи (Article entity) в категории «Guides», со специальным schema_type `HowTo`.

### AI-промпт для генерации

```yaml
# config/prompts/crypto/how_to_buy_guide.yaml
system: |
  You are a crypto journalist writing for {site_name}.
  Write step-by-step guides that are beginner-friendly.
  Include specific exchange recommendations where relevant.
  Always mention security best practices.
  Do NOT provide financial advice.

user: |
  Write a comprehensive guide: "How to Buy {coin_name} ({symbol})"
  
  Structure:
  1. Brief introduction: what is {coin_name}, why people buy it
  2. Step-by-step guide (H2: "How to Buy {coin_name} — Step by Step")
     - Step 1: Choose an exchange (mention 3-4: Coinbase, Binance, Kraken, etc.)
     - Step 2: Create and verify account
     - Step 3: Deposit funds (bank, card, crypto)
     - Step 4: Place order (market vs limit)
     - Step 5: Secure your {symbol} (wallet options)
  3. Best exchanges to buy {coin_name} (H2, comparison table)
     - Exchange name, fees, payment methods, pros/cons
  4. How to store {coin_name} safely (H2)
  5. FAQ (5 questions)
  
  Target: 1500-2000 words. HTML format.
  Include [AFFILIATE_LINK:exchange_name] placeholders where exchange links should go.
```

### Post-processing

В n8n-workflow после генерации:
1. Заменить `[AFFILIATE_LINK:binance]` на реальные affiliate-ссылки из таблицы `affiliate_links`
2. Добавить internal links на: price page монеты, конвертер, DCA calculator
3. Установить schema_type = 'HowTo'
4. Сгенерировать HowTo JSON-LD schema

### JSON-LD HowTo

```json
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "How to Buy Bitcoin",
  "description": "Step-by-step guide to buying Bitcoin...",
  "step": [
    {
      "@type": "HowToStep",
      "name": "Choose a cryptocurrency exchange",
      "text": "Select a reputable exchange like Coinbase, Binance, or Kraken..."
    },
    {
      "@type": "HowToStep",
      "name": "Create and verify your account",
      "text": "Sign up and complete identity verification..."
    }
  ]
}
```

### Список гайдов для MVP (40-50 монет)

```
bitcoin, ethereum, solana, xrp, cardano, dogecoin, polkadot,
avalanche, chainlink, polygon, uniswap, litecoin, cosmos,
near-protocol, aptos, sui, arbitrum, optimism, stellar, 
algorand, filecoin, the-graph, aave, maker, lido-dao,
injective, sei, celestia, render, fetch-ai, pepe, 
shiba-inu, bonk, floki, toncoin, kaspa, monero, bnb,
tron, internet-computer, hedera, fantom
```

---

## 3. Сравнения «X vs Y»

### URL-структура
```
/compare/{x}-vs-{y}  — /compare/bitcoin-vs-ethereum
/compare              — индекс сравнений
```

### Реализация

Тоже Article entity, категория «Comparisons», отдельный шаблон с таблицей сравнения.

### AI-промпт

```yaml
# config/prompts/crypto/comparison.yaml
system: |
  You are a crypto analyst writing for {site_name}.
  Write balanced, objective comparisons without recommending one over the other.
  Include data points: market cap, transaction speed, fees, use cases.
  Write in {locale}.

user: |
  Write a comparison article: "{coin1_name} vs {coin2_name}: Key Differences Explained"
  
  Structure:
  1. Introduction: why compare these two
  2. Comparison table (HTML table with rows):
     - Year launched
     - Consensus mechanism
     - Transaction speed (TPS)
     - Average transaction fee
     - Market cap
     - Max supply
     - Primary use case
     - Smart contracts: yes/no
     - Energy efficiency
  3. {coin1_name} Overview (H2) — 200 words
  4. {coin2_name} Overview (H2) — 200 words
  5. Key Differences (H2) — 3-5 differences in detail
  6. Which Should You Choose? (H2) — based on use case, not recommendation
  7. FAQ (5 questions)
  
  Target: 1200-1800 words. HTML format.
```

### Twig-шаблон: compare/show.html.twig

```
[H1] {Coin1} vs {Coin2}: Key Differences in {Year}
[Live price comparison — mini widget]
  - {Coin1}: ${price1} ({change1}%)
  - {Coin2}: ${price2} ({change2}%)
[Comparison table — рендерится из article content]
[Article content]
[FAQ — JSON-LD]
[Related comparisons — internal links]
[Links to price pages, how-to-buy guides — internal links]
```

### Список сравнений для MVP (30-50 пар)

```
bitcoin-vs-ethereum, bitcoin-vs-solana, bitcoin-vs-xrp,
ethereum-vs-solana, ethereum-vs-cardano, ethereum-vs-polygon,
solana-vs-avalanche, solana-vs-cardano, solana-vs-sui,
cardano-vs-polkadot, xrp-vs-stellar, litecoin-vs-bitcoin,
dogecoin-vs-shiba-inu, bnb-vs-ethereum, monero-vs-bitcoin,
bitcoin-vs-gold, bitcoin-vs-sp500, proof-of-work-vs-proof-of-stake,
coinbase-vs-binance, coinbase-vs-kraken, binance-vs-bybit,
ledger-vs-trezor, metamask-vs-trust-wallet,
defi-vs-cefi, layer1-vs-layer2, bitcoin-vs-litecoin
```

---

## 4. Обзоры бирж и кошельков

### URL-структура
```
/reviews/{slug}        — /reviews/binance-review
/reviews/exchanges     — листинг обзоров бирж
/reviews/wallets       — листинг обзоров кошельков
```

### AI-промпт

```yaml
# config/prompts/crypto/exchange_review.yaml
user: |
  Write a comprehensive review of {exchange_name} cryptocurrency exchange.
  
  Structure:
  1. TL;DR (3-4 bullet points: verdict, best for, fees, rating)
  2. Overview (H2): founded, HQ, users, supported coins, regulation
  3. Pros and Cons (H2): HTML table or structured list
  4. Fees (H2): trading fees, deposit/withdrawal fees, comparison with competitors
  5. Security (H2): measures, history of hacks, insurance
  6. Supported Cryptocurrencies (H2): number, notable coins
  7. User Experience (H2): interface, mobile app, customer support
  8. How to Get Started (H2): step-by-step
  9. {exchange_name} vs Competitors (H2): brief comparison with 2-3 alternatives
  10. Verdict (H2): who it's best for
  11. FAQ (5 questions)
  
  Target: 2000-2500 words. HTML format.
  Include [AFFILIATE_LINK:{exchange_id}] where signup links should go.
  Include a rating: X.X/10 format.
```

### Дополнительная таблица для structured data

```sql
CREATE TABLE exchange_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    rating DECIMAL(3,1),           -- 8.5
    founded_year SMALLINT,
    headquarters VARCHAR(255),
    supported_coins INT,
    trading_fee_maker DECIMAL(5,4), -- 0.0010
    trading_fee_taker DECIMAL(5,4), -- 0.0010
    has_mobile_app BOOLEAN DEFAULT TRUE,
    is_regulated BOOLEAN DEFAULT FALSE,
    kyc_required BOOLEAN DEFAULT TRUE,
    affiliate_url VARCHAR(2048),
    review_article_id INT UNSIGNED,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (review_article_id) REFERENCES articles(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_site_slug (site_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Список для MVP (10-15 бирж + 5 кошельков)

Биржи: Binance, Coinbase, Kraken, Bybit, OKX, KuCoin, Bitget, Gate.io, Crypto.com, Gemini

Кошельки: Ledger, Trezor, MetaMask, Trust Wallet, Phantom

---

## 5. Fear & Greed Index

### URL
```
/fear-greed-index
```

### Данные

Alternative.me API (бесплатно):
```
GET https://api.alternative.me/fng/?limit=30&format=json
```

Возвращает:
```json
{
  "data": [
    { "value": "73", "value_classification": "Greed", "timestamp": "..." },
    { "value": "65", "value_classification": "Greed", "timestamp": "..." }
  ]
}
```

### Контроллер

```php
#[Route('/fear-greed-index', name: 'fear_greed_index')]
public function fearGreedIndex(FearGreedClient $client): Response
{
    $data = $client->getIndex(30); // последние 30 дней
    $current = $data[0];
    
    return $this->render('frontend/tools/fear_greed.html.twig', [
        'current' => $current,
        'history' => $data,
    ]);
}
```

### Шаблон

```
[H1] Crypto Fear & Greed Index — {Value} ({Classification})
[Gauge chart — полукруг от 0 (Extreme Fear) до 100 (Extreme Greed)]
  - Стрелка на текущем значении
  - Зоны раскрашены: 0-25 красный, 25-45 оранжевый, 45-55 серый, 55-75 зелёный, 75-100 ярко-зелёный
[Текущее значение — крупно]
  - Значение: 73
  - Классификация: Greed
  - Дата: March 7, 2026
[Timeline — значения за 30 дней, линейный график]
[Таблица исторических значений]
  - Today: 73 (Greed)
  - Yesterday: 71 (Greed)
  - Last week: 45 (Neutral)
  - Last month: 28 (Fear)
[What is the Fear & Greed Index — объяснение]
  - Факторы: volatility, volume, social media, surveys, dominance, trends
[What does today's index mean — AI-генерированный комментарий (обновляется daily)]
[FAQ]
  - "What is the Crypto Fear and Greed Index?"
  - "How is the Fear & Greed Index calculated?"
  - "Should I buy when fear is high?"
  - "What does extreme greed mean for crypto?"
[Ссылки: Bitcoin price, latest news]
```

### Gauge Chart (SVG, inline)

```html
<!-- Простой SVG gauge, без библиотек -->
<svg viewBox="0 0 200 120" class="fear-greed-gauge">
  <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#eee" stroke-width="20"/>
  <!-- Цветные сегменты -->
  <path d="..." fill="none" stroke="#ea4335" stroke-width="20"/>  <!-- Extreme Fear -->
  <path d="..." fill="none" stroke="#fbbc05" stroke-width="20"/>  <!-- Fear -->
  <path d="..." fill="none" stroke="#9e9e9e" stroke-width="20"/>  <!-- Neutral -->
  <path d="..." fill="none" stroke="#34a853" stroke-width="20"/>  <!-- Greed -->
  <path d="..." fill="none" stroke="#1a73e8" stroke-width="20"/>  <!-- Extreme Greed -->
  <!-- Стрелка -->
  <line x1="100" y1="100" x2="..." y2="..." stroke="#333" stroke-width="2"/>
  <text x="100" y="85" text-anchor="middle" font-size="24" font-weight="bold">{value}</text>
  <text x="100" y="115" text-anchor="middle" font-size="12">{classification}</text>
</svg>
```

### Daily AI-комментарий

```php
// src/Command/UpdateFearGreedCommentCommand.php
// Cron: ежедневно в 10:00 UTC

// Промпт:
// "The Crypto Fear & Greed Index is currently {value} ({classification}).
//  Yesterday it was {prev_value}. Bitcoin price is ${btc_price}.
//  Write a 2-3 sentence market commentary based on these numbers.
//  Be factual, do not give financial advice."
```

---

## 6. Новостной поток (AI-генерация)

### Уже описан в CLAUDE.md (n8n workflows)

Здесь дополнительные детали для реализации.

### RSS-источники для крипто-вертикали

```yaml
# config/rss/crypto.yaml
feeds:
  - url: https://cointelegraph.com/rss
    name: CoinTelegraph
    priority: high
  - url: https://decrypt.co/feed
    name: Decrypt
    priority: high
  - url: https://www.coindesk.com/arc/outboundfeeds/rss/
    name: CoinDesk
    priority: high
  - url: https://cryptonews.com/news/feed/
    name: CryptoNews
    priority: medium
  - url: https://bitcoinmagazine.com/.rss/full/
    name: Bitcoin Magazine
    priority: medium
  - url: https://blog.ethereum.org/feed.xml
    name: Ethereum Blog
    priority: medium
```

### Промпт для новостей

```yaml
# config/prompts/crypto/news_article.yaml
system: |
  You are a crypto journalist for {site_name}.
  Rewrite the provided news topic into an original article.
  DO NOT copy from the source — create original reporting.
  Include relevant context and analysis.
  Be factual and objective. No financial advice.
  Target: 600-1000 words. HTML with H2 subheadings.

user: |
  Write an original news article about: {topic}
  
  Source context: {source_summary}
  
  Include:
  - Catchy H1 headline with primary keyword
  - TL;DR (2-3 sentences)
  - What happened (H2)
  - Why it matters (H2)
  - Market context / impact (H2)
  - What's next (H2)
  
  Keywords to include naturally: {keywords}
```

### Категории новостей

Создать в фикстурах для крипто-сайта:
```
- Bitcoin News (slug: bitcoin-news)
- Ethereum News (slug: ethereum-news)
- Altcoin News (slug: altcoin-news)
- DeFi News (slug: defi-news)
- NFT News (slug: nft-news)
- Regulation (slug: regulation)
- Market Analysis (slug: market-analysis)
- Exchange News (slug: exchange-news)
- Guides (slug: guides)
- Reviews (slug: reviews)
- Comparisons (slug: comparisons)
```

---

## 7. Таблица affiliate_links (для монетизации)

```sql
CREATE TABLE affiliate_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    partner VARCHAR(100) NOT NULL,      -- "binance", "coinbase", "ledger"
    partner_type ENUM('exchange', 'wallet', 'service', 'course') NOT NULL,
    base_url VARCHAR(2048) NOT NULL,    -- базовый URL партнёрки
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100) DEFAULT 'affiliate',
    utm_campaign VARCHAR(100),
    display_name VARCHAR(255) NOT NULL, -- "Binance" для отображения
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_site_partner (site_id, partner)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Twig Extension

```php
// src/Twig/Extension/AffiliateExtension.php

// Использование в шаблонах:
// {{ affiliate_link('binance') }}  → <a href="https://binance.com?ref=XXX&utm_source=...">Binance</a>
// {{ affiliate_url('coinbase') }} → raw URL

// При клике — middleware считает клик (UPDATE clicks = clicks + 1)
// Redirect через /go/{partner} для отслеживания
```

### Redirect-контроллер

```php
#[Route('/go/{partner}', name: 'affiliate_redirect')]
public function redirect(string $partner, AffiliateLinkRepository $repo): Response
{
    $link = $repo->findByPartner($partner);
    $link->incrementClicks();
    // ... flush
    return $this->redirect($link->getFullUrl(), 302);
}
```

---

## Порядок реализации в Claude Code

```
Шаг 1: Создай entity GlossaryTerm + миграцию.
        Реализуй GlossaryController (index + show).
        Создай шаблоны с SEO (breadcrumbs, FAQ, JSON-LD).

Шаг 2: Создай GenerateGlossaryCommand.
        Hardcode список 150 терминов.
        Интеграция с Claude API для генерации контента.
        Запусти — проверь 5-10 терминов.

Шаг 3: Создай шаблон для «How to Buy» гайдов.
        AI-промпт в config/prompts/crypto/how_to_buy_guide.yaml.
        Сгенерируй 20 гайдов через n8n или CLI command.
        Проверь HowTo JSON-LD schema.

Шаг 4: Создай шаблон для сравнений.
        CompareController с роутом /compare/{x}-vs-{y}.
        AI-промпт для генерации.
        Сгенерируй 30 сравнений.

Шаг 5: Создай exchange_data таблицу + миграцию.
        Сгенерируй 10 обзоров бирж.
        Шаблон обзора с structured data из exchange_data.

Шаг 6: Fear & Greed Index.
        FearGreedClient сервис (Alternative.me API + кэш).
        Шаблон с SVG gauge chart.
        UpdateFearGreedCommentCommand для daily AI-комментария.

Шаг 7: affiliate_links таблица + AffiliateExtension + redirect controller.
        Fixtures: 10 партнёров (Binance, Coinbase, Kraken, Bybit, OKX, 
        Ledger, Trezor, MetaMask, Trust Wallet, CoinLedger).

Шаг 8: Настрой RSS-источники в config/rss/crypto.yaml.
        Создай n8n workflow для парсинга RSS → очередь → генерация → публикация.
        Цель: 10-20 новостей в день в автоматическом режиме.

Шаг 9: Обнови sitemap — добавь glossary, guides, compare, reviews, fear-greed.
        Проверь все canonical URLs и JSON-LD schemas.

Шаг 10: Обнови /tools и главную страницу — добавь ссылки на все новые разделы.
         Проверь internal linking между разделами:
         - Price page → How to Buy guide, Converter
         - How to Buy guide → Exchange review, Price page
         - Glossary term → Related terms, Guides
         - Comparison → Price pages обеих монет
         - News → Price page упомянутой монеты, Glossary terms
```
