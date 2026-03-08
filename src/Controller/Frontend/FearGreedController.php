<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\CategoryRepository;
use App\Service\FearGreedClient;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FearGreedController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly FearGreedClient $fearGreedClient,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/fear-greed-index', name: 'fear_greed_index', methods: ['GET'], priority: 15)]
    public function index(): Response
    {
        $site    = $this->siteContext->getSite();
        $history = $this->fearGreedClient->getIndex(30);
        $current = $history[0] ?? ['value' => 50, 'classification' => 'Neutral', 'date' => new \DateTimeImmutable()];

        $value          = $current['value'];
        $classification = $current['classification'];

        // Build summary rows for the table (today, yesterday, 1w, 1m)
        $summary = [
            'Now'        => $current,
            'Yesterday'  => $history[1]  ?? null,
            'Last Week'  => $history[6]  ?? null,
            'Last Month' => $history[29] ?? null,
        ];

        return $this->render('frontend/tools/fear_greed.html.twig', [
            'site'            => $site,
            'categories'      => $this->categoryRepository->findActive(),
            'current'         => $current,
            'history'         => $history,
            'summary'         => $summary,
            'zone_color'      => FearGreedClient::getZoneColor($value),
            'meta_title'      => sprintf(
                'Crypto Fear & Greed Index: %d (%s) — %s | %s',
                $value,
                $classification,
                (new \DateTimeImmutable())->format('M j, Y'),
                $site->getName()
            ),
            'meta_description' => sprintf(
                'Today\'s Crypto Fear & Greed Index is %d (%s). Track 30 days of market sentiment data — updated daily.',
                $value,
                $classification
            ),
        ]);
    }
}
