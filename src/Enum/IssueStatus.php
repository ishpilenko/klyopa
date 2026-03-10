<?php
declare(strict_types=1);
namespace App\Enum;

enum IssueStatus: string
{
    case Draft   = 'draft';
    case Ready   = 'ready';
    case Sending = 'sending';
    case Sent    = 'sent';
    case Failed  = 'failed';
}
