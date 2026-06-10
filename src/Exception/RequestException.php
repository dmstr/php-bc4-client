<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Exception;

/**
 * Wraps a non-2xx HTTP response from the BC4 API after retries are exhausted.
 */
class RequestException extends Bc4ApiException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
