<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Client;

use Dmstr\Bc4Client\Authentication\AuthenticationInterface;
use Dmstr\Bc4Client\Exception\RequestException;
use Dmstr\Bc4Client\Resource\CardsResource;
use Dmstr\Bc4Client\Resource\CardTablesResource;
use Dmstr\Bc4Client\Resource\ColumnsResource;
use Dmstr\Bc4Client\Resource\PeopleResource;
use Dmstr\Bc4Client\Resource\ProjectsResource;
use Dmstr\Bc4Client\Resource\TodoSetsResource;
use Dmstr\Bc4Client\Resource\TodolistsResource;
use Dmstr\Bc4Client\Resource\TodosResource;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Entry point for talking to a single Basecamp 4 account.
 *
 * Holds the shared HTTP pipeline (auth-decorated transport, retries,
 * pagination) and hands out resource wrappers for the supported endpoints.
 */
class Bc4Client
{
    public const BASE_URL = 'https://3.basecampapi.com';
    public const MAX_429_RETRIES = 5;
    public const INITIAL_BACKOFF_SECONDS = 1;

    private ?HttpClientInterface $http;
    private ?ResponseInterface $lastResponse = null;

    private ?ProjectsResource $projects = null;
    private ?TodoSetsResource $todoSets = null;
    private ?TodolistsResource $todolists = null;
    private ?TodosResource $todos = null;
    private ?PeopleResource $people = null;
    private ?CardTablesResource $cardTables = null;
    private ?ColumnsResource $columns = null;
    private ?CardsResource $cards = null;

    public function __construct(
        private readonly string $accountId,
        private readonly AuthenticationInterface $authentication,
        ?HttpClientInterface $http = null,
    ) {
        $this->http = $http;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getEndpoint(): string
    {
        return sprintf('%s/%s', self::BASE_URL, $this->accountId);
    }

    public function projects(): ProjectsResource
    {
        return $this->projects ??= new ProjectsResource($this);
    }

    public function todoSets(): TodoSetsResource
    {
        return $this->todoSets ??= new TodoSetsResource($this);
    }

    public function todolists(): TodolistsResource
    {
        return $this->todolists ??= new TodolistsResource($this);
    }

    public function todos(): TodosResource
    {
        return $this->todos ??= new TodosResource($this);
    }

    public function people(): PeopleResource
    {
        return $this->people ??= new PeopleResource($this);
    }

    public function cardTables(): CardTablesResource
    {
        return $this->cardTables ??= new CardTablesResource($this);
    }

    public function columns(): ColumnsResource
    {
        return $this->columns ??= new ColumnsResource($this);
    }

    public function cards(): CardsResource
    {
        return $this->cards ??= new CardsResource($this);
    }

    /**
     * Single GET-or-write call to the BC4 API with full retry/refresh handling.
     *
     * @param array<string, mixed> $options Symfony HttpClient request options
     * @return array<mixed>
     */
    public function request(string $method, string $path, array $options = []): array
    {
        if ($this->authentication->requiresReauth()) {
            throw new \RuntimeException('Authentication requires fresh user authorization');
        }

        $attempt = 0;
        $alreadyRefreshed = false;
        $backoff = self::INITIAL_BACKOFF_SECONDS;

        while (true) {
            $attempt++;
            try {
                $response = $this->dispatch($method, $path, $options);
                $this->lastResponse = $response;
                $status = $response->getStatusCode();

                if ($status === 401 && !$alreadyRefreshed) {
                    $this->authentication->refresh();
                    $alreadyRefreshed = true;
                    continue;
                }
                if ($status === 429 && $attempt <= self::MAX_429_RETRIES) {
                    sleep($backoff);
                    $backoff *= 2;
                    continue;
                }
                if ($status >= 400) {
                    throw new RequestException(
                        sprintf('BC4 API returned status=%d for %s %s', $status, $method, $path),
                        $status,
                        $response->getContent(false),
                    );
                }

                return $this->decode($response);
            } catch (HttpExceptionInterface $e) {
                $status = $e->getResponse()->getStatusCode();
                if ($status === 401 && !$alreadyRefreshed) {
                    $this->authentication->refresh();
                    $alreadyRefreshed = true;
                    continue;
                }
                if ($status === 429 && $attempt <= self::MAX_429_RETRIES) {
                    sleep($backoff);
                    $backoff *= 2;
                    continue;
                }
                throw new RequestException($e->getMessage(), $status, '', $e);
            } catch (ExceptionInterface $e) {
                throw new \RuntimeException('BC4 transport error: ' . $e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Walk a paginated endpoint, following `Link: <…>; rel="next"` headers.
     *
     * @param array<string, mixed> $options
     * @return array<int, array<mixed>>
     */
    public function paginate(string $path, array $options = []): array
    {
        $items = [];
        $next = $path;

        while ($next !== null) {
            if (str_starts_with($next, self::BASE_URL)) {
                $next = substr($next, strlen($this->getEndpoint()));
            }

            $page = $this->request('GET', $next, $options);
            if (!is_array($page)) {
                break;
            }
            foreach ($page as $row) {
                $items[] = $row;
            }
            $options = [];
            $next = $this->lastResponse !== null ? $this->parseNextLink($this->lastResponse) : null;
        }

        return $items;
    }

    /**
     * Underlying HTTP dispatch. Always re-decorates with the authentication
     * layer so refreshed tokens are picked up immediately.
     */
    private function dispatch(string $method, string $path, array $options): ResponseInterface
    {
        $client = $this->authentication->decorate($this->getHttp());
        $url = $this->buildUri($path);

        return $client->request($method, $url, $options);
    }

    private function buildUri(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->getEndpoint() . '/' . ltrim($path, '/');
    }

    /**
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        if ($status === 204) {
            return [];
        }

        $body = $response->getContent(false);
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('BC4 returned non-JSON response');
        }

        return $decoded;
    }

    private function parseNextLink(ResponseInterface $response): ?string
    {
        $headers = $response->getHeaders(false);
        $link = $headers['link'] ?? $headers['Link'] ?? [];
        if (!is_array($link) || $link === []) {
            return null;
        }

        foreach ($link as $headerValue) {
            foreach (explode(',', $headerValue) as $segment) {
                if (preg_match('/<([^>]+)>;\s*rel="next"/', trim($segment), $m)) {
                    return $m[1];
                }
            }
        }

        return null;
    }

    private function getHttp(): HttpClientInterface
    {
        if ($this->http === null) {
            $this->http = HttpClient::create(['timeout' => 30]);
        }

        return $this->http;
    }
}
