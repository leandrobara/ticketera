<?php

namespace App\Helpers;

use DateTime;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\DTO\Monday\MondayNPSBoardItemDTO;
use App\DTO\Monday\MondayAPICelulaOBBoardClientLastWeeksHitsDTO;


// @TODO unificar con MondayAPIHelper (que usa Make.com)
class MondayAPIHelper2
{

    protected $apiToken;
    protected $obBoardId;
    protected $npsBoardId;
    protected $churnBoardId;
    protected $guzzleClient;
    protected $apiUrl = 'https://api.monday.com/v2/';


    public function __construct(
        string $apiToken,
        int|string $obBoardId,
        int|string $npsBoardId,
        int|string $churnBoardId,
    ) {
        $this->apiToken = $apiToken;
        $this->obBoardId = $obBoardId;
        $this->npsBoardId = $npsBoardId;
        $this->churnBoardId = $churnBoardId;
        $this->guzzleClient = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'API-Version' => '2024-07',
                'Authorization' => $this->apiToken,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }


    public function listBoards(): array
    {
        $query = '
            query {
                boards {
                    id
                    name
                }
            }
        ';
        $data = $this->sendRequest($query);
        return $data['boards'] ?? [];
    }


    public function listNPSBoardItems(array $opts = []): Collection
    {
        return $this->listBoardItems($this->npsBoardId, $opts);
    }


    public function listChurnBoardItems(array $opts = []): Collection
    {
        return $this->listBoardItems($this->churnBoardId, $opts);
    }


    public function listOBBoardItems(array $opts = []): Collection
    {
        return $this->listBoardItems($this->obBoardId, $opts);
    }


    public function listClientsBoardItems(array $opts = []): Collection
    {
        return $this->listBoardItems('6967792411', $opts);
    }


    public function findClientsBoardItemsKeyedByLeadId(array $opts = []): Collection
    {
        $mondayClientsBoardClients = $this->listClientsBoardItems($opts);
        $mondayClientsBoardClients = $mondayClientsBoardClients->map(function ($mondayClient) {
            $leadIdColumn = collect($mondayClient['column_values'])->first(function ($column) {
                return isset($column['column']['title']) && $column['column']['title'] === 'ID';
            });
            $mondayClient['leadID'] = null; // Asignar null si no tiene un ID numérico
            if ($leadIdColumn && isset($leadIdColumn['value'])) {
                $mondayClient['leadID'] = preg_replace('/\D/', '', $leadIdColumn['value']);
            }
            return $mondayClient;
        });
        $mondayClientsBoardClients = $mondayClientsBoardClients->filter(function ($mondayClient) {
            return is_numeric($mondayClient['leadID']);
        });
        $mondayClientsBoardClients = $mondayClientsBoardClients->keyBy('leadID');
        return $mondayClientsBoardClients;
    }


    public function listBoardItems(int $boardId, array $opts = []): Collection
    {
        $cursor = null;
        $allItems = [];
        $limit = (int) ($opts['limit'] ?? 100);
        $pageLimit = min($limit, 100);
        
        do {
            $query = $this->buildBoardItemsQuery($boardId, $cursor, $pageLimit);
            // echo($query);
            $data = $this->sendRequest($query);
            $itemsPage = $data['boards'][0]['items_page'] ?? $data['next_items_page'] ?? [];
            $items = $itemsPage['items'] ?? [];
            $cursor = $itemsPage['cursor'] ?? null;
            
            $allItems = array_merge($allItems, $items);
            $remainingItems = $limit - count($allItems);
            $pageLimit = min($remainingItems, 100);
        } while ($cursor && count($allItems) < $limit);

        $allItems = array_slice($allItems, 0, $limit); // Asegura que no exceda el límite especificado.
        return new Collection($allItems);
    }


    public function persistNPSPollItem(MondayNPSBoardItemDTO $dto, string $boardGroupId)
    {
        if ($dto->id) {
            return $this->updateNPSPollItem($dto, $boardGroupId);
        }
        return $this->createNPSPollItem($dto, $boardGroupId);
    }


    public function createNPSPollItem(MondayNPSBoardItemDTO $dto, string $boardGroupId)
    {
        return $this->createItem(
            itemName: $dto->name,
            groupId: $dto->groupId,
            boardId: $this->npsBoardId,
            columnValues: $dto->getFormattedColumnValues(),
        );
    }


    public function createUnsubscribeItem(string $itemName, array $columnValues)
    {
        //tablero: 1. pedidos de baja
        $boardId = 7038826698;
        $groupId = "group_mksamh86";

        return $this->createItem(
            groupId: $groupId,
            boardId: $boardId,
            itemName: $itemName,
            columnValues: $columnValues,
        );
    }


    public function updateNPSPollItem(MondayNPSBoardItemDTO $dto)
    {
        return $this->updateItem(
            itemId: $dto->id,
            boardId: $this->npsBoardId,
            columnValues: $dto->getFormattedColumnValues(),
        );
    }


