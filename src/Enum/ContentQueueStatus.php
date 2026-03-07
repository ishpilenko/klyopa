<?php

declare(strict_types=1);

namespace App\Enum;

enum ContentQueueStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
