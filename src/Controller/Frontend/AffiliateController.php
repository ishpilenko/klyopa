<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\AffiliateLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class AffiliateController extends AbstractController
{
    public function __construct(
        private readonly AffiliateLinkRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Tracks a click then redirects to the partner's affiliate URL.
     * Uses 302 (temporary) to prevent caching and ensure tracking on each visit.
     */
    #[Route('/go/{partner}', name: 'affiliate_redirect', methods: ['GET'],
        requirements: ['partner' => '[a-z0-9-]+'],
        priority: 30
    )]
    public function go(string $partner): Response
    {
        $link = $this->repository->findByPartner($partner);

        if (null === $link) {
            throw new NotFoundHttpException('Affiliate partner not found.');
        }

        $link->incrementClicks();
        $this->em->flush();

        return new RedirectResponse($link->getFullUrl(), Response::HTTP_FOUND, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
