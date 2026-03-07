<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Site;

class SeoManager
{
    /** @return array{meta_title: string, meta_description: string|null} */
    public function forArticle(Article $article, Site $site): array
    {
        return [
            'meta_title' => $article->getMetaTitle()
                ?: $article->getTitle() . ' — ' . $site->getName(),
            'meta_description' => $article->getMetaDescription()
                ?: ($article->getExcerpt() ? strip_tags($article->getExcerpt()) : null),
        ];
    }

    /** @return array{meta_title: string, meta_description: string|null} */
    public function forCategory(Category $category, Site $site): array
    {
        return [
            'meta_title' => $category->getMetaTitle()
                ?: $category->getName() . ' — ' . $site->getName(),
            'meta_description' => $category->getMetaDescription() ?: $category->getDescription(),
        ];
    }

    /** @return array{meta_title: string, meta_description: string|null} */
    public function forSite(Site $site): array
    {
        return [
            'meta_title' => $site->getDefaultMetaTitle() ?: $site->getName(),
            'meta_description' => $site->getDefaultMetaDescription(),
        ];
    }

    public function canonicalUrl(string $schemeAndHost, string $path): string
    {
        return rtrim($schemeAndHost, '/') . '/' . ltrim($path, '/');
    }
}
