<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

/**
 * BC4 projects expose tools through the `dock` array. The "todoset" tool is
 * the entry point to classic todolists; this helper resolves it for a project.
 */
class TodoSetsResource extends AbstractResource
{
    /**
     * Resolve the todoset tool for a project (from the project's dock).
     *
     * @return array<string, mixed>|null
     */
    public function findInProject(int|string $projectId): ?array
    {
        $project = $this->client->projects()->get($projectId);
        if ($project === null) {
            return null;
        }

        foreach ($project['dock'] ?? [] as $tool) {
            if (($tool['name'] ?? null) === 'todoset' && ($tool['enabled'] ?? false)) {
                return $tool;
            }
        }

        return null;
    }
}
