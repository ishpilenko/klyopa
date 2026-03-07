<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Site;
use App\Entity\Tag;
use App\Entity\Tool;
use App\Enum\ArticleSchemaType;
use App\Enum\ArticleStatus;
use App\Enum\SiteVertical;
use App\Enum\ToolType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $sites = $this->createSites($manager);
        $this->createCategoriesAndArticles($manager, $sites);
        $this->createTools($manager, $sites);

        $manager->flush();
    }

    /** @return Site[] */
    private function createSites(ObjectManager $manager): array
    {
        $cryptoSite = (new Site())
            ->setDomain('crypto.localhost')
            ->setName('CryptoInsider')
            ->setVertical(SiteVertical::Crypto)
            ->setTheme('crypto')
            ->setLocale('en')
            ->setDefaultMetaTitle('CryptoInsider — Latest Crypto News & Analysis')
            ->setDefaultMetaDescription('In-depth cryptocurrency news, price analysis, and market insights.')
            ->setSettings([
                'rss_feeds' => [
                    'https://cointelegraph.com/rss',
                    'https://decrypt.co/feed',
                ],
                'auto_publish' => false,
                'default_word_count' => 1500,
                'prompt_template' => 'crypto/news_article',
                'content_refresh_days' => 30,
                'max_daily_articles' => 10,
                'internal_links_per_article' => 5,
                'monetization' => [
                    'ad_network' => 'mediavine',
                    'ad_positions' => ['after_intro', 'mid_content', 'before_conclusion'],
                ],
            ]);

        $financeSite = (new Site())
            ->setDomain('finance.localhost')
            ->setName('FinanceHub')
            ->setVertical(SiteVertical::Finance)
            ->setTheme('finance')
            ->setLocale('en')
            ->setDefaultMetaTitle('FinanceHub — Personal Finance & Investment Guides')
            ->setDefaultMetaDescription('Expert personal finance advice, investment strategies, and financial planning tools.')
            ->setSettings([
                'auto_publish' => false,
                'default_word_count' => 2000,
                'prompt_template' => 'finance/guide',
                'content_refresh_days' => 60,
                'max_daily_articles' => 5,
                'internal_links_per_article' => 7,
            ]);

        $gamblingSite = (new Site())
            ->setDomain('gambling.localhost')
            ->setName('GamblingPro')
            ->setVertical(SiteVertical::Gambling)
            ->setTheme('gambling')
            ->setLocale('en')
            ->setDefaultMetaTitle('GamblingPro — Strategies, Odds & Casino Guides')
            ->setDefaultMetaDescription('Casino game strategies, odds calculators, and gambling guides for informed players.')
            ->setSettings([
                'auto_publish' => false,
                'default_word_count' => 1200,
                'prompt_template' => 'gambling/guide',
                'content_refresh_days' => 45,
                'max_daily_articles' => 8,
                'internal_links_per_article' => 4,
            ]);

        $manager->persist($cryptoSite);
        $manager->persist($financeSite);
        $manager->persist($gamblingSite);

        return [
            'crypto' => $cryptoSite,
            'finance' => $financeSite,
            'gambling' => $gamblingSite,
        ];
    }

    /** @param Site[] $sites */
    private function createCategoriesAndArticles(ObjectManager $manager, array $sites): void
    {
        // Crypto site categories
        $cryptoCategories = $this->createCategories($manager, $sites['crypto'], [
            ['name' => 'Bitcoin', 'slug' => 'bitcoin', 'description' => 'Bitcoin news, price analysis and guides'],
            ['name' => 'Ethereum', 'slug' => 'ethereum', 'description' => 'Ethereum ecosystem updates'],
            ['name' => 'DeFi', 'slug' => 'defi', 'description' => 'Decentralized Finance insights'],
            ['name' => 'Market Analysis', 'slug' => 'market-analysis', 'description' => 'Crypto market trends and analysis'],
        ]);

        // Finance site categories
        $financeCategories = $this->createCategories($manager, $sites['finance'], [
            ['name' => 'Investing', 'slug' => 'investing', 'description' => 'Investment strategies and tips'],
            ['name' => 'Personal Finance', 'slug' => 'personal-finance', 'description' => 'Budgeting, saving and debt management'],
            ['name' => 'Retirement', 'slug' => 'retirement', 'description' => 'Retirement planning guides'],
        ]);

        // Gambling site categories
        $gamblingCategories = $this->createCategories($manager, $sites['gambling'], [
            ['name' => 'Casino Games', 'slug' => 'casino-games', 'description' => 'Strategies for casino games'],
            ['name' => 'Sports Betting', 'slug' => 'sports-betting', 'description' => 'Sports betting guides and tips'],
            ['name' => 'Poker', 'slug' => 'poker', 'description' => 'Poker strategies and tournament guides'],
        ]);

        // Sample articles for crypto site
        $this->createArticle(
            $manager,
            $sites['crypto'],
            $cryptoCategories['bitcoin'],
            title: 'Bitcoin Price Analysis: Key Support and Resistance Levels to Watch',
            slug: 'bitcoin-price-analysis-key-support-resistance-levels',
            excerpt: 'In-depth technical analysis of Bitcoin price action, identifying critical support and resistance zones for traders.',
            content: $this->getBitcoinArticleContent(),
            tags: ['bitcoin', 'price-analysis', 'technical-analysis'],
            schemaType: ArticleSchemaType::NewsArticle,
        );

        $this->createArticle(
            $manager,
            $sites['crypto'],
            $cryptoCategories['defi'],
            title: 'Top 5 DeFi Protocols by Total Value Locked in 2026',
            slug: 'top-defi-protocols-total-value-locked-2026',
            excerpt: 'A comprehensive overview of the leading DeFi protocols dominating the decentralized finance space.',
            content: '<h2>DeFi Market Overview</h2><p>The decentralized finance sector continues to evolve rapidly...</p>',
            tags: ['defi', 'tvl', 'protocols'],
            schemaType: ArticleSchemaType::Article,
        );

        // Sample article for finance site
        $this->createArticle(
            $manager,
            $sites['finance'],
            $financeCategories['investing'],
            title: 'Compound Interest Calculator: How Your Money Grows Over Time',
            slug: 'compound-interest-calculator-money-grows-over-time',
            excerpt: 'Understand the power of compound interest and learn how to calculate your investment growth.',
            content: '<h2>What is Compound Interest?</h2><p>Compound interest is interest calculated on both the initial principal...</p>',
            tags: ['compound-interest', 'investing', 'calculator'],
        );
    }

    /**
     * @param array<array{name: string, slug: string, description: string}> $categoriesData
     * @return array<string, Category>
     */
    private function createCategories(ObjectManager $manager, Site $site, array $categoriesData): array
    {
        $categories = [];
        foreach ($categoriesData as $i => $data) {
            $category = (new Category())
                ->setSite($site)
                ->setName($data['name'])
                ->setSlug($data['slug'])
                ->setDescription($data['description'])
                ->setSortOrder($i);

            $manager->persist($category);
            $categories[$data['slug']] = $category;
        }

        return $categories;
    }

    private function createArticle(
        ObjectManager $manager,
        Site $site,
        Category $category,
        string $title,
        string $slug,
        string $excerpt,
        string $content,
        array $tags = [],
        ArticleSchemaType $schemaType = ArticleSchemaType::Article,
    ): Article {
        $article = (new Article())
            ->setSite($site)
            ->setCategory($category)
            ->setTitle($title)
            ->setSlug($slug)
            ->setExcerpt($excerpt)
            ->setContent($content)
            ->setSchemaType($schemaType)
            ->setMetaTitle($title)
            ->setMetaDescription($excerpt)
            ->setWordCount(str_word_count(strip_tags($content)))
            ->setReadingTimeMinutes(max(1, (int) ceil(str_word_count(strip_tags($content)) / 200)))
            ->publish();

        foreach ($tags as $tagName) {
            $tag = (new Tag())
                ->setSite($site)
                ->setName($tagName)
                ->setSlug($tagName);

            $manager->persist($tag);
            $article->addTag($tag);
        }

        $manager->persist($article);

        return $article;
    }

    /** @param Site[] $sites */
    private function createTools(ObjectManager $manager, array $sites): void
    {
        // ── Finance tools ──────────────────────────────────────────────────

        $tool = (new Tool())
            ->setSite($sites['finance'])
            ->setType(ToolType::Calculator)
            ->setName('Compound Interest Calculator')
            ->setSlug('compound-interest')
            ->setDescription('Calculate how your investment grows over time with the power of compound interest.')
            ->setMetaTitle('Compound Interest Calculator — Free Online Tool')
            ->setMetaDescription('Calculate compound interest on any investment. Enter principal, rate, years and compounding frequency to see your future value.')
            ->setConfig([
                'fields' => [
                    ['name' => 'principal', 'label' => 'Initial Investment', 'type' => 'number', 'default' => '10000', 'min' => 0, 'step' => 100, 'prefix' => '$'],
                    ['name' => 'rate', 'label' => 'Annual Interest Rate', 'type' => 'number', 'default' => '7', 'min' => 0, 'max' => 100, 'step' => 0.1, 'suffix' => '%'],
                    ['name' => 'years', 'label' => 'Investment Period', 'type' => 'number', 'default' => '10', 'min' => 1, 'max' => 50, 'step' => 1, 'suffix' => 'years'],
                    ['name' => 'freq', 'label' => 'Compounding Frequency', 'type' => 'select', 'default' => '12', 'options' => [
                        ['value' => '1', 'label' => 'Annually'],
                        ['value' => '4', 'label' => 'Quarterly'],
                        ['value' => '12', 'label' => 'Monthly'],
                        ['value' => '365', 'label' => 'Daily'],
                    ]],
                ],
                'outputs' => [
                    ['name' => 'future_value', 'label' => 'Future Value', 'format' => 'currency', 'prefix' => '$', 'highlight' => true,
                        'formula' => 'principal * Math.pow(1 + rate/100/freq, freq*years)'],
                    ['name' => 'total_interest', 'label' => 'Total Interest Earned', 'format' => 'currency', 'prefix' => '$',
                        'formula' => 'principal * Math.pow(1 + rate/100/freq, freq*years) - principal'],
                    ['name' => 'total_return', 'label' => 'Total Return', 'format' => 'percent',
                        'formula' => '(Math.pow(1 + rate/100/freq, freq*years) - 1) * 100'],
                ],
                'how_to_use' => 'Enter your initial investment amount, the expected annual interest rate, the number of years you plan to invest, and how often interest is compounded. Click Calculate to see your projected future value.',
                'formula_explanation' => 'Future Value = Principal × (1 + Rate/Frequency)^(Frequency × Years). For example, $10,000 at 7% compounded monthly for 10 years grows to $20,097.',
            ]);
        $manager->persist($tool);

        $tool = (new Tool())
            ->setSite($sites['finance'])
            ->setType(ToolType::Calculator)
            ->setName('Loan Calculator')
            ->setSlug('loan-calculator')
            ->setDescription('Calculate your monthly loan payment, total interest paid, and full repayment cost for any loan.')
            ->setMetaTitle('Loan Calculator — Monthly Payment & Total Cost')
            ->setMetaDescription('Free loan calculator. Enter loan amount, interest rate and term to calculate monthly payments and total interest cost.')
            ->setConfig([
                'fields' => [
                    ['name' => 'amount', 'label' => 'Loan Amount', 'type' => 'number', 'default' => '20000', 'min' => 100, 'step' => 100, 'prefix' => '$'],
                    ['name' => 'rate', 'label' => 'Annual Interest Rate', 'type' => 'number', 'default' => '6.5', 'min' => 0, 'max' => 100, 'step' => 0.1, 'suffix' => '%'],
                    ['name' => 'term', 'label' => 'Loan Term', 'type' => 'number', 'default' => '60', 'min' => 1, 'max' => 360, 'step' => 1, 'suffix' => 'months'],
                ],
                'outputs' => [
                    ['name' => 'monthly_payment', 'label' => 'Monthly Payment', 'format' => 'currency', 'prefix' => '$', 'highlight' => true,
                        'formula' => '(function(){ var r = rate/100/12; return r === 0 ? amount/term : amount * r * Math.pow(1+r,term) / (Math.pow(1+r,term) - 1); })()'],
                    ['name' => 'total_payment', 'label' => 'Total Amount Paid', 'format' => 'currency', 'prefix' => '$',
                        'formula' => '(function(){ var r = rate/100/12; var mp = r === 0 ? amount/term : amount * r * Math.pow(1+r,term) / (Math.pow(1+r,term) - 1); return mp * term; })()'],
                    ['name' => 'total_interest', 'label' => 'Total Interest Paid', 'format' => 'currency', 'prefix' => '$',
                        'formula' => '(function(){ var r = rate/100/12; var mp = r === 0 ? amount/term : amount * r * Math.pow(1+r,term) / (Math.pow(1+r,term) - 1); return mp * term - amount; })()'],
                ],
                'how_to_use' => 'Enter the total loan amount, the annual interest rate offered by your lender, and the loan term in months. The calculator uses the standard amortization formula to show your monthly payment and total cost.',
                'formula_explanation' => 'Monthly Payment = P × r(1+r)^n / ((1+r)^n − 1), where P = principal, r = monthly rate (annual rate ÷ 12), n = number of payments.',
            ]);
        $manager->persist($tool);

        // ── Crypto tools ───────────────────────────────────────────────────

        $tool = (new Tool())
            ->setSite($sites['crypto'])
            ->setType(ToolType::Calculator)
            ->setName('Crypto Profit & Loss Calculator')
            ->setSlug('crypto-profit-calculator')
            ->setDescription('Quickly calculate your profit or loss on any cryptocurrency trade, including fees.')
            ->setMetaTitle('Crypto Profit & Loss Calculator — Free Tool')
            ->setMetaDescription('Calculate crypto trading profit or loss. Enter buy price, sell price, amount and fees to see your net P&L and ROI.')
            ->setConfig([
                'fields' => [
                    ['name' => 'buy_price', 'label' => 'Buy Price', 'type' => 'number', 'default' => '40000', 'min' => 0, 'step' => 0.01, 'prefix' => '$'],
                    ['name' => 'sell_price', 'label' => 'Sell Price', 'type' => 'number', 'default' => '50000', 'min' => 0, 'step' => 0.01, 'prefix' => '$'],
                    ['name' => 'coins', 'label' => 'Amount of Coins', 'type' => 'number', 'default' => '0.5', 'min' => 0, 'step' => 0.0001],
                    ['name' => 'fee_pct', 'label' => 'Trading Fee (each side)', 'type' => 'number', 'default' => '0.1', 'min' => 0, 'max' => 10, 'step' => 0.01, 'suffix' => '%'],
                ],
                'outputs' => [
                    ['name' => 'net_profit', 'label' => 'Net Profit / Loss', 'format' => 'currency', 'prefix' => '$', 'highlight' => true,
                        'formula' => '(sell_price - buy_price) * coins - (buy_price * coins * fee_pct/100) - (sell_price * coins * fee_pct/100)'],
                    ['name' => 'roi', 'label' => 'ROI', 'format' => 'percent',
                        'formula' => '((sell_price - buy_price) * coins - (buy_price * coins * fee_pct/100) - (sell_price * coins * fee_pct/100)) / (buy_price * coins) * 100'],
                    ['name' => 'total_invested', 'label' => 'Total Invested', 'format' => 'currency', 'prefix' => '$',
                        'formula' => 'buy_price * coins * (1 + fee_pct/100)'],
                    ['name' => 'total_proceeds', 'label' => 'Total Proceeds', 'format' => 'currency', 'prefix' => '$',
                        'formula' => 'sell_price * coins * (1 - fee_pct/100)'],
                ],
                'how_to_use' => 'Enter your buy price, sell price, the number of coins traded, and the exchange trading fee percentage. The calculator accounts for fees on both the buy and sell sides.',
                'formula_explanation' => 'Net Profit = (Sell Price − Buy Price) × Coins − Buy Fee − Sell Fee. ROI = Net Profit ÷ Total Invested × 100.',
            ]);
        $manager->persist($tool);

        $tool = (new Tool())
            ->setSite($sites['crypto'])
            ->setType(ToolType::Calculator)
            ->setName('DCA Calculator')
            ->setSlug('dca-calculator')
            ->setDescription('Calculate the results of a Dollar-Cost Averaging (DCA) strategy for any cryptocurrency.')
            ->setMetaTitle('Crypto DCA Calculator — Dollar-Cost Averaging Tool')
            ->setMetaDescription('Calculate your average buy price and portfolio value using a DCA strategy. Enter weekly investment, number of weeks, and price range.')
            ->setConfig([
                'fields' => [
                    ['name' => 'weekly_invest', 'label' => 'Weekly Investment', 'type' => 'number', 'default' => '100', 'min' => 1, 'step' => 10, 'prefix' => '$'],
                    ['name' => 'weeks', 'label' => 'Number of Weeks', 'type' => 'number', 'default' => '52', 'min' => 1, 'max' => 520, 'step' => 1],
                    ['name' => 'start_price', 'label' => 'Starting Price', 'type' => 'number', 'default' => '30000', 'min' => 0.01, 'step' => 100, 'prefix' => '$'],
                    ['name' => 'end_price', 'label' => 'Current / End Price', 'type' => 'number', 'default' => '50000', 'min' => 0.01, 'step' => 100, 'prefix' => '$'],
                ],
                'outputs' => [
                    ['name' => 'total_invested', 'label' => 'Total Invested', 'format' => 'currency', 'prefix' => '$',
                        'formula' => 'weekly_invest * weeks'],
                    ['name' => 'avg_price', 'label' => 'Average Buy Price', 'format' => 'currency', 'prefix' => '$', 'highlight' => true,
                        'formula' => '(start_price + end_price) / 2'],
                    ['name' => 'total_coins', 'label' => 'Total Coins Accumulated', 'format' => 'number', 'decimals' => 6,
                        'formula' => 'weekly_invest * weeks / ((start_price + end_price) / 2)'],
                    ['name' => 'current_value', 'label' => 'Current Portfolio Value', 'format' => 'currency', 'prefix' => '$',
                        'formula' => '(weekly_invest * weeks / ((start_price + end_price) / 2)) * end_price'],
                    ['name' => 'roi', 'label' => 'ROI', 'format' => 'percent',
                        'formula' => '((weekly_invest * weeks / ((start_price + end_price) / 2)) * end_price - weekly_invest * weeks) / (weekly_invest * weeks) * 100'],
                ],
                'how_to_use' => 'Enter how much you invest each week, for how many weeks, and the price range from start to end. The calculator uses a linear price average to estimate your average cost basis and final portfolio value.',
                'formula_explanation' => 'Average Price ≈ (Start Price + End Price) / 2 (linear approximation). Total Coins = Total Invested ÷ Average Price. ROI = (Current Value − Total Invested) ÷ Total Invested × 100.',
            ]);
        $manager->persist($tool);

        // ── Gambling tools ─────────────────────────────────────────────────

        $tool = (new Tool())
            ->setSite($sites['gambling'])
            ->setType(ToolType::Converter)
            ->setName('Odds Converter')
            ->setSlug('odds-converter')
            ->setDescription('Convert betting odds between decimal, fractional, and American formats instantly.')
            ->setMetaTitle('Odds Converter — Decimal, Fractional & American')
            ->setMetaDescription('Free betting odds converter. Convert between decimal, fractional, and American odds formats. See implied probability and potential payout.')
            ->setConfig([
                'tool_type' => 'converter',
                'input_fields' => [
                    ['name' => 'format', 'label' => 'Odds Format', 'type' => 'select', 'options' => [
                        ['value' => 'decimal', 'label' => 'Decimal (e.g. 2.50)'],
                        ['value' => 'fractional', 'label' => 'Fractional (e.g. 3/2)'],
                        ['value' => 'american', 'label' => 'American (e.g. +150)'],
                    ]],
                    ['name' => 'value', 'label' => 'Odds Value', 'type' => 'text', 'placeholder' => 'e.g. 2.50', 'help' => 'Enter the odds in the selected format. For American odds, include the + or − sign.'],
                ],
                'outputs' => [
                    ['name' => 'decimal', 'label' => 'Decimal Odds', 'highlight' => true],
                    ['name' => 'fractional', 'label' => 'Fractional Odds'],
                    ['name' => 'american', 'label' => 'American Odds'],
                    ['name' => 'implied_prob', 'label' => 'Implied Probability'],
                    ['name' => 'payout', 'label' => 'Profit on $100 Stake'],
                ],
                'how_to_use' => 'Select your odds format, enter the odds value, and click Convert. The tool instantly shows all three formats plus the implied probability and potential profit on a $100 stake.',
                'formula_explanation' => 'Implied Probability = 1 ÷ Decimal Odds × 100. Decimal to American: if ≥ 2.0 → +(Decimal−1)×100; if < 2.0 → −100÷(Decimal−1).',
            ]);
        $manager->persist($tool);

        $tool = (new Tool())
            ->setSite($sites['gambling'])
            ->setType(ToolType::Probability)
            ->setName('Probability Calculator')
            ->setSlug('probability-calculator')
            ->setDescription('Calculate the probability of any event and express it as a percentage, ratio, or decimal odds.')
            ->setMetaTitle('Probability Calculator — Odds & Percentages')
            ->setMetaDescription('Calculate event probability as a percentage, fraction, and decimal odds. Enter favorable and total outcomes.')
            ->setConfig([
                'fields' => [
                    ['name' => 'favorable', 'label' => 'Favorable Outcomes', 'type' => 'number', 'default' => '1', 'min' => 0, 'step' => 1],
                    ['name' => 'total', 'label' => 'Total Possible Outcomes', 'type' => 'number', 'default' => '6', 'min' => 1, 'step' => 1],
                ],
                'outputs' => [
                    ['name' => 'probability', 'label' => 'Probability', 'format' => 'percent', 'highlight' => true,
                        'formula' => 'favorable / total * 100'],
                    ['name' => 'odds_for', 'label' => 'Odds For', 'format' => 'ratio',
                        'formula' => 'favorable / (total - favorable)'],
                    ['name' => 'odds_against', 'label' => 'Odds Against', 'format' => 'ratio',
                        'formula' => '(total - favorable) / favorable'],
                    ['name' => 'decimal_odds', 'label' => 'Decimal Odds', 'format' => 'number', 'decimals' => 3,
                        'formula' => 'total / favorable'],
                ],
                'how_to_use' => 'Enter the number of favorable outcomes and the total number of possible outcomes. For example, to find the probability of rolling a 6 on a die, enter 1 favorable and 6 total.',
                'formula_explanation' => 'Probability (%) = Favorable ÷ Total × 100. Odds For = Favorable : (Total − Favorable). Decimal Odds = Total ÷ Favorable.',
            ]);
        $manager->persist($tool);

        $tool = (new Tool())
            ->setSite($sites['gambling'])
            ->setType(ToolType::Calculator)
            ->setName('Expected Value Calculator')
            ->setSlug('expected-value-calculator')
            ->setDescription('Calculate the expected value (EV) of any bet to determine if it has a positive or negative edge.')
            ->setMetaTitle('Expected Value Calculator — Is Your Bet +EV?')
            ->setMetaDescription('Calculate the expected value of any bet. Enter win probability, win amount and loss amount to see if you have a positive edge.')
            ->setConfig([
                'fields' => [
                    ['name' => 'win_prob', 'label' => 'Win Probability', 'type' => 'number', 'default' => '55', 'min' => 0, 'max' => 100, 'step' => 0.1, 'suffix' => '%'],
                    ['name' => 'win_amount', 'label' => 'Amount Won (net)', 'type' => 'number', 'default' => '100', 'min' => 0, 'step' => 1, 'prefix' => '$'],
                    ['name' => 'loss_amount', 'label' => 'Amount Lost (stake)', 'type' => 'number', 'default' => '110', 'min' => 0, 'step' => 1, 'prefix' => '$'],
                ],
                'outputs' => [
                    ['name' => 'ev', 'label' => 'Expected Value per Bet', 'format' => 'currency', 'prefix' => '$', 'highlight' => true,
                        'formula' => '(win_prob/100) * win_amount - (1 - win_prob/100) * loss_amount'],
                    ['name' => 'roi', 'label' => 'ROI per Bet', 'format' => 'percent',
                        'formula' => '((win_prob/100) * win_amount - (1 - win_prob/100) * loss_amount) / loss_amount * 100'],
                    ['name' => 'breakeven', 'label' => 'Break-Even Win Rate', 'format' => 'percent',
                        'formula' => 'loss_amount / (win_amount + loss_amount) * 100'],
                ],
                'how_to_use' => 'Enter your estimated win probability (%), how much you net when you win, and how much you lose (your stake) when you lose. A positive EV means the bet has an edge in your favor over the long run.',
                'formula_explanation' => 'EV = (Win Probability × Win Amount) − (Loss Probability × Loss Amount). Break-Even Rate = Loss Amount ÷ (Win Amount + Loss Amount) × 100.',
            ]);
        $manager->persist($tool);
    }

    private function getBitcoinArticleContent(): string
    {
        return <<<'HTML'
<h2>TL;DR</h2>
<p>Bitcoin is trading near key resistance at $95,000. A breakout could target $100,000, while support sits at $88,000–$90,000. Watch for volume confirmation on either direction.</p>

<h2>Current Market Structure</h2>
<p>Bitcoin (BTC) has been consolidating in a tight range between $88,000 and $95,000 over the past two weeks. The price action suggests accumulation, with on-chain metrics showing long-term holders continuing to increase their positions.</p>

<h3>Key Resistance Levels</h3>
<ul>
    <li><strong>$95,000</strong> — Current major resistance, multiple rejections noted</li>
    <li><strong>$100,000</strong> — Psychological round number, expected high selling pressure</li>
    <li><strong>$110,000</strong> — 2025 ATH, longer-term target if momentum sustains</li>
</ul>

<h3>Key Support Levels</h3>
<ul>
    <li><strong>$90,000</strong> — Short-term demand zone, 20-day EMA</li>
    <li><strong>$88,000</strong> — Strong support, previous breakout level</li>
    <li><strong>$82,000</strong> — Major confluence support, 50-day MA and prior ATH</li>
</ul>

<h2>Technical Indicators</h2>
<p>The RSI on the daily chart reads 58, indicating room for further upside without entering overbought territory. The MACD shows a bullish crossover forming on the 4-hour chart, suggesting short-term momentum is building.</p>

<h2>On-Chain Metrics</h2>
<p>Exchange outflows have averaged 3,200 BTC/day this week, historically a bullish signal indicating HODLing behavior. The NUPL (Net Unrealized Profit/Loss) sits at 0.62, in the "Belief" phase — typically seen in mid-cycle bull markets.</p>

<h2>Conclusion</h2>
<p>Bitcoin's technical structure remains constructive. A daily close above $95,000 with volume would confirm the next leg up toward $100,000. Traders should monitor the $88,000 support as a line in the sand for short-term bulls. The macro environment, particularly Fed policy developments, could act as a catalyst in either direction over the coming weeks.</p>
HTML;
    }
}
