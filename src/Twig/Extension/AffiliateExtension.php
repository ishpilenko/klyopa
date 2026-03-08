<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Repository\AffiliateLinkRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides {{ affiliate_link('partner') }} and {{ affiliate_url('partner') }} in Twig templates.
 *
 * affiliate_link: renders full <a> tag with /go/{partner} tracking URL
 * affiliate_url:  returns raw /go/{partner} tracking URL (for embedding in content)
 */
class AffiliateExtension extends AbstractExtension
{
    public function __construct(
        private readonly AffiliateLinkRepository $repository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('affiliate_link', $this->affiliateLink(...), ['is_safe' => ['html']]),
            new TwigFunction('affiliate_url',  $this->affiliateUrl(...)),
        ];
    }

    /**
     * Renders an affiliate <a> tag pointing to /go/{partner} (tracking redirect).
     *
     * @param array{class?: string, rel?: string, text?: string} $options
     */
    public function affiliateLink(string $partner, array $options = []): string
    {
        $link = $this->repository->findByPartner($partner);
        if (null === $link) {
            return '';
        }

        $url   = '/go/' . htmlspecialchars($partner, ENT_QUOTES, 'UTF-8');
        $text  = htmlspecialchars($options['text'] ?? $link->getDisplayName(), ENT_QUOTES, 'UTF-8');
        $class = isset($options['class']) ? ' class="' . htmlspecialchars($options['class'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $rel   = $options['rel'] ?? 'nofollow noopener noreferrer sponsored';

        return sprintf(
            '<a href="%s" rel="%s" target="_blank"%s>%s</a>',
            $url,
            htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'),
            $class,
            $text,
        );
    }

    /**
     * Returns the /go/{partner} tracking URL string.
     */
    public function affiliateUrl(string $partner): string
    {
        $link = $this->repository->findByPartner($partner);
        if (null === $link) {
            return '';
        }

        return '/go/' . $partner;
    }
}
