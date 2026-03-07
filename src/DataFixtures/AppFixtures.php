<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Site;
use App\Entity\Tag;
use App\Enum\ArticleSchemaType;
use App\Enum\ArticleStatus;
use App\Enum\SiteVertical;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $sites = $this->createSites($manager);
        $this->createCategoriesAndArticles($manager, $sites);

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
