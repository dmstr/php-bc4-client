<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Tests\Authentication;

use Dmstr\Bc4Client\Authentication\OAuth2Authentication;
use Dmstr\Bc4Client\Authentication\TokenStorageInterface;
use Dmstr\Bc4Client\Exception\InvalidGrantException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OAuth2AuthenticationTest extends TestCase
{
    public function testProactiveRefreshOnExpiredAccessToken(): void
    {
        $launchpad = new MockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'access-2',
                'refresh_token' => 'refresh-2',
                'expires_in' => 1209600,
            ]), ['http_code' => 200]),
        ]);

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('isFresh')->willReturnCallback(
            static fn (string $iso) => (new \DateTimeImmutable($iso))->getTimestamp() > time() + 60,
        );
        $storage->method('loadTokens')->willReturn([
            'access_token' => null,
            'refresh_token' => 'refresh-1',
            'expires_at' => '2020-01-01T00:00:00+00:00',
            'requires_reauth' => false,
        ]);
        $storage->expects($this->once())
            ->method('refreshAndPersist')
            ->willReturnCallback(function (callable $exchange) {
                $tokens = $exchange('refresh-1');

                return [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'expires_at' => '2030-01-01T00:00:00+00:00',
                ];
            });

        $auth = new OAuth2Authentication('cid', 'secret', 'TestApp', 't@e.de', $storage, $launchpad);
        $token = $auth->getAccessToken();

        $this->assertSame('access-2', $token);
        $this->assertSame(1, $launchpad->getRequestsCount(), 'Exactly one Launchpad call');
    }

    public function testInvalidGrantMarksRequiresReauth(): void
    {
        $launchpad = new MockHttpClient([
            new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400]),
        ]);

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('isFresh')->willReturn(false);
        $storage->method('loadTokens')->willReturn([
            'access_token' => null,
            'refresh_token' => 'refresh-1',
            'expires_at' => '2020-01-01T00:00:00+00:00',
            'requires_reauth' => false,
        ]);
        $storage->method('refreshAndPersist')
            ->willReturnCallback(function (callable $exchange) {
                $exchange('refresh-1'); // throws InvalidGrantException internally
                throw new \LogicException('exchange returned without throwing');
            });
        $storage->expects($this->once())->method('markRequiresReauth');

        $auth = new OAuth2Authentication('cid', 'secret', 'TestApp', 't@e.de', $storage, $launchpad);

        $this->expectException(InvalidGrantException::class);
        $auth->getAccessToken();
    }

    public function testRequiresReauthReadsFromStorage(): void
    {
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('loadTokens')->willReturn([
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'requires_reauth' => true,
        ]);

        $auth = new OAuth2Authentication('cid', 'secret', 'TestApp', 't@e.de', $storage);

        $this->assertTrue($auth->requiresReauth());
    }

    public function testRequiresReauthSurfacesAsInvalidGrantOnDecorate(): void
    {
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('loadTokens')->willReturn([
            'access_token' => null,
            'refresh_token' => 'r',
            'expires_at' => null,
            'requires_reauth' => true,
        ]);
        $storage->method('isFresh')->willReturn(false);

        $auth = new OAuth2Authentication('cid', 'secret', 'TestApp', 't@e.de', $storage);

        $this->expectException(InvalidGrantException::class);
        $auth->decorate(new MockHttpClient());
    }
}
