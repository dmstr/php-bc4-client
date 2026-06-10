<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Authentication;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Helpers for the one-shot interactive OAuth flow.
 *
 *   1. Build the authorize URL the operator opens in a browser
 *   2. Exchange the resulting `code` for an initial token bundle
 *   3. Discover the BC account_id when the OAuth user belongs to exactly one
 *
 * This class is intentionally HTTP-server-free — capturing the redirect (loopback
 * server, manual paste, custom UI) is the calling application's job. We just
 * provide the URL builders and the API calls.
 */
class AuthorizationFlow
{
    public const AUTHORIZE_URL = 'https://launchpad.37signals.com/authorization/new';
    public const TOKEN_URL = 'https://launchpad.37signals.com/authorization/token';
    public const ACCOUNTS_URL = 'https://launchpad.37signals.com/authorization.json';

    private ?HttpClientInterface $http;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $appName,
        private readonly string $appContact,
        ?HttpClientInterface $http = null,
    ) {
        $this->http = $http;
    }

    public function buildAuthorizeUrl(string $redirectUri, ?string $state = null): string
    {
        $params = [
            'type' => 'web_server',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
        ];
        if ($state !== null && $state !== '') {
            $params['state'] = $state;
        }

        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange an authorization code for the initial token bundle.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $response = $this->getHttp()->request('POST', self::TOKEN_URL, [
            'body' => [
                'type' => 'web_server',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ],
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => $this->buildUserAgent(),
            ],
        ]);

        $status = $response->getStatusCode();
        $raw = $response->getContent(false);
        $body = json_decode($raw, true);

        if ($status >= 400 || !is_array($body) || !isset($body['access_token'], $body['refresh_token'], $body['expires_in'])) {
            $detail = is_array($body) ? json_encode($body) : $raw;
            throw new \RuntimeException(sprintf('Launchpad token exchange returned status=%d body=%s', $status, $detail));
        }

        return [
            'access_token' => (string) $body['access_token'],
            'refresh_token' => (string) $body['refresh_token'],
            'expires_in' => (int) $body['expires_in'],
        ];
    }

    /**
     * Resolve the BC account id when the authorized user has access to exactly
     * one BC3/BC4 account. Returns null otherwise (caller decides what to do).
     */
    public function discoverSingleAccountId(string $accessToken): ?string
    {
        $response = $this->getHttp()->request('GET', self::ACCOUNTS_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'User-Agent' => $this->buildUserAgent(),
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $body = json_decode($response->getContent(false), true);
        if (!is_array($body)) {
            return null;
        }

        $bcAccounts = array_values(array_filter(
            $body['accounts'] ?? [],
            static fn ($a) => is_array($a) && in_array($a['product'] ?? null, ['bc3', 'bc4'], true),
        ));

        if (count($bcAccounts) !== 1) {
            return null;
        }

        $id = $bcAccounts[0]['id'] ?? null;

        return $id !== null ? (string) $id : null;
    }

    private function buildUserAgent(): string
    {
        return sprintf('%s (%s)', $this->appName, $this->appContact);
    }

    private function getHttp(): HttpClientInterface
    {
        if ($this->http === null) {
            $this->http = HttpClient::create(['timeout' => 30]);
        }

        return $this->http;
    }
}
