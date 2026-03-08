<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed Phase 9 data: glossary terms, exchange data, affiliate links for crypto site';
    }

    public function up(Schema $schema): void
    {
        // Get crypto site id
        $this->addSql(<<<'SQL'
            SET @crypto_site_id = (SELECT id FROM sites WHERE domain = 'crypto.localhost' LIMIT 1)
        SQL);

        // ── Affiliate links ───────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO affiliate_links
                (site_id, partner, partner_type, base_url, utm_source, utm_medium, utm_campaign, display_name, is_active, clicks, created_at)
            VALUES
                (@crypto_site_id, 'binance',      'exchange', 'https://www.binance.com/en/register',          'cryptoinsider', 'affiliate', 'exchange-review', 'Binance',       1, 0, NOW()),
                (@crypto_site_id, 'coinbase',     'exchange', 'https://www.coinbase.com/join/',               'cryptoinsider', 'affiliate', 'exchange-review', 'Coinbase',      1, 0, NOW()),
                (@crypto_site_id, 'kraken',       'exchange', 'https://www.kraken.com/sign-up',              'cryptoinsider', 'affiliate', 'exchange-review', 'Kraken',        1, 0, NOW()),
                (@crypto_site_id, 'bybit',        'exchange', 'https://www.bybit.com/invite',                'cryptoinsider', 'affiliate', 'exchange-review', 'Bybit',         1, 0, NOW()),
                (@crypto_site_id, 'okx',          'exchange', 'https://www.okx.com/join',                   'cryptoinsider', 'affiliate', 'exchange-review', 'OKX',           1, 0, NOW()),
                (@crypto_site_id, 'kucoin',       'exchange', 'https://www.kucoin.com/r/',                  'cryptoinsider', 'affiliate', 'exchange-review', 'KuCoin',        1, 0, NOW()),
                (@crypto_site_id, 'ledger',       'wallet',   'https://shop.ledger.com/?r=',                'cryptoinsider', 'affiliate', 'wallet-review',   'Ledger',        1, 0, NOW()),
                (@crypto_site_id, 'trezor',       'wallet',   'https://trezor.io/',                         'cryptoinsider', 'affiliate', 'wallet-review',   'Trezor',        1, 0, NOW()),
                (@crypto_site_id, 'metamask',     'wallet',   'https://metamask.io/download/',              'cryptoinsider', 'affiliate', 'wallet-review',   'MetaMask',      1, 0, NOW()),
                (@crypto_site_id, 'coinledger',   'service',  'https://coinledger.io/?via=',                'cryptoinsider', 'affiliate', 'tax-tool',        'CoinLedger',    1, 0, NOW())
        SQL);

        // ── Exchange data ─────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO exchange_data
                (site_id, name, slug, rating, founded_year, headquarters, supported_coins,
                 trading_fee_maker, trading_fee_taker, has_mobile_app, is_regulated, kyc_required,
                 affiliate_url, created_at, updated_at)
            VALUES
                (@crypto_site_id, 'Binance',  'binance',  9.2, 2017, 'Cayman Islands', 350, 0.0010, 0.0010, 1, 0, 1, 'https://www.binance.com/en/register', NOW(), NOW()),
                (@crypto_site_id, 'Coinbase', 'coinbase', 8.8, 2012, 'USA',            250, 0.0040, 0.0060, 1, 1, 1, 'https://www.coinbase.com/join/',       NOW(), NOW()),
                (@crypto_site_id, 'Kraken',   'kraken',   8.9, 2011, 'USA',            220, 0.0016, 0.0026, 1, 1, 1, 'https://www.kraken.com/sign-up',      NOW(), NOW()),
                (@crypto_site_id, 'Bybit',    'bybit',    8.5, 2018, 'UAE',            300, 0.0010, 0.0010, 1, 0, 1, 'https://www.bybit.com/invite',         NOW(), NOW()),
                (@crypto_site_id, 'OKX',      'okx',      8.6, 2017, 'Seychelles',    340, 0.0008, 0.0010, 1, 0, 1, 'https://www.okx.com/join',            NOW(), NOW())
        SQL);

        // ── Glossary terms ────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO glossary_terms
                (site_id, term, slug, short_definition, full_content, first_letter, status, created_at, updated_at)
            VALUES
                (
                    @crypto_site_id,
                    'Blockchain',
                    'blockchain',
                    'A blockchain is a distributed digital ledger that records transactions across a network of computers in a way that is transparent, immutable, and decentralized.',
                    '<h2>What is Blockchain?</h2><p>A blockchain is a type of distributed database or ledger that is shared and synchronized across many computers. Unlike traditional databases controlled by a central authority, a blockchain is maintained by a network of nodes (computers) that each hold a copy of the entire chain.</p><h2>How Blockchain Works</h2><p>Data is grouped into "blocks," each containing a set of transactions, a timestamp, and a cryptographic hash of the previous block. This chaining of blocks makes tampering virtually impossible — changing one block would invalidate all subsequent blocks.</p><h2>Key Properties</h2><ul><li><strong>Decentralization:</strong> No single entity controls the network.</li><li><strong>Immutability:</strong> Once data is written, it cannot be altered.</li><li><strong>Transparency:</strong> All transactions are publicly verifiable.</li><li><strong>Security:</strong> Cryptographic hashing protects data integrity.</li></ul><h2>Real-World Applications</h2><p>Beyond cryptocurrency, blockchain powers supply chain tracking, digital identity, voting systems, smart contracts, and decentralized finance (DeFi).</p>',
                    'B',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'Bitcoin',
                    'bitcoin',
                    'Bitcoin (BTC) is the world''s first and largest cryptocurrency by market cap, created in 2009 by the pseudonymous Satoshi Nakamoto as a decentralized digital currency.',
                    '<h2>What is Bitcoin?</h2><p>Bitcoin is a decentralized digital currency that enables peer-to-peer transactions without a central authority like a bank or government. It was introduced in 2008 via the Bitcoin whitepaper by Satoshi Nakamoto and launched in January 2009.</p><h2>How Bitcoin Works</h2><p>Bitcoin runs on a proof-of-work blockchain. Miners compete to solve complex mathematical puzzles to add new blocks and earn freshly minted BTC as a reward. The total supply is capped at 21 million coins.</p><h2>Bitcoin Halving</h2><p>Every 210,000 blocks (~4 years), the block reward is cut in half — an event called the "halving." This built-in scarcity mechanism is one reason many view Bitcoin as "digital gold."</p><h2>Why Bitcoin Matters</h2><p>Bitcoin introduced the concept of trustless, permissionless money. It remains the dominant store of value in crypto and the most widely accepted digital asset.</p>',
                    'B',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'DeFi',
                    'defi',
                    'DeFi (Decentralized Finance) refers to a set of financial services built on blockchain networks that operate without traditional intermediaries like banks, enabling permissionless lending, trading, and earning.',
                    '<h2>What is DeFi?</h2><p>Decentralized Finance, or DeFi, is an umbrella term for financial applications built on public blockchains — primarily Ethereum — that aim to recreate and improve upon traditional financial systems without central intermediaries.</p><h2>Core DeFi Applications</h2><ul><li><strong>Decentralized Exchanges (DEXs):</strong> Trade tokens directly from your wallet (e.g., Uniswap, Curve).</li><li><strong>Lending Protocols:</strong> Borrow or earn interest on crypto (e.g., Aave, Compound).</li><li><strong>Yield Farming:</strong> Earn returns by providing liquidity to protocols.</li><li><strong>Stablecoins:</strong> Algorithmic or collateral-backed stable assets (e.g., DAI).</li></ul><h2>Total Value Locked (TVL)</h2><p>TVL is the key metric for DeFi — it measures the total amount of assets deposited in DeFi protocols. At peak, TVL exceeded $180 billion.</p><h2>Risks in DeFi</h2><p>Smart contract bugs, rug pulls, impermanent loss, and oracle manipulation are real risks. Always research protocols before depositing funds.</p>',
                    'D',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'NFT',
                    'nft',
                    'An NFT (Non-Fungible Token) is a unique digital asset on a blockchain that represents ownership of a specific item — such as art, music, or a collectible — and cannot be replicated or exchanged 1:1 with another token.',
                    '<h2>What is an NFT?</h2><p>NFT stands for Non-Fungible Token. Unlike Bitcoin or Ether, which are fungible (every unit is identical), each NFT is unique and represents ownership of a specific digital or physical item.</p><h2>How NFTs Work</h2><p>NFTs are typically issued on Ethereum using the ERC-721 or ERC-1155 standard. Each token contains metadata pointing to the associated asset and a record of ownership on the blockchain.</p><h2>NFT Use Cases</h2><ul><li>Digital art and collectibles</li><li>Gaming items and virtual land</li><li>Event tickets and membership passes</li><li>Music rights and royalties</li><li>Real-world asset tokenization</li></ul><h2>Are NFTs Still Relevant?</h2><p>After the 2021-2022 boom, NFT trading volumes declined sharply. However, NFT infrastructure remains foundational for tokenizing real-world assets and Web3 identity.</p>',
                    'N',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'Smart Contract',
                    'smart-contract',
                    'A smart contract is a self-executing program stored on a blockchain that automatically enforces the terms of an agreement when predefined conditions are met, without requiring intermediaries.',
                    '<h2>What is a Smart Contract?</h2><p>A smart contract is a program that runs on a blockchain and automatically executes actions when specific conditions are met. The term was coined by computer scientist Nick Szabo in 1994, but became widely used with Ethereum''s launch in 2015.</p><h2>How Smart Contracts Work</h2><p>Smart contracts are written in languages like Solidity (Ethereum) or Rust (Solana). Once deployed, the code is immutable and executes deterministically — every node in the network runs the same code and reaches the same result.</p><h2>Real-World Examples</h2><ul><li><strong>DeFi:</strong> Automated lending and trading without banks.</li><li><strong>NFTs:</strong> Ownership transfer and royalty distribution.</li><li><strong>DAOs:</strong> On-chain governance voting.</li><li><strong>Insurance:</strong> Automatic payouts when oracle-verified events occur.</li></ul><h2>Limitations</h2><p>Smart contracts are only as good as the code they contain. Bugs can lead to hacks (e.g., the 2016 DAO hack). Audits by security firms are essential before deploying high-value contracts.</p>',
                    'S',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'Staking',
                    'staking',
                    'Staking is the process of locking up cryptocurrency in a proof-of-stake network to help validate transactions and secure the blockchain, earning rewards in return.',
                    '<h2>What is Crypto Staking?</h2><p>Staking is a way to earn passive income with your cryptocurrency by participating in a proof-of-stake (PoS) blockchain''s validation process. Instead of mining with hardware, you lock ("stake") your tokens as collateral to validate transactions.</p><h2>How Staking Works</h2><p>Validators are chosen to create new blocks based on the amount staked and other factors (e.g., randomness). In return for their service and risk, validators earn staking rewards — typically paid in the native token.</p><h2>Types of Staking</h2><ul><li><strong>Solo staking:</strong> Run your own validator node (e.g., 32 ETH minimum for Ethereum).</li><li><strong>Delegated staking:</strong> Delegate to a validator through a wallet or exchange.</li><li><strong>Liquid staking:</strong> Stake and receive a liquid token (e.g., stETH from Lido) you can use in DeFi.</li></ul><h2>Staking Rewards</h2><p>Annual yields vary by network: Ethereum ~4%, Cosmos ~14%, Solana ~6-8%. Rewards are paid in the staked token, so real returns also depend on price performance.</p>',
                    'S',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'HODL',
                    'hodl',
                    'HODL is crypto slang for holding cryptocurrency long-term rather than selling during price volatility — originating from a 2013 Bitcoin forum typo of "hold."',
                    '<h2>What Does HODL Mean?</h2><p>HODL originated as a typo in a 2013 BitcoinTalk forum post where a user wrote "I AM HODLING" during a Bitcoin price crash. The term stuck and became a philosophy: hold through volatility rather than panic selling.</p><h2>HODL as an Investment Strategy</h2><p>HODLing means buying and holding cryptocurrency long-term, ignoring short-term price fluctuations. This strategy is based on the belief that crypto assets will appreciate significantly over years despite short-term volatility.</p><h2>HODL vs Trading</h2><p>Studies consistently show that the majority of active traders underperform simple buy-and-hold strategies. The HODLing approach eliminates emotional decision-making and reduces transaction fees.</p><h2>When HODLing Makes Sense</h2><p>HODLing works best for assets with strong fundamentals (Bitcoin, Ethereum) and long investment horizons (3-5+ years). It''s less appropriate for speculative altcoins with unclear long-term value propositions.</p>',
                    'H',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'Gas Fee',
                    'gas-fee',
                    'Gas fees are payments made to compensate Ethereum network validators for the computational energy required to process and validate transactions on the blockchain.',
                    '<h2>What Are Gas Fees?</h2><p>Gas fees are transaction costs on the Ethereum network, measured in "gwei" (a fraction of ETH). They compensate validators for the computational resources needed to execute transactions and smart contracts.</p><h2>How Gas Fees Work</h2><p>Each operation on Ethereum requires a certain amount of gas. The total fee = Gas Units × Gas Price (in gwei). After EIP-1559, fees have two components: a base fee (burned) and a priority tip (to validators).</p><h2>Why Are Gas Fees High?</h2><p>Ethereum is a shared resource. During high demand (NFT drops, DeFi surges), users bid up gas prices to have their transactions processed faster. This can push fees to $50-$200+ per transaction.</p><h2>How to Reduce Gas Fees</h2><ul><li>Use Layer 2 networks (Arbitrum, Optimism, Base)</li><li>Transact during off-peak hours (weekends, late nights UTC)</li><li>Use gas trackers to find optimal times</li><li>Batch transactions where possible</li></ul>',
                    'G',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'Layer 2',
                    'layer-2',
                    'Layer 2 (L2) refers to scaling solutions built on top of a base blockchain (Layer 1) that process transactions off-chain to increase speed and reduce fees while inheriting the security of the main chain.',
                    '<h2>What is Layer 2?</h2><p>A Layer 2 is a secondary framework or protocol built on top of an existing blockchain (the "Layer 1" like Ethereum or Bitcoin) designed to increase transaction throughput and reduce costs while leveraging the security of the base chain.</p><h2>Types of Layer 2 Solutions</h2><ul><li><strong>Optimistic Rollups:</strong> Assume transactions are valid by default, with a fraud-proof window (Arbitrum, Optimism, Base).</li><li><strong>ZK-Rollups:</strong> Use zero-knowledge proofs to verify transactions cryptographically (zkSync, Polygon zkEVM, Starknet).</li><li><strong>State Channels:</strong> Off-chain channels for specific parties (Lightning Network for Bitcoin).</li></ul><h2>Layer 2 vs Layer 1</h2><p>L1 (Ethereum) prioritizes security and decentralization but is expensive. L2s sacrifice some decentralization for speed and cost, but settle back to L1 for finality. Typical L2 fees: $0.01-$0.10 vs $5-$50 on Ethereum mainnet.</p><h2>Major Layer 2 Networks</h2><p>Arbitrum, Optimism, Base (Coinbase), zkSync Era, Polygon, and Starknet are the leading Ethereum L2s, collectively holding billions in TVL.</p>',
                    'L',
                    'published',
                    NOW(),
                    NOW()
                ),
                (
                    @crypto_site_id,
                    'Proof of Work',
                    'proof-of-work',
                    'Proof of Work (PoW) is a consensus mechanism where miners compete to solve complex mathematical puzzles to validate transactions and add new blocks, earning crypto rewards for their computational effort.',
                    '<h2>What is Proof of Work?</h2><p>Proof of Work is the original blockchain consensus mechanism, used by Bitcoin. Miners expend computational energy to find a hash value below a target threshold, proving they performed "work." The first miner to succeed adds the next block and earns the block reward.</p><h2>How Mining Works</h2><p>Miners repeatedly hash block data with a changing nonce until the result meets the difficulty target. This requires significant hardware (ASICs for Bitcoin) and electricity. The difficulty adjusts every 2016 blocks (~2 weeks) to maintain a 10-minute block time.</p><h2>Security Model</h2><p>PoW is secured by economic incentive — attacking the network would require controlling 51% of hashrate, costing billions of dollars in hardware and energy. Attackers would earn less from attacking than from honest mining.</p><h2>Environmental Concerns</h2><p>Bitcoin''s annual energy consumption rivals some countries. This criticism drove the shift to Proof of Stake for Ethereum and most newer blockchains, though Bitcoin''s mining increasingly uses renewable energy.</p>',
                    'P',
                    'published',
                    NOW(),
                    NOW()
                )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE al FROM affiliate_links al
            JOIN sites s ON al.site_id = s.id WHERE s.domain = 'crypto.localhost'
        SQL);
        $this->addSql(<<<'SQL'
            DELETE ed FROM exchange_data ed
            JOIN sites s ON ed.site_id = s.id WHERE s.domain = 'crypto.localhost'
        SQL);
        $this->addSql(<<<'SQL'
            DELETE gt FROM glossary_terms gt
            JOIN sites s ON gt.site_id = s.id WHERE s.domain = 'crypto.localhost'
        SQL);
    }
}
