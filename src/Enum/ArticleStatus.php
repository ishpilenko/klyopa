<?php

declare(strict_types=1);

namespace App\Enum;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::Review => 'In Review',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
