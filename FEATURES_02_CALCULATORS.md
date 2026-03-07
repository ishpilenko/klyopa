# Калькуляторы и интерактивные инструменты — Инструкции для Claude Code

> Этот файл описывает реализацию интерактивных инструментов: Profit/Loss Calculator, DCA Calculator, Mining Calculator, Tax Calculator, Gas Fee Tracker. Инструменты работают на vanilla JS (серверный рендеринг + клиентская интерактивность).

## Архитектурный подход

Все калькуляторы реализуются по единому паттерну:

1. **Серверная часть** — Twig-шаблон с SEO-контентом (H1, description, FAQ, JSON-LD). Рендерится полностью на сервере — Google видит готовую страницу.
2. **Клиентская часть** — vanilla JS, встроенный в шаблон или подключённый как отдельный файл. Обрабатывает ввод, считает результат, обновляет DOM. Никаких фреймворков.
3. **Данные** — статические формулы (profit calculator, tax) или серверные данные через data-атрибуты / inline JSON (DCA, mining).

### Общий Twig layout для инструментов

```twig
{# templates/frontend/tools/base_tool.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
<article class="tool-page">
    <h1>{% block tool_title %}{% endblock %}</h1>
    
    <div class="tool-intro">
        {% block tool_intro %}{% endblock %}
    </div>

    <div class="tool-widget" id="tool-widget">
        {% block tool_widget %}{% endblock %}
    </div>

    <div class="tool-content">
        {% block tool_content %}{% endblock %}
    </div>

    <div class="tool-faq" itemscope itemtype="https://schema.org/FAQPage">
        {% block tool_faq %}{% endblock %}
    </div>
    
    <div class="tool-related">
        {% block tool_related %}{% endblock %}
    </div>
</article>
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    {% block tool_js %}{% endblock %}
{% endblock %}
```

---

## 1. Crypto Profit/Loss Calculator

### URL
```
/tools/crypto-profit-calculator
```

### Контроллер

```php
// src/Controller/Frontend/ToolsController.php

#[Route('/tools/crypto-profit-calculator', name: 'tool_profit_calculator')]
public function profitCalculator(): Response
{
    return $this->render('frontend/tools/profit_calculator.html.twig');
}
```

### Логика расчёта (JS, чисто клиентская)

```javascript
// Входные данные:
// - buyPrice: цена покупки за 1 монету
// - sellPrice: цена продажи за 1 монету
// - quantity: количество монет
// - buyFee: комиссия при покупке (%)
// - sellFee: комиссия при продаже (%)

// Расчёт:
const totalInvested = buyPrice * quantity;
const buyFeeAmount = totalInvested * (buyFee / 100);
const totalCost = totalInvested + buyFeeAmount;

const totalRevenue = sellPrice * quantity;
const sellFeeAmount = totalRevenue * (sellFee / 100);
const totalReceived = totalRevenue - sellFeeAmount;

const profitLoss = totalReceived - totalCost;
const percentReturn = ((totalReceived - totalCost) / totalCost) * 100;

// Вывод:
// - Total invested (incl. fees): $X,XXX.XX
// - Total received (after fees): $X,XXX.XX
// - Profit/Loss: +$X,XXX.XX / -$X,XXX.XX
// - ROI: +XX.XX% / -XX.XX%
```

### Шаблон UI

```
[H1] Crypto Profit/Loss Calculator
[Intro: 1-2 предложения]

[Calculator Widget]
  ┌──────────────────────────────┐
  │ Investment Details            │
  │ Buy Price ($):  [________]   │
  │ Sell Price ($): [________]   │
  │ Quantity:       [________]   │
  │                              │
  │ Fees (optional)              │
  │ Buy Fee (%):    [__0.1___]   │
  │ Sell Fee (%):   [__0.1___]   │
  │                              │
  │ [Calculate Profit]           │
  ├──────────────────────────────┤
  │ Results                      │
  │ Total Invested:   $5,010.00  │
  │ Total Received:   $9,990.00  │
  │ Profit:        ▲ $4,980.00   │
  │ ROI:           ▲ 99.40%      │
  └──────────────────────────────┘

[How to Use — 3-4 шага]
[What is ROI in crypto — пояснительный текст, 2-3 параграфа]
[FAQ — 5 вопросов, JSON-LD FAQPage]
  - "How do I calculate crypto profit?"
  - "How do crypto trading fees affect profit?"
  - "What is a good ROI for cryptocurrency?"
  - "Do I need to pay taxes on crypto profits?"
  - "How do I track my crypto profits over time?"
[Related Tools — ссылки на другие калькуляторы]
```

