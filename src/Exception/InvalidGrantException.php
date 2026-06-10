<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Exception;

/**
 * Thrown when Launchpad rejects the refresh_token with `400 invalid_grant`.
 *
 * Signals that the persisted refresh_token is no longer usable — the calling
 * application must run a fresh user-authorization flow (interactive OAuth).
 * Storage backends typically mark the configuration as `requires_reauth`.
 */
class InvalidGrantException extends Bc4ApiException
{
}