    public function updateCelulaOBLastWeeksHits(MondayAPICelulaOBBoardClientLastWeeksHitsDTO $dto)
    {
        $values = [];
        $values['n_meros2__1'] = $dto->averageHits;
        $values['n_meros'] = $dto->hitsCountLastWeek;
        $values['n_meros8__1'] = $dto->hitsCountTwoWeeksAgo;
        $values['n_meros6__1'] = $dto->hitsCountFourWeeksAgo;
        $values['n_meros4__1'] = $dto->hitsCountThreeWeeksAgo;
        $values['fecha3__1'] = (new DateTime())->format('Y-m-d H:i:s');
        
        return $this->updateItem(
            itemId: $dto->id,
            columnValues: $values,
            boardId: $this->obBoardId,
        );
    }


    public function updateUsersUsagePercentage(
        MondayAPICelulaOBBoardClientLastWeeksHitsDTO $dto,
        int $usersUsagePercentage
    ) {
        $values = [];
        $values['n_meros47__1'] = $usersUsagePercentage;
        $values['fecha34__1'] = (new DateTime())->format('Y-m-d H:i:s');
        return $this->updateItem(
            itemId: $dto->id,
            columnValues: $values,
            boardId: $this->obBoardId,
        );
    }


    public function createItem(
        int $boardId,
        string $groupId,
        string $itemName,
        array $columnValues = []
    ): array {
        $columnValuesJSONStr = json_encode($columnValues,
            JSON_HEX_APOS |
            // JSON_HEX_QUOT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );
        $columnValuesJSONStr = addslashes($columnValuesJSONStr);
        $query = "
          mutation {
            create_item (
              board_id: {$boardId},
              group_id: \"{$groupId}\",
              item_name: \"{$itemName}\",
              column_values: \"{$columnValuesJSONStr}\"
            ) {
              id
            }
          }
        ";
        $data = $this->sendRequest($query);
        return $data['create_item'] ?? [];
    }


    public function updateItem(
        int $boardId,
        int $itemId,
        array $columnValues = []
    ): array {
        $columnValuesJSONStr = json_encode($columnValues,
            JSON_HEX_APOS |
            // JSON_HEX_QUOT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );
        $columnValuesJSONStr = addslashes($columnValuesJSONStr);
        $query = "
          mutation {
            change_multiple_column_values (
              item_id: {$itemId},
              board_id: {$boardId},
              column_values: \"{$columnValuesJSONStr}\"
            ) {
              id
            }
          }
        ";
        $data = $this->sendRequest($query);
        return $data['change_multiple_column_values'] ?? [];
    }


    public function updateSingleColumn(
        int $boardId,
        int $itemId,
        string $columnId,
        $value,
        string $columnType = 'text'
    ): array {
        switch ($columnType) {
            case 'link':
                // Idealmente recibir $value acá así: ['url' => 'xxx', 'text' => 'xxx']
                if (is_string($value) && Str::startsWith(trim($value), '{')) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }
                break;
            case 'status':
                if (is_string($value)) {
                    $value = ['label' => $value];
                }
                break;
            case 'date':
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d');
                }
                break;
            case 'numbers':
                if (is_numeric($value)) {
                    $value = $value + 0;
                }
                break;
            case 'email':
                if (is_string($value)) {
                    $value = ['email' => $value, 'text' => $value];
                }
                break;
            case 'country':
                if (is_string($value)) {
                    $value = ['country' => $value];
                }
                break;
            case 'text':
            case 'long_text':
                $value = (string) $value;
                break;
        }

        return $this->updateItem($boardId, $itemId, [
            $columnId => $value
        ]);
    }


    protected function sendRequest(string $query, array $variables = []): array
    {
        try {
            $payload = ['query' => $query];
            if (!empty($variables)) {
                $payload['variables'] = $variables;
            }
            $response = $this->guzzleClient->post('', ['json' => $payload]);
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);
            if (isset($data['errors'])) {
                throw new Exception('Monday API Error: ' . json_encode($data['errors']));
            }
            return $data['data'];
        } catch (Exception $e) {
            throw new Exception('Error communicating with Monday API: ' . $e->getMessage());
        }
    }


    protected function buildBoardItemsQuery($boardId, ?string $cursor, int $pageLimit): string
    {
        $itemsResponseSubQuery = "
            cursor
            items {
              id
              name
              group {
                id
                title
              }
              column_values {
                column {
                  id
                  title
                }
                id
                type
                value
                ... on StatusValue { # will only run for status columns
                  label
                  update_id
                }
                ... on LinkValue { # will only run for link columns
                  url
                  url_text
                }
                ... on DateValue { # will only run for date columns
                  time
                  date
                }
                ... on DropdownValue {
                  id
                  text
                  value
                }
                ... on MirrorValue {
                  display_value
                  id
                }
                ... on EmailValue {
                  email
                  updated_at
                }
                ... on HourValue {
                  minute
                  hour
                }
                ... on LongTextValue {
                  text
                  value
                }
              }
              created_at
              updated_at
            }
        ";
        if (!$cursor) {
            $query = "
              query {
                boards (ids: {$boardId}) {
                  items_page (limit: {$pageLimit}) {
                    $itemsResponseSubQuery
                  }
                }
              }
            ";
            return $query;
        }
        $query = "
          query {
            next_items_page (limit: {$pageLimit}, cursor: \"{$cursor}\") {
              $itemsResponseSubQuery
            }
          }
        ";
        return $query;
    }

}
