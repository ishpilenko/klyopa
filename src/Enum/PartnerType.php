<?php

declare(strict_types=1);

namespace App\Enum;

enum PartnerType: string
{
    case Exchange = 'exchange';
    case Wallet   = 'wallet';
    case Service  = 'service';
    case Course   = 'course';
}