### SEO-мета

```html
<title>Crypto Profit Calculator — Calculate Your Trading Profit & Loss | {SiteName}</title>
<meta name="description" content="Free crypto profit calculator. Calculate your cryptocurrency 
trading profit or loss including fees. Works for Bitcoin, Ethereum, and all altcoins.">
```

### JSON-LD

```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "Crypto Profit Calculator",
  "applicationCategory": "FinanceApplication",
  "operatingSystem": "Web",
  "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" }
}
```

---

## 2. DCA (Dollar Cost Averaging) Calculator

### URL
```
/tools/dca-calculator
/tools/dca-calculator/{coin}  — pre-selected монета
```

### Контроллер

```php
#[Route('/tools/dca-calculator/{coin}', name: 'tool_dca_calculator', defaults: ['coin' => 'bitcoin'])]
public function dcaCalculator(string $coin, CoinGeckoClient $client): Response
{
    // Получить доступные монеты для dropdown
    $topCoins = $client->getTopCoins('usd', 50);
    
    return $this->render('frontend/tools/dca_calculator.html.twig', [
        'selectedCoin' => $coin,
        'topCoins' => $topCoins,
    ]);
}
```

### Internal API для исторических данных

```php
// src/Controller/Api/DcaApiController.php

#[Route('/api/v1/dca/calculate', name: 'api_dca_calculate', methods: ['GET'])]
public function calculate(Request $request, CoinGeckoClient $client): JsonResponse
{
    // Query params: coin, startDate, endDate, amount, frequency (weekly|monthly)
    
    // Логика:
    // 1. Получить market_chart за период (days = разница дат)
    // 2. Выбрать точки по frequency (каждые 7 или 30 дней)
    // 3. Для каждой точки:
    //    - coinsBought = amount / priceAtDate
    //    - totalCoins += coinsBought
    //    - totalInvested += amount
    // 4. currentValue = totalCoins * currentPrice
    // 5. Вернуть JSON с массивом точек + summary
    
    return $this->json([
        'summary' => [
            'totalInvested' => $totalInvested,
            'totalCoins' => $totalCoins,
            'currentValue' => $currentValue,
            'profit' => $currentValue - $totalInvested,
            'percentReturn' => (($currentValue - $totalInvested) / $totalInvested) * 100,
            'numPurchases' => count($purchases),
            'avgCostPerCoin' => $totalInvested / $totalCoins,
        ],
        'purchases' => $purchases, // массив {date, price, coinsBought, totalCoinsAtDate, totalInvestedAtDate}
    ]);
}
```

### Шаблон UI

```
[H1] DCA Calculator — Dollar Cost Averaging for {CoinName}
[Intro: что такое DCA, 2-3 предложения]

[Calculator Widget]
  ┌──────────────────────────────────┐
  │ Cryptocurrency: [Bitcoin ▼]      │
  │ Investment per period: [$___100] │
  │ Frequency: [Weekly ▼] / Monthly  │
  │ Start date: [2020-01-01]         │
  │ End date:   [Today]              │
  │                                  │
  │ [Calculate DCA]                  │
  ├──────────────────────────────────┤
  │ Results                          │
  │ Total invested:    $15,600.00    │
  │ Current value:     $48,230.00    │
  │ Total coins:       0.5847 BTC    │
  │ Profit:         ▲  $32,630.00    │
  │ ROI:            ▲  209.17%       │
  │ Avg cost/coin:     $26,671.45    │
  │ # of purchases:    156           │
  ├──────────────────────────────────┤
  │ [Линейный график]                │
  │ - Линия 1: Total Invested (рост)│
  │ - Линия 2: Portfolio Value       │
  │ - Область между ними = profit    │
  └──────────────────────────────────┘

[What is Dollar Cost Averaging — объяснение, 3-4 параграфа]
[DCA vs Lump Sum Investing — сравнение]
[FAQ — 5 вопросов]
  - "What is DCA in crypto?"
  - "Is DCA a good strategy for Bitcoin?"
  - "How often should I DCA into crypto?"
  - "What is the best day to DCA Bitcoin?"
  - "Does DCA work in a bear market?"
[Pre-calculated examples — таблица]
  - $100/week into BTC since 2020: $X,XXX → $XX,XXX
  - $50/month into ETH since 2021: $X,XXX → $XX,XXX
```

