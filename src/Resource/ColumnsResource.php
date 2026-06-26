<?php
// file generated with AI assistance: Claude Code - 2026-06-26

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

use Dmstr\Bc4Client\Exception\RequestException;

/**
 * Columns (BC4 calls them "lists") of a card table. BC4 has no standalone
 * "list all columns" endpoint — the columns are embedded as the `lists` array
 * of the card table payload — so iteration goes through the card table. A single
 * column can still be loaded directly (used to resolve a card's parent table).
 */
class ColumnsResource extends AbstractResource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInCardTable(int|string $projectId, int|string $cardTableId): array
    {
        $table = $this->client->cardTables()->get($projectId, $cardTableId);

        return is_array($table['lists'] ?? null) ? $table['lists'] : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int|string $projectId, int|string $columnId): ?array
    {
        try {
            return $this->client->request(
                'GET',
                sprintf('/buckets/%s/card_tables/columns/%s.json', $projectId, $columnId),
            );
        } catch (RequestException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
