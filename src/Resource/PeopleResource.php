<?php
// file generated with AI assistance: Claude Code - 2026-05-07

declare(strict_types=1);

namespace Dmstr\Bc4Client\Resource;

use Dmstr\Bc4Client\Exception\RequestException;

class PeopleResource extends AbstractResource
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(int|string $personId): ?array
    {
        try {
            return $this->client->request('GET', sprintf('/people/%s.json', $personId));
        } catch (RequestException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
