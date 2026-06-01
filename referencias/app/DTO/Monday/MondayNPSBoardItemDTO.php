<?php

namespace App\DTO\Monday;

use DateTime;
use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\NPSPoll;
use Illuminate\Support\Str;
use App\Models\NPSPollAnswer;


class MondayNPSBoardItemDTO
{

    const NPS_GROUP_ID = 'topics';
    const NPS_GROUP_NAME = 'Encuestas NPS';

    const CSAT_GROUP_ID = 'group_title';
    const CSAT_GROUP_NAME = 'Encuestas CSAT';

    public ?string $id = null;
    public ?string $name = null;
    public ?string $groupId = null;
    public ?User $clientyUser = null;
    public ?string $subdomain = null;
    public ?string $groupName = null;
    public ?int $clientyUserId = null;
    public ?int $clientyClientId = null;
    public ?Client $clientyClient = null;

    // private array $rawMondayItemData = [];
    public array $columnsToUpdate = [
        'puntaje__1' => ['name' => 'Puntaje', 'value' => null],
        'enlace__1' => ['name' => 'Link CRM', 'value' => null],
        'texto_1__1' => ['name' => 'Usuario', 'value' => null],
        'texto2__1' => ['name' => 'Respuesta', 'value' => null],
        'n_meros__1' => ['name' => 'ID Cliente', 'value' => null],
        'n_meros9__1' => ['name' => 'ID Usuario', 'value' => null],
        'texto05__1' => ['name' => 'Texto encuesta', 'value' => null],
        // 'texto__1' => ['name' => 'Total puntajes', 'value' => null],
        // 'n_meros3__1' => ['name' => 'Puntaje promedio', 'value' => null],
        'n_meros7__1' => ['name' => 'ID última encuesta', 'value' => null],
        'fecha__1' => ['name' => 'Fecha última encuesta', 'value' => null],
        'date' => ['name' => 'Fecha última actualización', 'value' => null],
        'tel_fono_usuario__1' => ['name' => 'Teléfono usuario', 'value' => null],
    ];


    private function __construct()
    {
    }


    public static function buildFromClientyUser(
        User $clientyUser,
        string $groupId
    ): MondayNPSBoardItemDTO {
        $dto = new MondayNPSBoardItemDTO();
        $dto->clientyUser = $clientyUser;
        $dto->clientyUserId = $clientyUser->id;
        $dto->name = $clientyUser->client->name;
        $dto->clientyClient = $clientyUser->client;
        $dto->clientyClientId = $clientyUser->client->id;
        $dto->subdomain = $clientyUser->client->subdomain;

        if ($groupId == self::NPS_GROUP_ID) {
            $dto->groupId = self::NPS_GROUP_ID;
            $dto->groupName = self::NPS_GROUP_NAME;
        }
        if ($groupId == self::CSAT_GROUP_ID) {
            $dto->groupId = self::CSAT_GROUP_ID;
            $dto->groupName = self::CSAT_GROUP_NAME;
        }
        return $dto;
    }


    public static function buildFromMondayItemData(array $itemData): MondayNPSBoardItemDTO
    {
        $dto = new MondayNPSBoardItemDTO();
        // $dto->rawMondayItemData = $itemData;

        $dto->id = $itemData['id'];
        $dto->name = $itemData['name'];
        $dto->groupId = $itemData['group']['id'];
        $dto->groupName = $itemData['group']['title'];
        $dto->subdomain = $dto->getSubdomainValue($itemData['column_values']);
        
        $clientyUserId = $dto->findMondayColumnValueById($itemData['column_values'], 'n_meros9__1');
        $clientyClientId = $dto->findMondayColumnValueById($itemData['column_values'], 'n_meros__1');
        $dto->clientyUserId = $clientyUserId ? (int) $clientyUserId : null;
        $dto->clientyClientId = $clientyClientId ? (int) $clientyClientId : null;
        return $dto;
    }


    public function isNPSPollItem(): bool
    {
        return $this->groupName == self::NPS_GROUP_NAME;
    }


    public function isCSATPollItem(): bool
    {
        return $this->groupName == self::CSAT_GROUP_NAME;
    }


    public function fillColumnValuesByUserAndNPSPollAnswer(
        User $user,
        NPSPollAnswer $NPSPollAnswer
    ): MondayNPSBoardItemDTO {
        $this->setColumnValue('n_meros9__1', $user->id);
        $this->setColumnValue('n_meros__1', $user->client->id);
        $this->setColumnValue('enlace__1', [
            'url' => 'https://' . $user->client->subdomain . '.clienty.co',
            'text' => 'https://' . $user->client->subdomain . '.clienty.co',
        ]);

        $userStr = "{$user->name} {$user->last_name} ($user->username)";
        $scoreStr = '' . ($NPSPollAnswer->score ?? '0') . '';
        $lastPollDateStr = $NPSPollAnswer->NPSPoll->created_at->format('Y-m-d');
        $mondayPollText = "{$NPSPollAnswer->NPSPoll->score_title} | {$NPSPollAnswer->NPSPoll->comments_title}";

        $this->setColumnValue('puntaje__1', $scoreStr); // Puntaje
        $this->setColumnValue('texto_1__1', $userStr); // Usuario
        $this->setColumnValue('n_meros7__1', $NPSPollAnswer->NPSPoll->id); // ID última encuesta
        $this->setColumnValue('fecha__1', $lastPollDateStr); // Fecha última encuesta
        $this->setColumnValue('texto05__1', $mondayPollText); // Texto encuesta
        $this->setColumnValue('tel_fono_usuario__1', $user->phone); // Teléfono usuario
        $this->setColumnValue('texto2__1', $NPSPollAnswer->comments ?? ''); // Respuesta
        $this->setColumnValue('date', (new DateTime('now'))->format('Y-m-d'));
        return $this;
    }


    public function getFormattedColumnValues(): array
    {
        $columnValues = [];
        foreach ($this->columnsToUpdate as $id => $col) {
            if ($col['value'] !== null) {
                $columnValues[$id] = $col['value'];
            }
        }
        return $columnValues;
    }


    private function getSubdomainValue(array $mondayColumnValues): ?string
    {
        $urlArr = $this->findMondayColumnValueByTitle($mondayColumnValues, 'Link CRM');
        if (!$urlArr) {
            return null;
        }
        $host = parse_url($urlArr['url'], PHP_URL_HOST);
        $subdomain = Str::before($host, '.');
        return $subdomain;
    }


    private function findMondayColumnValueById(array $mondayColumnValues, string $id): string | array | null
    {
        foreach ($mondayColumnValues as $columnValue) {
            if ($columnValue['column']['id'] === $id) {
                return ($columnValue['value'] !== null) ? json_decode($columnValue['value'], true) : null;
            }
        }
        return null;
    }


    private function findMondayColumnValueByTitle(array $mondayColumnValues, string $title): string | array | null
    {
        foreach ($mondayColumnValues as $columnValue) {
            if (strtolower($columnValue['column']['title']) == strtolower($title)) {
                return ($columnValue['value'] !== null) ? json_decode($columnValue['value'], true) : null;
            }
        }
        return null;
    }


    private function setColumnValue(string $id, $value): void
    {
        if (isset($this->columnsToUpdate[$id])) {
            $this->columnsToUpdate[$id]['value'] = $value;
        } else {
            throw new Exception("Column ID {$id} does not exist in columnsToUpdate.");
        }
    }

}