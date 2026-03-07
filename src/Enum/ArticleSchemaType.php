<?php

declare(strict_types=1);

namespace App\Enum;

enum ArticleSchemaType: string
{
    case Article = 'Article';
    case NewsArticle = 'NewsArticle';
    case BlogPosting = 'BlogPosting';
    case HowTo = 'HowTo';
    case FAQPage = 'FAQPage';
}
