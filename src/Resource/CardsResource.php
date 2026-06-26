<?php
// file generated with AI assistance: Claude Code - 2026-06-26

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

use Dmstr\Bc4Client\Exception\RequestException;

/**
 * Cards of a BC4 card table. Cards are the kanban-board analogue of todos:
 * {@see getAllInProject()} mirrors {@see TodosResource::getAllInProject()} by
 * walking every column of the project's card table and enriching each card with
 * its `card_table { id, title }` and `column { id, title, type }` so consumers
 * can group cards the same way they group todos by todolist.
 */
class CardsResource extends AbstractResource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInColumn(int|string $projectId, int|string $columnId): array
    {
        return $this->client->paginate(
            sprintf('/buckets/%s/card_tables/lists/%s/cards.json', $projectId, $columnId),
        );
    }

    /**
     * Walk a project's card table (all columns) and return every card enriched
     * with its card table and column. Returns an empty array when the project
     * has no enabled card table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllInProject(int|string $projectId): array
    {
        $table = $this->client->cardTables()->getInProject($projectId);
        if ($table === null) {
            return [];
        }

        $tableRef = ['id' => $table['id'] ?? null, 'title' => $table['title'] ?? null];
        $cards = [];

        foreach ($table['lists'] ?? [] as $column) {
            if (!isset($column['id'])) {
                continue;
            }
            $columnRef = [
                'id' => $column['id'],
                'title' => $column['title'] ?? null,
                'type' => $column['type'] ?? null,
            ];
            foreach ($this->getInColumn($projectId, $column['id']) as $card) {
                $card['card_table'] = $tableRef;
                $card['column'] = $columnRef;
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * Load a single card, enriched with its column and card table (resolved via
     * the card's parent column) so single-record sync matches the scan output.
     *
     * @return array<string, mixed>|null
     */
    public function get(int|string $projectId, int|string $cardId): ?array
    {
        try {
            $card = $this->client->request(
                'GET',
                sprintf('/buckets/%s/card_tables/cards/%s.json', $projectId, $cardId),
            );
        } catch (RequestException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }

        // A card's `parent` is its column; the column's `parent` is the card
        // table. Resolve both so the enrichment matches getAllInProject().
        $columnId = $card['parent']['id'] ?? null;
        if ($columnId !== null) {
            $column = $this->client->columns()->get($projectId, $columnId);
            if ($column !== null) {
                $card['column'] = [
                    'id' => $column['id'] ?? $columnId,
                    'title' => $column['title'] ?? null,
                    'type' => $column['type'] ?? null,
                ];
                if (isset($column['parent']['id'])) {
                    $card['card_table'] = [
                        'id' => $column['parent']['id'],
                        'title' => $column['parent']['title'] ?? null,
                    ];
                }
            }
        }

        return $card;
    }
}
