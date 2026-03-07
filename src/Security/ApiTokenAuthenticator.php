<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates API requests via Bearer token.
 * Token format: "Bearer <token>"
 * Tokens are stored in the api_tokens table (Phase 4).
 */
class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization', '');
        $token = substr($authHeader, 7); // Remove "Bearer "

        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('No API token provided.');
        }

        // TODO Phase 4: validate token against api_tokens table
        // For now, accept any non-empty token in dev
        return new SelfValidatingPassport(
            new UserBadge($token, fn() => new ApiUser($token))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Continue to controller
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
