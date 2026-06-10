<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Authentication;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Decorates an HTTP client with the credentials needed to talk to BC4.
 *
 * Implementations either inject static headers (Basic auth, Personal Access Tokens)
 * or manage a token lifecycle (OAuth 2.0 — see {@see OAuth2Authentication}).
 */
interface AuthenticationInterface
{
    /**
     * Return an HttpClient that has been configured to inject the credentials
     * (typically via the `Authorization` header) into every outgoing request.
     */
    public function decorate(HttpClientInterface $client): HttpClientInterface;

    /**
     * Force a credential refresh outside the normal lifecycle (e.g. after a 401).
     *
     * No-op for static credential schemes; OAuth implementations exchange the
     * refresh_token here.
     */
    public function refresh(): void;

    /**
     * True if the credentials are currently in a state that prevents API calls
     * (e.g. refresh_token rejected by the IdP, missing initial authorization).
     */
    public function requiresReauth(): bool;
}
