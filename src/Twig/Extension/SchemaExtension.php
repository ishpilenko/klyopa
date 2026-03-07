<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Article;
use App\Entity\Site;
use App\Entity\Tool;
use App\Enum\ArticleSchemaType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SchemaExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('schema_article', $this->schemaArticle(...), ['is_safe' => ['html']]),
            new TwigFunction('schema_website', $this->schemaWebsite(...), ['is_safe' => ['html']]),
            new TwigFunction('schema_breadcrumb', $this->schemaBreadcrumb(...), ['is_safe' => ['html']]),
            new TwigFunction('schema_tool', $this->schemaTool(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<array{label: string, url: string|null}> $items
     */
    public function schemaBreadcrumb(array $items, string $baseUrl = ''): string
    {
        $elements = [];
        foreach ($items as $position => $item) {
            $element = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['label'],
            ];
            if ($item['url'] !== null) {
                $element['item'] = rtrim($baseUrl, '/') . $item['url'];
            }
            $elements[] = $element;
        }

        return $this->toScript([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ]);
    }

    public function schemaWebsite(Site $site, string $baseUrl): string
    {
        return $this->toScript([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site->getName(),
            'url' => $baseUrl,
        ]);
    }

    public function schemaArticle(Article $article, Site $site, string $baseUrl): string
    {
        $canonicalUrl = $baseUrl;
        if ($category = $article->getCategory()) {
            $canonicalUrl = rtrim($baseUrl, '/') . '/' . $category->getSlug() . '/' . $article->getSlug();
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $article->getSchemaType()->value,
            'headline' => $article->getTitle(),
            'description' => $article->getMetaDescription() ?? $article->getExcerpt() ?? '',
            'datePublished' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            'dateModified' => ($article->getContentUpdatedAt() ?? $article->getPublishedAt())?->format(\DateTimeInterface::ATOM),
            'url' => $canonicalUrl,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonicalUrl,
            ],
            'author' => [
                '@type' => 'Organization',
                'name' => $article->getAuthorName() ?? $site->getName(),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $site->getName(),
            ],
        ];

        if ($article->getFeaturedImage()) {
            $schema['image'] = rtrim($baseUrl, '/') . '/uploads/' . $article->getSite()->getId() . '/' . $article->getFeaturedImage()->getFilename();
        }

        // HowTo and FAQPage need extra handling
        if ($article->getSchemaType() === ArticleSchemaType::HowTo) {
            $schema['@type'] = 'HowTo';
            $schema['name'] = $article->getTitle();
        } elseif ($article->getSchemaType() === ArticleSchemaType::FAQPage) {
            $schema['@type'] = 'FAQPage';
        }

        return $this->toScript($schema);
    }

    public function schemaTool(Tool $tool, Site $site, string $baseUrl): string
    {
        $url = rtrim($baseUrl, '/') . '/tools/' . $tool->getSlug();

        return $this->toScript([
            '@context' => 'https://schema.org',
            '@type' => $tool->getSchemaType() ?? 'SoftwareApplication',
            'name' => $tool->getName(),
            'description' => $tool->getDescription() ?? $tool->getMetaDescription() ?? '',
            'url' => $url,
            'applicationCategory' => 'UtilityApplication',
            'operatingSystem' => 'Web',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'USD',
            ],
            'author' => [
                '@type' => 'Organization',
                'name' => $site->getName(),
            ],
        ]);
    }

    private function toScript(array $data): string
    {
        return sprintf(
            '<script type="application/ld+json">%s</script>',
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
