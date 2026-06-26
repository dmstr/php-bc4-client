<?php
// file generated with AI assistance: Claude Code - 2026-06-26

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

use Dmstr\Bc4Client\Exception\RequestException;

/**
 * BC4 projects expose tools through the `dock` array. The "kanban_board" tool
 * is the entry point to a project's card table — the card-table analogue of the
 * "todoset" tool resolved by {@see TodoSetsResource}. The dock tool's `id`
 * equals the card table id, so it can be loaded directly.
 */
class CardTablesResource extends AbstractResource
{
    /**
     * Resolve the kanban_board tool for a project (from the project's dock).
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
            if (($tool['name'] ?? null) === 'kanban_board' && ($tool['enabled'] ?? false)) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Load a single card table. Its JSON embeds the `lists` array (= columns).
     *
     * @return array<string, mixed>|null
     */
    public function get(int|string $projectId, int|string $cardTableId): ?array
    {
        try {
            return $this->client->request(
                'GET',
                sprintf('/buckets/%s/card_tables/%s.json', $projectId, $cardTableId),
            );
        } catch (RequestException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Resolve the project's card table via its dock and load it. Returns null
     * when the project has no enabled kanban_board tool.
     *
     * @return array<string, mixed>|null
     */
    public function getInProject(int|string $projectId): ?array
    {
        $tool = $this->findInProject($projectId);
        if ($tool === null || !isset($tool['id'])) {
            return null;
        }

        return $this->get($projectId, $tool['id']);
    }
}
