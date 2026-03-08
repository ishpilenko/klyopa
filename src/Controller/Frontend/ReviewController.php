<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\ExchangeDataRepository;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reviews', name: 'reviews_', priority: 15)]
class ReviewController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ExchangeDataRepository $exchangeRepo,
    ) {
    }

    /** /reviews/exchanges — listing of exchange reviews */
    #[Route('/exchanges', name: 'exchanges', methods: ['GET'])]
    public function exchanges(): Response
    {
        $site      = $this->siteContext->getSite();
        $exchanges = $this->exchangeRepo->findAll();

        return $this->render('frontend/review/index.html.twig', [
            'site'            => $site,
            'categories'      => $this->categoryRepository->findActive(),
            'exchanges'       => $exchanges,
            'type'            => 'exchanges',
            'meta_title'      => 'Best Crypto Exchanges Reviewed & Ranked ' . date('Y') . ' | ' . $site->getName(),
            'meta_description' => 'Unbiased reviews of the top cryptocurrency exchanges. Compare fees, security, supported coins and more to find the best exchange for you.',
        ]);
    }

    /** /reviews/wallets — placeholder listing */
    #[Route('/wallets', name: 'wallets', methods: ['GET'])]
    public function wallets(): Response
    {
        $site = $this->siteContext->getSite();

        return $this->render('frontend/review/index.html.twig', [
            'site'            => $site,
            'categories'      => $this->categoryRepository->findActive(),
            'exchanges'       => [],
            'type'            => 'wallets',
            'meta_title'      => 'Best Crypto Wallets Reviewed ' . date('Y') . ' | ' . $site->getName(),
            'meta_description' => 'Expert reviews of hardware and software crypto wallets. Find the safest option to store your Bitcoin, Ethereum and altcoins.',
        ]);
    }

    /** /reviews/{slug} — individual exchange review article */
    #[Route('/{slug}', name: 'show', methods: ['GET'],
        requirements: ['slug' => '[a-z0-9-]+']
    )]
    public function show(string $slug): Response
    {
        $site         = $this->siteContext->getSite();
        $exchangeData = $this->exchangeRepo->findBySlug(rtrim($slug, '-review'));

        // Fallback: try slug as-is
        if (null === $exchangeData) {
            $exchangeData = $this->exchangeRepo->findBySlug($slug);
        }

        // Try to find the review article
        $article = $this->articleRepository->findBySlug($slug);
        if (null === $article && null === $exchangeData) {
            throw new NotFoundHttpException('Review not found.');
        }

        // If we only have article, render it normally
        if (null === $exchangeData && null !== $article) {
            return $this->render('frontend/review/show.html.twig', [
                'site'          => $site,
                'categories'    => $this->categoryRepository->findActive(),
                'article'       => $article,
                'exchange_data' => null,
                'meta_title'    => $article->getMetaTitle() ?: $article->getTitle() . ' | ' . $site->getName(),
                'meta_description' => $article->getMetaDescription() ?: $article->getExcerpt(),
            ]);
        }

        // Load associated article if available
        if (null === $article && null !== $exchangeData?->getReviewArticle()) {
            $article = $exchangeData->getReviewArticle();
        }

        if (null === $article) {
            throw new NotFoundHttpException('Review article not found.');
        }

        $relatedExchanges = $this->exchangeRepo->findTopByRating(5);

        return $this->render('frontend/review/show.html.twig', [
            'site'              => $site,
            'categories'        => $this->categoryRepository->findActive(),
            'article'           => $article,
            'exchange_data'     => $exchangeData,
            'related_exchanges' => $relatedExchanges,
            'meta_title'        => $article->getMetaTitle() ?: $article->getTitle() . ' | ' . $site->getName(),
            'meta_description'  => $article->getMetaDescription() ?: $article->getExcerpt(),
        ]);
    }
}
