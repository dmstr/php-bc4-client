<?php
// file generated with AI assistance: Claude Code - 2026-06-13 23:14:54 UTC

declare(strict_types=1);

namespace Dmstr\Bc4Client\Tests\Exception;

use Dmstr\Bc4Client\Exception\Bc4ApiException;
use Dmstr\Bc4Client\Exception\InvalidGrantException;
use Dmstr\Bc4Client\Exception\RequestException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the BC4 client exception hierarchy — verifies the type
 * relationships callers rely on for catch-block dispatch, plus the
 * RequestException payload accessors.
 */
final class ExceptionTest extends TestCase
{
    public function testBc4ApiExceptionIsRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, new Bc4ApiException('boom'));
    }

    public function testInvalidGrantExceptionExtendsBc4ApiException(): void
    {
        $exception = new InvalidGrantException('refresh token rejected');

        self::assertInstanceOf(Bc4ApiException::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('refresh token rejected', $exception->getMessage());
    }

    public function testRequestExceptionExposesStatusAndBody(): void
    {
        $exception = new RequestException('Not Found', 404, '{"error":"missing"}');

        self::assertInstanceOf(Bc4ApiException::class, $exception);
        self::assertSame('Not Found', $exception->getMessage());
        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('{"error":"missing"}', $exception->getResponseBody());
        self::assertSame(0, $exception->getCode());
    }

    public function testRequestExceptionResponseBodyDefaultsToEmpty(): void
    {
        self::assertSame('', (new RequestException('Server Error', 500))->getResponseBody());
    }

    public function testRequestExceptionPreservesPreviousThrowable(): void
    {
        $previous = new \LogicException('root cause');
        $exception = new RequestException('Bad Gateway', 502, '', $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
