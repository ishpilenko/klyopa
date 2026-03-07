<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tool;

class BreadcrumbService
{
    /** @return array<array{label: string, url: string|null}> */
    public function forHome(): array
    {
        return [
            ['label' => 'Home', 'url' => null],
        ];
    }

    /** @return array<array{label: string, url: string|null}> */
    public function forCategory(Category $category): array
    {
        return [
            ['label' => 'Home', 'url' => '/'],
            ['label' => $category->getName(), 'url' => null],
        ];
    }

    /** @return array<array{label: string, url: string|null}> */
    public function forTool(Tool $tool): array
    {
        return [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Tools', 'url' => '/tools/'],
            ['label' => $tool->getName(), 'url' => null],
        ];
    }

    /** @return array<array{label: string, url: string|null}> */
    public function forArticle(Article $article): array
    {
        $items = [['label' => 'Home', 'url' => '/']];

        if ($category = $article->getCategory()) {
            $items[] = ['label' => $category->getName(), 'url' => '/' . $category->getSlug() . '/'];
        }

        $items[] = ['label' => $article->getTitle(), 'url' => null];

        return $items;
    }
}
