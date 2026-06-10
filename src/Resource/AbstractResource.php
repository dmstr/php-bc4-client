<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

use Dmstr\Bc4Client\Client\Bc4Client;

/**
 * Base class for resource wrappers — gives them access to the shared HTTP/auth
 * pipeline of the {@see Bc4Client} without duplicating retry/refresh logic.
 */
abstract class AbstractResource
{
    public function __construct(protected readonly Bc4Client $client)
    {
    }
}
