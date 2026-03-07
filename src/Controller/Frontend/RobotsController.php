<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RobotsController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
    ) {
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function index(): Response
    {
        $site = $this->siteContext->getSite();
        $baseUrl = 'https://' . $site->getDomain();

        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /api/',
            '',
            'Sitemap: ' . $baseUrl . '/sitemap.xml',
            '',
        ]);

        return new Response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
