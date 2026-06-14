<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Tests\Client;

use Dmstr\Bc4Client\Authentication\AuthenticationInterface;
use Dmstr\Bc4Client\Client\Bc4Client;
use Dmstr\Bc4Client\Exception\InvalidGrantException;
use Dmstr\Bc4Client\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP-pipeline tests for the library Bc4Client. Auth is faked via a
 * stub that wraps the MockHttpClient unchanged so we can assert headers.
 */
class Bc4ClientTest extends TestCase
{
    private const ACCOUNT = '6164391';

    public function testAuthDecorateIsAppliedOnEveryRequest(): void
    {
        $http = new MockHttpClient([
            new MockResponse('[]', ['http_code' => 200]),
            new MockResponse('[]', ['http_code' => 200]),
        ]);

        $auth = $this->createMock(AuthenticationInterface::class);
        $auth->method('requiresReauth')->willReturn(false);
        $auth->expects($this->exactly(2))->method('decorate')->willReturnArgument(0);

        $client = $this->makeClient($http, $auth);
        $client->request('GET', '/projects.json');
        $client->request('GET', '/people.json');

        $this->assertSame(2, $http->getRequestsCount());
    }

    public function testUriIsBuiltUnderAccountEndpoint(): void
    {
        $captured = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
            $captured[] = $url;

            return new MockResponse('[]', ['http_code' => 200]);
        });

        $client = $this->makeClient($http, $this->makeAuthStub());
        $client->request('GET', '/projects.json');
        $client->request('GET', 'buckets/1/todos/2.json');

        $this->assertSame('https://3.basecampapi.com/' . self::ACCOUNT . '/projects.json', $captured[0]);
        $this->assertSame('https://3.basecampapi.com/' . self::ACCOUNT . '/buckets/1/todos/2.json', $captured[1]);
    }

    public function testReactiveRefreshOn401ThenRetrySucceeds(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"error":"unauthorized"}', ['http_code' => 401]),
            new MockResponse('[]', ['http_code' => 200]),
        ]);

        // Real mock here (not makeAuthStub): we assert refresh() is called once.
        $auth = $this->createMock(AuthenticationInterface::class);
        $auth->method('requiresReauth')->willReturn(false);
        $auth->method('decorate')->willReturnArgument(0);
        $auth->expects($this->once())->method('refresh');

        $client = $this->makeClient($http, $auth);
        $result = $client->request('GET', '/projects.json');

        $this->assertSame([], $result);
        $this->assertSame(2, $http->getRequestsCount(), 'Expected 1 fail (401) + 1 retry success');
    }

    public function testRetryOn429ThenSucceeds(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"error":"rate_limited"}', ['http_code' => 429]),
            new MockResponse('[]', ['http_code' => 200]),
        ]);

        // Note: production Bc4Client::request() calls sleep(1) before retry.
        // We accept the 1 s test cost here as the cheapest way to verify retry.
        $client = $this->makeClient($http, $this->makeAuthStub('access-1'));
        $client->request('GET', '/projects.json');

        $this->assertSame(2, $http->getRequestsCount());
    }

    public function testInvalidGrantPropagates(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"error":"unauthorized"}', ['http_code' => 401]),
        ]);

        $auth = $this->makeAuthStub('access-1');
        $auth->method('refresh')->willThrowException(new InvalidGrantException('refresh rejected'));

        $client = $this->makeClient($http, $auth);

        $this->expectException(InvalidGrantException::class);
        $client->request('GET', '/projects.json');
    }

    public function testRequiresReauthShortCircuits(): void
    {
        $http = new MockHttpClient([
            new MockResponse('SHOULD_NOT_BE_CALLED', ['http_code' => 200]),
        ]);

        $auth = $this->createStub(AuthenticationInterface::class);
        $auth->method('requiresReauth')->willReturn(true);

        $client = $this->makeClient($http, $auth);

        try {
            $client->request('GET', '/projects.json');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('fresh user authorization', $e->getMessage());
        }

        $this->assertSame(0, $http->getRequestsCount());
    }

    public function testNon2xxAfterRetriesThrowsRequestException(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"error":"boom"}', ['http_code' => 500]),
        ]);

        $client = $this->makeClient($http, $this->makeAuthStub('access-1'));

        try {
            $client->request('GET', '/projects.json');
            $this->fail('Expected RequestException');
        } catch (RequestException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('boom', $e->getResponseBody());
        }
    }

    public function testPaginateFollowsLinkHeader(): void
    {
        $page1 = new MockResponse(json_encode([['id' => 1], ['id' => 2]]), [
            'http_code' => 200,
            'response_headers' => ['Link' => '<https://3.basecampapi.com/' . self::ACCOUNT . '/projects.json?page=2>; rel="next"'],
        ]);
        $page2 = new MockResponse(json_encode([['id' => 3]]), ['http_code' => 200]);
        $http = new MockHttpClient([$page1, $page2]);

        $client = $this->makeClient($http, $this->makeAuthStub('access-1'));
        $items = $client->paginate('/projects.json');

        $this->assertCount(3, $items);
        $this->assertSame([1, 2, 3], array_column($items, 'id'));
    }

    private function makeClient(MockHttpClient $http, AuthenticationInterface $auth): Bc4Client
    {
        return new Bc4Client(self::ACCOUNT, $auth, $http);
    }

    private function makeAuthStub(string $accessToken = 'access-1'): AuthenticationInterface
    {
        $auth = $this->createStub(AuthenticationInterface::class);
        $auth->method('requiresReauth')->willReturn(false);
        // Pass the client through unchanged so MockHttpClient::getRequestsCount()
        // reflects the actual call sequence. Header injection is exercised in
        // OAuth2Authentication's own tests.
        $auth->method('decorate')->willReturnArgument(0);

        return $auth;
    }
}
