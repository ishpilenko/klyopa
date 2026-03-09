<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\GlossaryTermRepository;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/search', name: 'api_search', methods: ['GET'])]
class SearchApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteContext $siteContext,
        private readonly GlossaryTermRepository $glossaryRepo,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $q     = trim((string) $request->query->get('q', ''));
        $limit = min($request->query->getInt('limit', 8), 20);

        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $results = [];

        // Search articles
        if ($this->siteContext->hasSite()) {
            $site = $this->siteContext->getSite();

            $articles = $this->em->getConnection()->fetchAllAssociative(
                'SELECT a.id, a.title, a.slug, c.slug AS cat_slug
                 FROM articles a
                 LEFT JOIN categories c ON c.id = a.category_id
                 WHERE a.site_id = :siteId
                   AND a.status = \'published\'
                   AND a.title LIKE :q
                 ORDER BY a.published_at DESC
                 LIMIT :limit',
                ['siteId' => $site->getId(), 'q' => '%' . $q . '%', 'limit' => $limit],
                ['limit' => \PDO::PARAM_INT],
            );

            foreach ($articles as $row) {
                $results[] = [
                    'type'  => 'article',
                    'title' => $row['title'],
                    'url'   => '/' . ($row['cat_slug'] ? $row['cat_slug'] . '/' : '') . $row['slug'],
                ];
            }

            // Search glossary terms
            $terms = $this->em->getConnection()->fetchAllAssociative(
                'SELECT term, slug FROM glossary_terms
                 WHERE site_id = :siteId AND term LIKE :q
                 LIMIT :limit',
                ['siteId' => $site->getId(), 'q' => '%' . $q . '%', 'limit' => 4],
                ['limit' => \PDO::PARAM_INT],
            );

            foreach ($terms as $row) {
                $results[] = [
                    'type'  => 'term',
                    'title' => $row['term'],
                    'url'   => '/glossary/' . $row['slug'],
                ];
            }
        }

        // Cap total results
        return $this->json(array_slice($results, 0, $limit));
    }
}
