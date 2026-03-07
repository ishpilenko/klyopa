<?php

declare(strict_types=1);

namespace App\Enum;

enum SiteVertical: string
{
    case Crypto = 'crypto';
    case Finance = 'finance';
    case Gambling = 'gambling';
    case General = 'general';

    public function label(): string
    {
        return match($this) {
            self::Crypto => 'Cryptocurrency',
            self::Finance => 'Finance',
            self::Gambling => 'Gambling',
            self::General => 'General',
        };
    }
}
