<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractApiController extends AbstractController
{
    protected function success(mixed $data, int $status = 200): JsonResponse
    {
        return $this->json(['data' => $data], $status);
    }

    protected function error(string $message, int $status = 400, array $details = []): JsonResponse
    {
        $body = ['error' => $message];
        if ($details) {
            $body['details'] = $details;
        }
        return $this->json($body, $status);
    }

    protected function notFound(string $message = 'Not found.'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /** Decode JSON request body, return null on parse error */
    protected function decodeBody(Request $request): ?array
    {
        if (empty($request->getContent())) {
            return [];
        }
        $data = json_decode($request->getContent(), true);
        return is_array($data) ? $data : null;
    }

    protected function paginated(array $data, int $total, int $page, int $limit): JsonResponse
    {
        return $this->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => max(1, (int) ceil($total / $limit)),
            ],
        ]);
    }
}
