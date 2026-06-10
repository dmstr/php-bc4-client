<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

use Dmstr\Bc4Client\Exception\RequestException;

class ProjectsResource extends AbstractResource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->client->paginate('/projects.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int|string $projectId): ?array
    {
        try {
            return $this->client->request('GET', sprintf('/projects/%s.json', $projectId));
        } catch (RequestException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