### JS

```javascript
// 1. При submit формы — AJAX GET /api/v1/dca/calculate?coin=...&startDate=...
// 2. Отрисовать результат (summary)
// 3. Отрисовать график (SVG или простой canvas — без Chart.js для скорости)
//    Или использовать lightweight chart library (uPlot — 30KB)
// 4. Обновить URL через history.pushState для шаринга
```

### SEO-мета

```html
<title>DCA Calculator for {CoinName} — Dollar Cost Averaging | {SiteName}</title>
<meta name="description" content="Calculate returns of dollar cost averaging into {CoinName}. 
See how investing ${amount}/{frequency} would perform over time.">
```

---

## 3. Mining Profitability Calculator

### URL
```
/tools/mining-calculator
/tools/mining-calculator/{coin}
```

### Данные для расчёта

Нужны данные о сложности сети, награде за блок, текущей цене. Источники:
- Цена: CoinGecko API
- Сложность и награда: hardcode для MVP (обновлять через cron)
  или API типа WhatToMine / minerstat (бесплатные тиры)

Для MVP достаточно поддержать 5-10 майнинговых монет: BTC, ETH (historical), LTC, DOGE, XMR, KAS, RVN.

### Логика расчёта

```javascript
// Входные данные:
// - hashrate: хешрейт оборудования (TH/s, GH/s, MH/s)
// - power: потребление электричества (Watts)
// - electricityCost: стоимость kWh ($)
// - poolFee: комиссия пула (%, default 1%)
// - coin: выбранная монета

// Константы монеты (серверные, передаются через data-атрибуты):
// - networkHashrate: общий хешрейт сети
// - blockReward: награда за блок
// - blockTime: время блока (секунды)
// - currentPrice: текущая цена в USD

// Расчёт:
const blocksPerDay = 86400 / blockTime;
const totalDailyReward = blocksPerDay * blockReward;
const myShareOfNetwork = hashrate / networkHashrate;
const dailyCoinsEarned = totalDailyReward * myShareOfNetwork * (1 - poolFee / 100);
const dailyRevenue = dailyCoinsEarned * currentPrice;
const dailyElectricityCost = (power / 1000) * 24 * electricityCost;
const dailyProfit = dailyRevenue - dailyElectricityCost;

// Вывод (daily / weekly / monthly / yearly):
// - Revenue: $X.XX
// - Electricity cost: $X.XX
// - Profit: $X.XX
// - Coins earned: X.XXXXX
// - Break-even price: electricityCost / coinsPerKwh
```

### Шаблон UI

```
[H1] {CoinName} Mining Calculator — Is Mining Profitable?
[Intro]

[Calculator Widget]
  ┌─────────────────────────────────┐
  │ Coin: [Bitcoin ▼]               │
  │ Hashrate: [___] [TH/s ▼]       │
  │ Power consumption: [____] W     │
  │ Electricity cost: [$__0.10] /kWh│
  │ Pool fee: [__1__] %             │
  │                                 │
  │ [Calculate]                     │
  ├─────────────────────────────────┤
  │         Daily  Monthly  Yearly  │
  │ Revenue $X.XX  $XXX    $X,XXX  │
  │ Electr. $X.XX  $XXX    $X,XXX  │
  │ Profit  $X.XX  $XXX    $X,XXX  │
  │ Coins   0.000X 0.00XX  0.0XXX  │
  ├─────────────────────────────────┤
  │ Break-even electricity: $X.XX/kWh │
  │ Days to mine 1 {SYMBOL}: XXX    │
  └─────────────────────────────────┘

[Network stats — текущая сложность, хешрейт, награда]
[How Crypto Mining Works — объяснение]
[FAQ]
[Popular Mining Hardware — таблица с preset hashrate/power]
```

