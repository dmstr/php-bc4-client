<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

class TodolistsResource extends AbstractResource
{
    /**
     * Classic todolists belonging to a project's todoset.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInProject(int|string $projectId): array
    {
        $todoset = $this->client->todoSets()->findInProject($projectId);
        if ($todoset === null) {
            return [];
        }

        return $this->client->paginate(sprintf('/buckets/%s/todosets/%s/todolists.json', $projectId, $todoset['id']));
    }
}
