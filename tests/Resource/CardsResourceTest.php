<?php
// file generated with AI assistance: Claude Code - 2026-06-26

declare(strict_types=1);

namespace Dmstr\Bc4Client\Tests\Resource;

use Dmstr\Bc4Client\Authentication\AuthenticationInterface;
use Dmstr\Bc4Client\Client\Bc4Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Orchestration tests for the card-table resources. The HTTP pipeline itself is
 * covered by Bc4ClientTest; here we assert the dock → table → columns → cards
 * walk and the card_table/column enrichment that mirrors TodosResource.
 */
class CardsResourceTest extends TestCase
{
    private const ACCOUNT = '6164391';

    public function testGetAllInProjectWalksColumnsAndEnrichesCards(): void
    {
        $project = [
            'id' => 47064467,
            'dock' => [
                ['id' => 1, 'name' => 'message_board', 'enabled' => true],
                ['id' => 9833339747, 'name' => 'kanban_board', 'enabled' => true, 'title' => 'Card Table'],
            ],
        ];
        $cardTable = [
            'id' => 9833339747,
            'title' => 'Card Table',
            'lists' => [
                ['id' => 9833339752, 'title' => 'Triage', 'type' => 'Kanban::Triage'],
                ['id' => 9833339809, 'title' => 'Done', 'type' => 'Kanban::DoneColumn'],
            ],
        ];
        $triageCards = [
            ['id' => 111, 'type' => 'Kanban::Card', 'title' => 'A'],
            ['id' => 112, 'type' => 'Kanban::Card', 'title' => 'B'],
        ];
        $doneCards = [
            ['id' => 222, 'type' => 'Kanban::Card', 'title' => 'C', 'completed' => true],
        ];

        $http = new MockHttpClient([
            new MockResponse((string) json_encode($project), ['http_code' => 200]),
            new MockResponse((string) json_encode($cardTable), ['http_code' => 200]),
            new MockResponse((string) json_encode($triageCards), ['http_code' => 200]),
            new MockResponse((string) json_encode($doneCards), ['http_code' => 200]),
        ]);

        $cards = (new Bc4Client(self::ACCOUNT, $this->makeAuthStub(), $http))
            ->cards()->getAllInProject(47064467);

        $this->assertCount(3, $cards);
        $this->assertSame([111, 112, 222], array_column($cards, 'id'));

        // Every card is enriched with the card table (the parent/group).
        foreach ($cards as $card) {
            $this->assertSame(9833339747, $card['card_table']['id']);
            $this->assertSame('Card Table', $card['card_table']['title']);
        }

        // The column reflects the iterated list, not the card's own payload.
        $this->assertSame('Triage', $cards[0]['column']['title']);
        $this->assertSame('Kanban::Triage', $cards[0]['column']['type']);
        $this->assertSame('Done', $cards[2]['column']['title']);
        $this->assertSame('Kanban::DoneColumn', $cards[2]['column']['type']);

        // 1 project + 1 card table + 2 column card pages.
        $this->assertSame(4, $http->getRequestsCount());
    }

    public function testGetAllInProjectReturnsEmptyWhenNoKanbanBoard(): void
    {
        $project = ['id' => 1, 'dock' => [['id' => 2, 'name' => 'todoset', 'enabled' => true]]];
        $http = new MockHttpClient([new MockResponse((string) json_encode($project), ['http_code' => 200])]);

        $cards = (new Bc4Client(self::ACCOUNT, $this->makeAuthStub(), $http))
            ->cards()->getAllInProject(1);

        $this->assertSame([], $cards);
        $this->assertSame(1, $http->getRequestsCount(), 'No card table → no card requests');
    }

    public function testGetSingleCardResolvesColumnAndCardTable(): void
    {
        $card = [
            'id' => 111,
            'type' => 'Kanban::Card',
            'title' => 'A',
            'parent' => ['id' => 9833339752, 'title' => 'Triage', 'type' => 'Kanban::Triage'],
        ];
        $column = [
            'id' => 9833339752,
            'title' => 'Triage',
            'type' => 'Kanban::Triage',
            'parent' => ['id' => 9833339747, 'title' => 'Card Table', 'type' => 'Kanban::Board'],
        ];

        $http = new MockHttpClient([
            new MockResponse((string) json_encode($card), ['http_code' => 200]),
            new MockResponse((string) json_encode($column), ['http_code' => 200]),
        ]);

        $result = (new Bc4Client(self::ACCOUNT, $this->makeAuthStub(), $http))
            ->cards()->get(47064467, 111);

        $this->assertNotNull($result);
        $this->assertSame('Triage', $result['column']['title']);
        $this->assertSame(9833339747, $result['card_table']['id']);
        $this->assertSame('Card Table', $result['card_table']['title']);
    }

    public function testGetSingleCardReturnsNullOn404(): void
    {
        $http = new MockHttpClient([new MockResponse('{"error":"not found"}', ['http_code' => 404])]);

        $result = (new Bc4Client(self::ACCOUNT, $this->makeAuthStub(), $http))
            ->cards()->get(1, 999);

        $this->assertNull($result);
    }

    private function makeAuthStub(): AuthenticationInterface
    {
        $auth = $this->createStub(AuthenticationInterface::class);
        $auth->method('requiresReauth')->willReturn(false);
        $auth->method('decorate')->willReturnArgument(0);

        return $auth;
    }
}
