<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\ApiTokenRepository;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
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

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiTokenRepository $apiTokenRepository,
        private readonly SiteContext $siteContext,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization', '');
        $tokenString = substr($authHeader, 7);

        if ('' === $tokenString) {
            throw new CustomUserMessageAuthenticationException('No API token provided.');
        }

        $apiToken = $this->apiTokenRepository->findActiveByToken($tokenString);

        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired API token.');
        }

        // Set site context from token (replaces Host-based resolution for API requests)
        $site = $apiToken->getSite();
        $this->siteContext->setSite($site);

        // Enable Doctrine SiteFilter for this request
        $filter = $this->em->getFilters()->enable('site_filter');
        $filter->setParameter('siteId', $site->getId());

        $user = new ApiUser($tokenString, $site->getId());

        return new SelfValidatingPassport(
            new UserBadge($tokenString, fn() => $user)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
