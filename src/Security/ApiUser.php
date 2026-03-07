<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Minimal user object for API token authentication.
 * Will be replaced with a proper User entity in Phase 4.
 */
class ApiUser implements UserInterface
{
    public function __construct(private readonly string $token)
    {
    }

    public function getRoles(): array
    {
        return ['ROLE_API'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->token;
    }
}