### Command для обновления данных

```php
// src/Command/UpdateMiningDataCommand.php
// bin/console app:update:mining-data
// Cron: каждые 6 часов
// Обновляет: сложность, хешрейт сети, награда за блок для каждой монеты
// Хранит в Redis или в таблице mining_coin_data
```

---

## 4. Crypto Tax Calculator (упрощённый)

### URL
```
/tools/crypto-tax-calculator
```

### Логика

Упрощённый калькулятор — не импорт транзакций, а простая форма:

```javascript
// Входные данные:
// - country: US / UK / DE / AU / CA
// - annualIncome: годовой доход (для определения bracket)
// - cryptoGains: прибыль от крипто-сделок за год
// - holdingPeriod: short-term (<1 year) / long-term (>1 year)
// - filingStatus: single / married (для US)

// Tax rates по странам (hardcoded, обновлять ежегодно):
const taxRates = {
    US: {
        shortTerm: [
            { min: 0, max: 11600, rate: 10 },
            { min: 11600, max: 47150, rate: 12 },
            { min: 47150, max: 100525, rate: 22 },
            { min: 100525, max: 191950, rate: 24 },
            // ...
        ],
        longTerm: [
            { min: 0, max: 47025, rate: 0 },
            { min: 47025, max: 518900, rate: 15 },
            { min: 518900, max: Infinity, rate: 20 },
        ]
    },
    UK: { /* Capital Gains Tax: 10% basic / 20% higher rate */ },
    DE: { /* Free if held >1 year, else income tax rate */ },
    // ...
};

// Расчёт:
// Определить applicable rate на основе страны + доход + период владения
// estimatedTax = cryptoGains * applicableRate
```

### UI

```
[H1] Crypto Tax Calculator — Estimate Your Crypto Taxes
[Disclaimer: This is an estimate only. Consult a tax professional.]

[Widget]
  - Country: [US ▼]
  - Filing status: [Single ▼] (if US)
  - Annual income: [$______]
  - Crypto gains this year: [$______]
  - Holding period: [Short-term (<1yr) ▼]
  - [Calculate]
  - Result: Estimated tax: $X,XXX (XX%)

[Crypto Tax Guide by Country — секция с табами]
  - US: overview of IRS rules
  - UK: HMRC rules
  - Germany: unique >1 year exemption
  - Australia: ATO rules
[FAQ]
[CTA: "For accurate tax reporting, consider CoinLedger / CoinTracker" — affiliate link opportunity]
```

### SEO-мета

```html
<title>Crypto Tax Calculator 2025 — Estimate Your Crypto Taxes Free | {SiteName}</title>
<meta name="description" content="Free crypto tax calculator for US, UK, Germany, and Australia. 
Estimate your capital gains tax on cryptocurrency profits. Updated for 2025 tax year.">
```

**Важно:** Добавить disclaimer: «This calculator provides estimates only. Tax laws vary by jurisdiction. Consult a qualified tax professional.» Это critical для YMYL compliance.

---

## 5. Gas Fee Tracker

### URL
```
/tools/gas-tracker
/tools/gas-tracker/{network}  — ethereum, bsc, polygon и др.
```

### Данные

Ethereum gas prices: из публичных API — Etherscan Gas Tracker API (бесплатный ключ) или Blocknative API.

Для MVP достаточно Ethereum. Добавить BSC, Polygon позже.

### Структура

