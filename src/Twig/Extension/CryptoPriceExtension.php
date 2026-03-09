<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig filters for safe crypto price / amount formatting.
 *
 * The built-in pattern  |number_format(8)|trim('0')|trim('.')  is broken for
 * values between 0 and 1 (e.g. USDT ≈ 0.9998):
 *   "0.9998" |trim('0')  →  ".9998"  |trim('.')  →  "9998"   ← WRONG
 *
 * These filters use rtrim (right-side only) and choose decimal precision
 * automatically based on the magnitude of the value.
 */
class CryptoPriceExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // {{ price|crypto_price }}  →  e.g. "1.00", "0.9998", "0.00000800"
            new TwigFilter('crypto_price', $this->formatPrice(...), ['is_safe' => ['html']]),

            // {{ amount|crypto_amount }}  →  trailing zeros stripped, e.g. "0.00123456"
            new TwigFilter('crypto_amount', $this->formatAmount(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Format a cryptocurrency price in USD.
     * Auto-selects decimal precision by magnitude; never trims the leading "0".
     */
    public function formatPrice(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $f   = (float) $value;
        $abs = abs($f);

        return match (true) {
            $abs >= 1.0    => number_format($f, 2),
            $abs >= 0.01   => number_format($f, 4),
            $abs >= 0.0001 => number_format($f, 6),
            default        => number_format($f, 8),
        };
    }

    /**
     * Format a crypto quantity (e.g. "coins acquired").
     * Uses 8 decimal places and strips trailing zeros from the RIGHT only.
     */
    public function formatAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $f         = (float) $value;
        $abs       = abs($f);
        $precision = match (true) {
            $abs >= 1.0    => 8,
            $abs >= 0.0001 => 8,
            default        => 10,
        };

        $formatted = number_format($f, $precision);

        // rtrim only from the right — safe for "0.XXXX" values
        if (str_contains($formatted, '.')) {
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, '.');
        }

        return $formatted;
    }
}
