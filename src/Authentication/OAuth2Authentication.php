<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Authentication;

use Dmstr\Bc4Client\Exception\InvalidGrantException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OAuth 2.0 web-server-flow authentication for Basecamp 4 / BC3 API.
 *
 * Manages the access/refresh token lifecycle:
 *   - Proactive refresh when `expires_at < now + 60s`
 *   - Reactive refresh on `401` (handled by {@see Bc4Client::request()} via
 *     {@see refresh()})
 *   - Refresh-token rotation when Launchpad sends one: a new refresh_token
 *     replaces the previous one in storage. The legacy `type=refresh`
 *     response usually carries only `access_token` (see the "legacy format
 *     refresh" case in basecamp/basecamp-sdk); the current refresh_token
 *     then stays valid and is kept
 *   - On `400 invalid_grant`: storage is marked `requires_reauth` and an
 *     {@see InvalidGrantException} is thrown
 *
 * Concurrency control (e.g. pessimistic database locks) lives in the
 * {@see TokenStorageInterface} implementation, not here.
 */
class OAuth2Authentication implements AuthenticationInterface
{
    private const LAUNCHPAD_TOKEN_URL = 'https://launchpad.37signals.com/authorization/token';

    /**
     * Access-token lifetime documented by 37signals ("2 week lifetime,
     * currently"), used when a refresh response omits `expires_in`.
     */
    private const DEFAULT_EXPIRES_IN = 1209600;

    private ?string $cachedAccessToken = null;
    private ?string $cachedExpiresAt = null;
    private ?HttpClientInterface $launchpadClient = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $appName,
        private readonly string $appContact,
        private readonly TokenStorageInterface $storage,
        ?HttpClientInterface $launchpadClient = null,
    ) {
        $this->launchpadClient = $launchpadClient;
    }

    public function decorate(HttpClientInterface $client): HttpClientInterface
    {
        $this->ensureUsableTokenState();

        return $client->withOptions([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->cachedAccessToken,
                'User-Agent' => $this->buildUserAgent(),
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function refresh(): void
    {
        try {
            $refreshed = $this->storage->refreshAndPersist(
                fn (string $refreshToken): array => $this->exchangeRefreshToken($refreshToken),
            );
        } catch (InvalidGrantException $e) {
            $this->storage->markRequiresReauth();
            throw $e;
        }

        $this->cachedAccessToken = $refreshed['access_token'];
        $this->cachedExpiresAt = $refreshed['expires_at'];
    }

    public function requiresReauth(): bool
    {
        return $this->storage->loadTokens()['requires_reauth'];
    }

    /**
     * Currently held access_token, refreshing first if needed.
     */
    public function getAccessToken(): string
    {
        $this->ensureUsableTokenState();

        return (string) $this->cachedAccessToken;
    }

    public function buildUserAgent(): string
    {
        return sprintf('%s (%s)', $this->appName, $this->appContact);
    }

    private function ensureUsableTokenState(): void
    {
        if ($this->cachedAccessToken !== null && $this->cachedExpiresAt !== null && $this->storage->isFresh($this->cachedExpiresAt)) {
            return;
        }

        $tokens = $this->storage->loadTokens();
        if ($tokens['requires_reauth']) {
            throw new InvalidGrantException('OAuth credentials require fresh user authorization');
        }

        if ($tokens['access_token'] !== null && $tokens['expires_at'] !== null && $this->storage->isFresh($tokens['expires_at'])) {
            $this->cachedAccessToken = $tokens['access_token'];
            $this->cachedExpiresAt = $tokens['expires_at'];

            return;
        }

        $this->refresh();
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     * @throws InvalidGrantException
     */
    private function exchangeRefreshToken(string $refreshToken): array
    {
        $response = $this->getLaunchpadClient()->request('POST', self::LAUNCHPAD_TOKEN_URL, [
            'body' => [
                'type' => 'refresh',
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
            'headers' => [
                'User-Agent' => $this->buildUserAgent(),
                'Accept' => 'application/json',
            ],
        ]);

        $status = $response->getStatusCode();
        $body = json_decode($response->getContent(false), true);

        if ($status === 400 && is_array($body) && ($body['error'] ?? null) === 'invalid_grant') {
            throw new InvalidGrantException('Refresh token rejected by Launchpad (invalid_grant)');
        }

        if ($status >= 400 || !is_array($body) || empty($body['access_token'])) {
            throw new \RuntimeException(sprintf('BC4 token exchange failed (status=%d)', $status));
        }

        return [
            'access_token' => (string) $body['access_token'],
            // no rotation: the current refresh_token stays valid
            'refresh_token' => !empty($body['refresh_token']) ? (string) $body['refresh_token'] : $refreshToken,
            'expires_in' => isset($body['expires_in']) ? (int) $body['expires_in'] : self::DEFAULT_EXPIRES_IN,
        ];
    }

    private function getLaunchpadClient(): HttpClientInterface
    {
        if ($this->launchpadClient === null) {
            $this->launchpadClient = HttpClient::create(['timeout' => 30]);
        }

        return $this->launchpadClient;
    }
}
