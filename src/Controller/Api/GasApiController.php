<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\GasTrackerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Internal API for the Gas Fee Tracker tool.
 * Polled by JavaScript every 15 seconds for live gas data.
 */
class GasApiController extends AbstractController
{
    public function __construct(
        private readonly GasTrackerService $gasTracker,
    ) {}

    #[Route('/api/v1/gas-prices', name: 'api_gas_prices', methods: ['GET'])]
    public function gasPrices(Request $request): JsonResponse
    {
        $network = $request->query->get('network', 'ethereum');

        $response = $this->json($this->gasTracker->getGasPrices($network));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
