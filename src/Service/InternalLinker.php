<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Repository\ArticleRepository;

class InternalLinker
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
    ) {
    }

    /**
     * Suggest internal links for the given HTML content.
     *
     * @return array<array{anchor: string, url: string, article_id: int}>
     */
    public function suggest(string $htmlContent, int $excludeArticleId, int $maxLinks = 5): array
    {
        $plainText = strip_tags($htmlContent);
        $suggestions = [];

        $candidates = $this->articleRepository->findPublished(100);

        foreach ($candidates as $article) {
            if ($article->getId() === $excludeArticleId) {
                continue;
            }

            $title = $article->getTitle();
            if (mb_strlen($title) < 5) {
                continue;
            }

            if (stripos($plainText, $title) !== false) {
                $url = $article->getCategory()
                    ? '/' . $article->getCategory()->getSlug() . '/' . $article->getSlug()
                    : '/' . $article->getSlug();

                $suggestions[] = [
                    'anchor' => $title,
                    'url' => $url,
                    'article_id' => $article->getId(),
                ];

                if (count($suggestions) >= $maxLinks) {
                    break;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Apply suggested links to HTML content.
     * Replaces first occurrence of each anchor text with an <a> tag.
     * Does not modify text already inside <a> tags.
     *
     * @param array<array{anchor: string, url: string, article_id: int}> $suggestions
     */
    public function apply(string $htmlContent, array $suggestions): string
    {
        foreach ($suggestions as $suggestion) {
            $anchor = htmlspecialchars($suggestion['anchor'], ENT_QUOTES);
            $url = htmlspecialchars($suggestion['url'], ENT_QUOTES);
            $link = '<a href="' . $url . '">' . $anchor . '</a>';

            // Replace first occurrence outside existing <a> tags
            $htmlContent = $this->replaceFirstOutsideLinks($htmlContent, $suggestion['anchor'], $link);
        }

        return $htmlContent;
    }

    private function replaceFirstOutsideLinks(string $html, string $search, string $replacement): string
    {
        // Split on <a...>...</a> blocks, only replace in non-link segments
        $parts = preg_split('/(<a\s[^>]*>.*?<\/a>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        $replaced = false;
        foreach ($parts as $i => $part) {
            if ($replaced) {
                break;
            }
            // Skip <a> blocks (odd indices after split with PREG_SPLIT_DELIM_CAPTURE)
            if ($i % 2 === 1) {
                continue;
            }
            $count = 0;
            $parts[$i] = str_ireplace($search, $replacement, $part, $count);
            if ($count > 0) {
                $replaced = true;
            }
        }

        return implode('', $parts);
    }
}
