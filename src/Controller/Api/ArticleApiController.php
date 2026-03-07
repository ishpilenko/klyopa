<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Tag;
use App\Enum\ArticleSchemaType;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use App\Repository\TagRepository;
use App\Service\SiteContext;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/articles', name: 'api_article_')]
class ArticleApiController extends AbstractApiController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly SlugGenerator $slugGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** GET /api/v1/articles */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $categoryId = $request->query->getInt('category_id') ?: null;
        $isAiGenerated = $request->query->has('is_ai_generated')
            ? filter_var($request->query->get('is_ai_generated'), FILTER_VALIDATE_BOOLEAN)
            : null;
        $olderThanDays = $request->query->getInt('older_than') ?: null;
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * $limit;

        $statusEnum = $status ? ArticleStatus::tryFrom($status) : null;
        $category = $categoryId ? $this->categoryRepository->find($categoryId) : null;

        [$articles, $total] = $this->articleRepository->findForApi(
            status: $statusEnum,
            category: $category,
            isAiGenerated: $isAiGenerated,
            olderThanDays: $olderThanDays,
            limit: $limit,
            offset: $offset,
        );

        return $this->paginated(
            array_map($this->serialize(...), $articles),
            $total,
            $page,
            $limit,
        );
    }

    /** POST /api/v1/articles */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeBody($request);
        if ($data === null) {
            return $this->error('Invalid JSON body.');
        }

        $required = ['title', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->error("Field '{$field}' is required.");
            }
        }

        $site = $this->siteContext->getSite();
        $article = new Article();
        $article->setSite($site);

        $this->applyData($article, $data, isNew: true);

        $this->em->persist($article);
        $this->em->flush();

        return $this->success($this->serialize($article), 201);
    }

    /** GET /api/v1/articles/{id} */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);
        if (!$article) {
            return $this->notFound('Article not found.');
        }
        return $this->success($this->serialize($article));
    }

    /** PUT /api/v1/articles/{id} */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $article = $this->articleRepository->find($id);
        if (!$article) {
            return $this->notFound('Article not found.');
        }

        $data = $this->decodeBody($request);
        if ($data === null) {
            return $this->error('Invalid JSON body.');
        }

        $this->applyData($article, $data);
        $this->em->flush();

        return $this->success($this->serialize($article));
    }

    /** PATCH /api/v1/articles/{id}/publish */
    #[Route('/{id}/publish', name: 'publish', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function publish(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);
        if (!$article) {
            return $this->notFound('Article not found.');
        }

        if (!$article->isPublished()) {
            $article->publish();
            $this->em->flush();
        }

        return $this->success($this->serialize($article));
    }

    private function applyData(Article $article, array $data, bool $isNew = false): void
    {
        if (isset($data['title'])) {
            $article->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $article->setContent($data['content']);
            $article->setWordCount($this->countWords($data['content']));
            $article->setReadingTimeMinutes(max(1, (int) ceil($article->getWordCount() / 200)));
            $article->setContentUpdatedAt(new \DateTimeImmutable());
        }

        if (isset($data['slug'])) {
            $article->setSlug($data['slug']);
        } elseif ($isNew && isset($data['title'])) {
            $siteId = $article->getSite()->getId();
            $article->setSlug($this->slugGenerator->generateUnique(
                $data['title'],
                fn(string $s) => (bool) $this->articleRepository->findBySlugUnfiltered($s, $siteId),
            ));
        }

        if (array_key_exists('excerpt', $data)) {
            $article->setExcerpt($data['excerpt'] ?: null);
        }
        if (array_key_exists('meta_title', $data)) {
            $article->setMetaTitle($data['meta_title'] ?: null);
        }
        if (array_key_exists('meta_description', $data)) {
            $article->setMetaDescription($data['meta_description'] ?: null);
        }
        if (array_key_exists('author_name', $data)) {
            $article->setAuthorName($data['author_name'] ?: null);
        }
        if (array_key_exists('source_url', $data)) {
            $article->setSourceUrl($data['source_url'] ?: null);
        }
        if (isset($data['is_ai_generated'])) {
            $article->setIsAiGenerated((bool) $data['is_ai_generated']);
        }
        if (isset($data['is_evergreen'])) {
            $article->setIsEvergreen((bool) $data['is_evergreen']);
        }

        if (isset($data['status'])) {
            $status = ArticleStatus::tryFrom($data['status']);
            if ($status) {
                $article->setStatus($status);
                if ($status === ArticleStatus::Published && !$article->getPublishedAt()) {
                    $article->setPublishedAt(new \DateTimeImmutable());
                }
            }
        }

        if (isset($data['schema_type'])) {
            $schemaType = ArticleSchemaType::tryFrom($data['schema_type']);
            if ($schemaType) {
                $article->setSchemaType($schemaType);
            }
        }

        if (isset($data['category_id'])) {
            $category = $this->categoryRepository->find((int) $data['category_id']);
            $article->setCategory($category);
        }

        if (isset($data['featured_image_id'])) {
            $media = $this->mediaRepository->find((int) $data['featured_image_id']);
            $article->setFeaturedImage($media);
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $article->getTags()->clear();
            foreach ($data['tags'] as $tagName) {
                $slug = mb_strtolower(trim(str_replace(' ', '-', $tagName)));
                $tag = $this->tagRepository->findBySlug($slug)
                    ?? $this->createTag($tagName, $slug, $article->getSite());
                $article->getTags()->add($tag);
            }
        }
    }

    private function createTag(string $name, string $slug, \App\Entity\Site $site): Tag
    {
        $tag = new Tag();
        $tag->setName($name)->setSlug($slug)->setSite($site);
        $this->em->persist($tag);
        return $tag;
    }

    private function serialize(Article $article): array
    {
        return [
            'id' => $article->getId(),
            'site_id' => $article->getSite()->getId(),
            'category_id' => $article->getCategory()?->getId(),
            'title' => $article->getTitle(),
            'slug' => $article->getSlug(),
            'excerpt' => $article->getExcerpt(),
            'content' => $article->getContent(),
            'status' => $article->getStatus()->value,
            'schema_type' => $article->getSchemaType()->value,
            'meta_title' => $article->getMetaTitle(),
            'meta_description' => $article->getMetaDescription(),
            'author_name' => $article->getAuthorName(),
            'source_url' => $article->getSourceUrl(),
            'is_ai_generated' => $article->isAiGenerated(),
            'is_evergreen' => $article->isEvergreen(),
            'word_count' => $article->getWordCount(),
            'reading_time_minutes' => $article->getReadingTimeMinutes(),
            'featured_image_id' => $article->getFeaturedImage()?->getId(),
            'tags' => array_map(
                fn($t) => ['id' => $t->getId(), 'name' => $t->getName(), 'slug' => $t->getSlug()],
                $article->getTags()->toArray(),
            ),
            'published_at' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            'content_updated_at' => $article->getContentUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $article->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $article->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function countWords(string $html): int
    {
        return str_word_count(strip_tags($html));
    }
}
