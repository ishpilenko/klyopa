<?php
declare(strict_types=1);
namespace App\Message;

final readonly class SendNewsletterMessage
{
    public function __construct(
        public int $issueId,
        public int $subscriberId,
    ) {
    }
}
