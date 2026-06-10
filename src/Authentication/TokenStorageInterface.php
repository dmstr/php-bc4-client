<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Authentication;

/**
 * Persistence backend for OAuth 2.0 access/refresh tokens.
 *
 * Implementations decide where tokens live (Doctrine, Redis, file, in-memory)
 * and how to handle concurrent refreshes (e.g. via row-level pessimistic locks).
 *
 * Returned token arrays use these keys:
 *   - access_token:    ?string
 *   - refresh_token:   ?string
 *   - expires_at:      ?string  (ISO 8601)
 *   - requires_reauth: bool
 */
interface TokenStorageInterface
{
    /**
     * Read the current token state.
     *
     * @return array{access_token: ?string, refresh_token: ?string, expires_at: ?string, requires_reauth: bool}
     */
    public function loadTokens(): array;

    /**
     * Run a token refresh under whatever concurrency control the backend offers
     * (e.g. SELECT ... FOR UPDATE on a database row). The exchange callable
     * receives the current refresh_token and must return a fresh token bundle.
     *
     * Implementations should re-check freshness after acquiring the lock and
     * return existing tokens without invoking $exchange when they are still
     * valid (avoids redundant Launchpad calls on concurrent workers).
     *
     * @param callable(string $refreshToken): array{access_token: string, refresh_token: string, expires_in: int} $exchange
     * @return array{access_token: string, refresh_token: string, expires_at: string}
     */
    public function refreshAndPersist(callable $exchange): array;

    /**
     * Mark the current configuration as needing a fresh user-authorization
     * (e.g. after Launchpad returned `invalid_grant`).
     */
    public function markRequiresReauth(): void;

    /**
     * Convenience hook for the initial authorization flow: persist the very
     * first token bundle obtained from `code → token` exchange.
     *
     * @param array{access_token: string, refresh_token: string, expires_in: int, account_id?: string} $tokens
     */
    public function persistInitialTokens(array $tokens): void;

    /**
     * Helper used by the OAuth layer to decide whether a refresh is needed.
     * Implementations may choose any safety buffer; returning `true` skips
     * the refresh, `false` triggers one.
     */
    public function isFresh(string $expiresAtIso): bool;
}
