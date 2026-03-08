<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\CategoryRepository;
use App\Repository\GlossaryTermRepository;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/glossary', name: 'glossary_', priority: 15)]
class GlossaryController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly GlossaryTermRepository $glossaryRepo,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $site         = $this->siteContext->getSite();
        $termsByLetter = $this->glossaryRepo->findAllGroupedByLetter();
        $total        = $this->glossaryRepo->countPublished();

        return $this->render('frontend/glossary/index.html.twig', [
            'site'           => $site,
            'categories'     => $this->categoryRepository->findActive(),
            'terms_by_letter' => $termsByLetter,
            'total'          => $total,
            'meta_title'     => 'Crypto Glossary — ' . $total . ' Terms Explained | ' . $site->getName(),
            'meta_description' => 'The most comprehensive crypto glossary. Learn the meaning of ' . $total . ' cryptocurrency terms and concepts, from Bitcoin basics to advanced DeFi.',
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'],
        requirements: ['slug' => '[a-z0-9-]+']
    )]
    public function show(string $slug): Response
    {
        $site = $this->siteContext->getSite();
        $term = $this->glossaryRepo->findBySlug($slug);

        if (null === $term) {
            throw new NotFoundHttpException('Glossary term not found.');
        }

        $relatedTerms = [];
        if (!empty($term->getRelatedTermSlugs())) {
            $relatedTerms = $this->glossaryRepo->findBySlugs($term->getRelatedTermSlugs());
        }

        $metaTitle = $term->getMetaTitle()
            ?: 'What is ' . $term->getTerm() . '? Definition & Explanation | ' . $site->getName();
        $metaDescription = $term->getMetaDescription()
            ?: $term->getShortDefinition() . ' Learn about ' . $term->getTerm() . ' in our crypto glossary.';

        return $this->render('frontend/glossary/show.html.twig', [
            'site'          => $site,
            'categories'    => $this->categoryRepository->findActive(),
            'term'          => $term,
            'related_terms' => $relatedTerms,
            'meta_title'    => $metaTitle,
            'meta_description' => $metaDescription,
        ]);
    }
}
