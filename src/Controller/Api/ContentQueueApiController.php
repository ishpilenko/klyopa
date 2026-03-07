<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ContentQueue;
use App\Enum\ContentQueueStatus;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\ContentQueueRepository;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/queue', name: 'api_queue_')]
class ContentQueueApiController extends AbstractApiController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ContentQueueRepository $queueRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** GET /api/v1/queue */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = ContentQueueStatus::tryFrom($request->query->get('status', 'pending'))
            ?? ContentQueueStatus::Pending;
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));

        $items = $this->queueRepository->findByStatus($status, $limit);

        return $this->success(array_map($this->serialize(...), $items));
    }

    /** POST /api/v1/queue */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeBody($request);
        if ($data === null) {
            return $this->error('Invalid JSON body.');
        }

        if (empty($data['topic'])) {
            return $this->error("Field 'topic' is required.");
        }

        $site = $this->siteContext->getSite();
        $item = new ContentQueue();
        $item->setSite($site);
        $item->setTopic($data['topic']);

        if (!empty($data['keywords']) && is_array($data['keywords'])) {
            $item->setKeywords($data['keywords']);
        }
        if (!empty($data['target_category_id'])) {
            $category = $this->categoryRepository->find((int) $data['target_category_id']);
            $item->setTargetCategory($category);
        }
        if (!empty($data['target_word_count'])) {
            $item->setTargetWordCount((int) $data['target_word_count']);
        }
        if (!empty($data['prompt_template'])) {
            $item->setPromptTemplate($data['prompt_template']);
        }
        if (isset($data['priority'])) {
            $item->setPriority((int) $data['priority']);
        }

        $this->em->persist($item);
        $this->em->flush();

        return $this->success($this->serialize($item), 201);
    }

    /** PATCH /api/v1/queue/{id} */
    #[Route('/{id}', name: 'update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->queueRepository->find($id);
        if (!$item) {
            return $this->notFound('Queue item not found.');
        }

        $data = $this->decodeBody($request);
        if ($data === null) {
            return $this->error('Invalid JSON body.');
        }

        if (isset($data['status'])) {
            $status = ContentQueueStatus::tryFrom($data['status']);
            if (!$status) {
                return $this->error("Invalid status value '{$data['status']}'.");
            }
            match ($status) {
                ContentQueueStatus::Processing => $item->markAsProcessing(),
                ContentQueueStatus::Completed  => $item->markAsCompleted(
                    isset($data['result_article_id'])
                        ? $this->articleRepository->find((int) $data['result_article_id'])
                        : null
                ),
                ContentQueueStatus::Failed => $item->markAsFailed($data['error_message'] ?? 'Unknown error'),
                default => null,
            };
        }

        $this->em->flush();

        return $this->success($this->serialize($item));
    }

    private function serialize(ContentQueue $item): array
    {
        return [
            'id' => $item->getId(),
            'site_id' => $item->getSite()->getId(),
            'topic' => $item->getTopic(),
            'keywords' => $item->getKeywords(),
            'target_category_id' => $item->getTargetCategory()?->getId(),
            'target_word_count' => $item->getTargetWordCount(),
            'prompt_template' => $item->getPromptTemplate(),
            'status' => $item->getStatus()->value,
            'priority' => $item->getPriority(),
            'result_article_id' => $item->getResultArticle()?->getId(),
            'error_message' => $item->getErrorMessage(),
            'created_at' => $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'processed_at' => $item->getProcessedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
