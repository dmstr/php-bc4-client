<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

use Dmstr\Bc4Client\Exception\RequestException;

class TodosResource extends AbstractResource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInTodolist(int|string $projectId, int|string $todolistId): array
    {
        return $this->client->paginate(sprintf('/buckets/%s/todolists/%s/todos.json', $projectId, $todolistId));
    }

    /**
     * Walks all classic todolists of a project and returns every todo with
     * `todolist: { id, name }` enrichment from the iterated todolist.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllInProject(int|string $projectId): array
    {
        $todolists = $this->client->todolists()->getInProject($projectId);
        $todos = [];

        foreach ($todolists as $todolist) {
            $rows = $this->getInTodolist($projectId, $todolist['id']);
            foreach ($rows as $todo) {
                $todo['todolist'] = [
                    'id' => $todolist['id'],
                    'name' => $todolist['title'] ?? $todolist['name'] ?? null,
                ];
                $todos[] = $todo;
            }
        }

        return $todos;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int|string $projectId, int|string $todoId): ?array
    {
        try {
            return $this->client->request('GET', sprintf('/buckets/%s/todos/%s.json', $projectId, $todoId));
        } catch (RequestException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