```
[H1] Ethereum Gas Tracker — Current Gas Fees
[Три карточки: Low / Average / High]
  - Low: XX Gwei (~$X.XX for transfer)
  - Average: XX Gwei (~$X.XX for transfer)
  - High: XX Gwei (~$X.XX for transfer)
  - Updated: XX seconds ago

[Таблица: стоимость типичных транзакций]
  - ETH Transfer:         $X.XX
  - ERC-20 Transfer:      $X.XX
  - Uniswap Swap:         $X.XX
  - NFT Mint:             $X.XX
  - Contract Deployment:  $X.XX

[График: gas prices за последние 24h / 7d]
[Best Time to Transact — визуализация по часам]
[What is Gas in Ethereum — объяснение]
[FAQ]
```

### Auto-refresh

```javascript
// Обновлять данные каждые 15 секунд через AJAX
// Endpoint: /api/v1/gas-prices
// Серверная часть кэширует в Redis (TTL 10 сек)
```

---

## Общие правила для всех инструментов

### CSS

Минимальный CSS, единый для всех калькуляторов. Не использовать CSS-фреймворки. Стили inline-critical для PageSpeed:

```css
.tool-widget {
    background: #f8f9fa;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 24px;
    margin: 24px 0;
    max-width: 600px;
}
.tool-widget input[type="number"],
.tool-widget select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 16px; /* предотвращает zoom на iOS */
}
.tool-result { margin-top: 16px; padding: 16px; border-radius: 4px; }
.tool-result.positive { background: #ecfdf5; color: #065f46; }
.tool-result.negative { background: #fef2f2; color: #991b1b; }
```

### Доступность

- Все input'ы с `<label>`
- `aria-live="polite"` на блоке результатов (обновляется без перезагрузки)
- Корректная работа без JS (форма отправляется GET, результат рендерится сервером)

### Числовое форматирование

```javascript
// Единый хелпер для всех калькуляторов
function formatCurrency(value, currency = 'USD') {
    return new Intl.NumberFormat('en-US', { 
        style: 'currency', 
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2 
    }).format(value);
}

function formatCrypto(value, decimals = 8) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: decimals
    }).format(value);
}

function formatPercent(value) {
    const sign = value >= 0 ? '+' : '';
    return sign + value.toFixed(2) + '%';
}
```

### Роутинг — единый ToolsController

```php
// src/Controller/Frontend/ToolsController.php

class ToolsController extends AbstractController
{
    #[Route('/tools', name: 'tools_index')]
    public function index(): Response

    #[Route('/tools/crypto-profit-calculator', name: 'tool_profit_calculator')]
    public function profitCalculator(): Response

    #[Route('/tools/dca-calculator/{coin}', name: 'tool_dca_calculator', defaults: ['coin' => 'bitcoin'])]
    public function dcaCalculator(string $coin): Response

    #[Route('/tools/mining-calculator/{coin}', name: 'tool_mining_calculator', defaults: ['coin' => 'bitcoin'])]
    public function miningCalculator(string $coin): Response

    #[Route('/tools/crypto-tax-calculator', name: 'tool_tax_calculator')]
    public function taxCalculator(): Response

    #[Route('/tools/gas-tracker/{network}', name: 'tool_gas_tracker', defaults: ['network' => 'ethereum'])]
    public function gasTracker(string $network): Response
}
```

---

## Порядок реализации в Claude Code

```
Шаг 1: Создай base_tool.html.twig layout.
        Создай единый CSS-файл для всех инструментов.
        Создай JS-хелпер с функциями форматирования.

Шаг 2: Profit/Loss Calculator (самый простой — чистый JS, без API).
        Проверь: ввод данных → корректный расчёт → результат.

Шаг 3: DCA Calculator.
        Создай API эндпоинт /api/v1/dca/calculate.
        Подключи CoinGeckoClient для исторических данных.
        Создай JS для AJAX-запроса и отрисовки графика.

Шаг 4: Mining Calculator.
        Hardcode данные для BTC, LTC, XMR, KAS.
        Создай UpdateMiningDataCommand (cron).

Шаг 5: Tax Calculator (чистый JS, hardcoded rates).

Шаг 6: Gas Tracker.
        Создай API интеграцию с Etherscan gas API.
        Реализуй auto-refresh через JS.

Шаг 7: /tools index page со всеми инструментами.

Шаг 8: Обнови sitemap — добавь все /tools/* страницы.
```
