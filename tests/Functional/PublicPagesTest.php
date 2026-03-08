<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke tests for all public frontend pages across all three sites.
 *
 * Each test creates a fresh client with the correct HTTP_HOST so the
 * SiteResolverSubscriber picks the right Site entity and the Doctrine
 * SiteFilter scopes all queries to that site.
 *
 * Pages that call the CoinGecko API still return 200 on API failure
 * because controllers fall back to DB data or render with null values.
 */
class PublicPagesTest extends WebTestCase
{
    /**
     * @dataProvider publicPagesProvider
     */
    public function testPageReturnsExpectedStatus(string $host, string $url, int $expected = 200): void
    {
        $client = static::createClient([], ['HTTP_HOST' => $host]);
        $client->request('GET', $url);

        $this->assertResponseStatusCodeSame(
            $expected,
            sprintf('Failed asserting status %d for %s%s', $expected, $host, $url),
        );
    }

    public static function publicPagesProvider(): \Generator
    {
        // ── Crypto site (site_id=4, crypto.localhost) ────────────────────────

        $c = 'crypto.localhost';

        // Home
        yield 'crypto: home'                        => [$c, '/'];

        // Category listings
        yield 'crypto: category bitcoin'            => [$c, '/bitcoin/'];
        yield 'crypto: category ethereum'           => [$c, '/ethereum/'];
        yield 'crypto: category defi'               => [$c, '/defi/'];
        yield 'crypto: category market-analysis'    => [$c, '/market-analysis/'];
        yield 'crypto: category 404'                => [$c, '/nonexistent-category/', 404];

        // Articles
        yield 'crypto: article in bitcoin'          => [$c, '/bitcoin/bitcoin-price-analysis-key-support-resistance-levels'];
        yield 'crypto: article in defi'             => [$c, '/defi/top-defi-protocols-total-value-locked-2026'];
        yield 'crypto: article 404'                 => [$c, '/bitcoin/this-slug-does-not-exist', 404];

        // Tools (DB-driven generic + dedicated Phase-8 routes)
        yield 'crypto: tool profit-calculator'      => [$c, '/tools/crypto-profit-calculator'];
        yield 'crypto: tool dca-calculator'         => [$c, '/tools/dca-calculator'];
        yield 'crypto: tool mining-calculator'      => [$c, '/tools/mining-calculator'];
        yield 'crypto: tool crypto-tax-calculator'  => [$c, '/tools/crypto-tax-calculator'];
        yield 'crypto: tool gas-tracker'            => [$c, '/tools/gas-tracker'];
        yield 'crypto: tool 404'                    => [$c, '/tools/nonexistent-tool', 404];

        // Prices (CoinGecko API — falls back gracefully on rate limit)
        yield 'crypto: prices index'                => [$c, '/prices'];
        yield 'crypto: price bitcoin'               => [$c, '/price/bitcoin'];
        yield 'crypto: price dogecoin'              => [$c, '/price/dogecoin'];
        yield 'crypto: price ethereum'              => [$c, '/price/ethereum'];
        yield 'crypto: price ripple'                => [$c, '/price/ripple'];
        yield 'crypto: price unknown coin 404'      => [$c, '/price/this-coin-does-not-exist-xyz', 404];

        // Converter (CoinGecko API — renders with null rate on failure)
        yield 'crypto: converter index'             => [$c, '/tools/converter'];
        yield 'crypto: convert btc to usd'          => [$c, '/convert/btc-to-usd'];
        yield 'crypto: convert eth to eur'          => [$c, '/convert/eth-to-eur'];
        yield 'crypto: convert btc to eth'          => [$c, '/convert/btc-to-eth'];
        yield 'crypto: convert with amount'         => [$c, '/convert/btc-to-usd/0.5'];

        // Investment calculator
        yield 'crypto: investment calculator'       => [$c, '/tools/investment-calculator/bitcoin'];

        // Glossary
        yield 'crypto: glossary index'              => [$c, '/glossary'];
        yield 'crypto: glossary term blockchain'    => [$c, '/glossary/blockchain'];
        yield 'crypto: glossary term bitcoin'       => [$c, '/glossary/bitcoin'];
        yield 'crypto: glossary term defi'          => [$c, '/glossary/defi'];
        yield 'crypto: glossary term hodl'          => [$c, '/glossary/hodl'];
        yield 'crypto: glossary 404'                => [$c, '/glossary/this-term-does-not-exist-xyz', 404];

        // Fear & Greed Index
        yield 'crypto: fear-greed-index'            => [$c, '/fear-greed-index'];

        // Exchanges listing
        yield 'crypto: reviews exchanges'           => [$c, '/reviews/exchanges'];
        yield 'crypto: reviews wallets'             => [$c, '/reviews/wallets'];

        // Compare
        yield 'crypto: compare index'               => [$c, '/compare'];

        // Affiliate redirect
        yield 'crypto: affiliate binance'           => [$c, '/go/binance', 302];
        yield 'crypto: affiliate coinbase'          => [$c, '/go/coinbase', 302];
        yield 'crypto: affiliate 404'               => [$c, '/go/this-partner-does-not-exist', 404];

        // Sitemaps & robots
        yield 'crypto: sitemap index'               => [$c, '/sitemap.xml'];
        yield 'crypto: sitemap articles page 1'     => [$c, '/sitemap-articles-1.xml'];
        yield 'crypto: sitemap categories'          => [$c, '/sitemap-categories.xml'];
        yield 'crypto: sitemap tools'               => [$c, '/sitemap-tools.xml'];
        yield 'crypto: sitemap prices'              => [$c, '/sitemap-prices.xml'];
        yield 'crypto: sitemap converter'           => [$c, '/sitemap-converter.xml'];
        yield 'crypto: sitemap glossary'            => [$c, '/sitemap-glossary.xml'];
        yield 'crypto: robots.txt'                  => [$c, '/robots.txt'];

        // Generic 404
        yield 'crypto: 404 unknown page'            => [$c, '/this-page-does-not-exist-xyz123/', 404];

        // ── Finance site (site_id=5, finance.localhost) ──────────────────────

        $f = 'finance.localhost';

        yield 'finance: home'                       => [$f, '/'];

        yield 'finance: category investing'         => [$f, '/investing/'];
        yield 'finance: category personal-finance'  => [$f, '/personal-finance/'];
        yield 'finance: category retirement'        => [$f, '/retirement/'];
        yield 'finance: category 404'               => [$f, '/nonexistent-category/', 404];

        yield 'finance: article'                    => [$f, '/investing/compound-interest-calculator-money-grows-over-time'];
        yield 'finance: article 404'                => [$f, '/investing/this-slug-does-not-exist', 404];

        yield 'finance: tool compound-interest'     => [$f, '/tools/compound-interest'];
        yield 'finance: tool loan-calculator'       => [$f, '/tools/loan-calculator'];
        yield 'finance: tool 404'                   => [$f, '/tools/nonexistent-tool', 404];

        yield 'finance: sitemap index'              => [$f, '/sitemap.xml'];
        yield 'finance: sitemap articles page 1'    => [$f, '/sitemap-articles-1.xml'];
        yield 'finance: sitemap categories'         => [$f, '/sitemap-categories.xml'];
        yield 'finance: sitemap tools'              => [$f, '/sitemap-tools.xml'];
        yield 'finance: robots.txt'                 => [$f, '/robots.txt'];

        yield 'finance: 404 unknown page'           => [$f, '/this-page-does-not-exist-xyz123/', 404];

        // ── Gambling site (site_id=6, gambling.localhost) ────────────────────

        $g = 'gambling.localhost';

        yield 'gambling: home'                      => [$g, '/'];

        yield 'gambling: category casino-games'     => [$g, '/casino-games/'];
        yield 'gambling: category sports-betting'   => [$g, '/sports-betting/'];
        yield 'gambling: category poker'            => [$g, '/poker/'];
        yield 'gambling: category 404'              => [$g, '/nonexistent-category/', 404];

        yield 'gambling: tool odds-converter'       => [$g, '/tools/odds-converter'];
        yield 'gambling: tool probability'          => [$g, '/tools/probability-calculator'];
        yield 'gambling: tool expected-value'       => [$g, '/tools/expected-value-calculator'];
        yield 'gambling: tool 404'                  => [$g, '/tools/nonexistent-tool', 404];

        yield 'gambling: sitemap index'             => [$g, '/sitemap.xml'];
        yield 'gambling: sitemap articles page 1'   => [$g, '/sitemap-articles-1.xml'];
        yield 'gambling: sitemap categories'        => [$g, '/sitemap-categories.xml'];
        yield 'gambling: sitemap tools'             => [$g, '/sitemap-tools.xml'];
        yield 'gambling: robots.txt'                => [$g, '/robots.txt'];

        yield 'gambling: 404 unknown page'          => [$g, '/this-page-does-not-exist-xyz123/', 404];
    }
}
