<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class ApiUser implements UserInterface
{
    public function __construct(
        private readonly string $token,
        private readonly int $siteId,
    ) {
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getRoles(): array
    {
        return ['ROLE_API'];
    }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return $this->token;
    }
}
