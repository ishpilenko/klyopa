<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies Cloudflare Turnstile tokens server-side.
 *
 * When $enabled = false (CAPTCHA_ENABLED=0), verify() always returns true
 * so the rest of the app works normally without any keys configured.
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $secretKey,
        private readonly bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Verify a Turnstile token.
     * Returns true when captcha is disabled, secret key is empty, or token is valid.
     */
    public function verify(?string $token, string $ip): bool
    {
        if (!$this->enabled) {
            return true;
        }

        if (empty($this->secretKey)) {
            $this->logger->warning('TurnstileVerifier: CAPTCHA_ENABLED=1 but TURNSTILE_SECRET_KEY is empty.');
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body'    => ['secret' => $this->secretKey, 'response' => $token, 'remoteip' => $ip],
                'timeout' => 5,
            ]);

            $data = $response->toArray(false);

            if (!($data['success'] ?? false)) {
                $this->logger->info('Turnstile verification failed', [
                    'error-codes' => $data['error-codes'] ?? [],
                    'ip'          => $ip,
                ]);
            }

            return (bool) ($data['success'] ?? false);
        } catch (\Throwable $e) {
            // Network error — fail open to not block legit users
            $this->logger->error('Turnstile request failed: ' . $e->getMessage());
            return true;
        }
    }
}
