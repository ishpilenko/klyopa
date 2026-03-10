<?php
declare(strict_types=1);
namespace App\Enum;

enum SubscriberStatus: string
{
    case Pending      = 'pending';
    case Active       = 'active';
    case Unsubscribed = 'unsubscribed';
    case Bounced      = 'bounced';
}
