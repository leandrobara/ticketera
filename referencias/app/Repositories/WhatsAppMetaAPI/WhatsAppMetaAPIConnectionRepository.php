<?php

namespace App\Repositories\WhatsAppMetaAPI;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\Models\WhatsAppMetaAPIConnection;
use App\Repositories\Traits\VoidClearCache;


class WhatsAppMetaAPIConnectionRepository implements Repository
{

    use VoidClearCache;


    public function findById(int $id): ?WhatsAppMetaAPIConnection
    {
        return WhatsAppMetaAPIConnection::find($id);
    }


    public function findAllByClient(Client $client, array $opts = []): Collection
    {
        $queryBuilder = WhatsAppMetaAPIConnection::where('client_id', $client->id);
        if ($opts['with'] ?? []) {
            $queryBuilder->with($opts['with']);
        }
        return $queryBuilder->get();
    }


    public function findLastByClient(Client $client): ?WhatsAppMetaAPIConnection
    {
        return WhatsAppMetaAPIConnection::where('client_id', $client->id)->orderBy('created_at', 'desc')->first();
    }


    public function findClientOtherWABAIdConnections(WhatsAppMetaAPIConnection $whatsAppMetaAPIConnection): Collection
    {
        return WhatsAppMetaAPIConnection::where('client_id', $whatsAppMetaAPIConnection->client_id)
            ->where('waba_id', '!=', $whatsAppMetaAPIConnection->waba_id)
            ->get()
        ;
    }


    public function findLastByUser(User $user): ?WhatsAppMetaAPIConnection
    {
        return WhatsAppMetaAPIConnection::where('client_id', $user->client_id)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first()
        ;
    }


    public function findActiveByPhoneNumberId(string $phoneNumberId): ?WhatsAppMetaAPIConnection
    {
        return WhatsAppMetaAPIConnection::where('phone_number_id', $phoneNumberId)
            ->orderBy('id', 'desc')
            ->first()
        ;
    }


    public function findActiveByPhoneNumber(string $phoneNumber): ?WhatsAppMetaAPIConnection
    {
        return WhatsAppMetaAPIConnection::where('phone_number', $phoneNumber)
            ->orderBy('id', 'desc')
            ->first()
        ;
    }


    public function findActiveConnection(Client $client, string $phoneNumberId): ?WhatsAppMetaAPIConnection
    {
        return WhatsAppMetaAPIConnection::where('phone_number_id', $phoneNumberId)
            ->where('client_id', $client->id)
            ->orderBy('id', 'desc')
            ->first()
        ;
    }
    

    public function create($data)
    {
        $whatsAppMetaAPIConnection = new WhatsAppMetaAPIConnection($data);
        $whatsAppMetaAPIConnection->saveOrFail();
        return $whatsAppMetaAPIConnection->fresh();
    }


    public function update(WhatsAppMetaAPIConnection $whatsAppMetaAPIConn, array $data): WhatsAppMetaAPIConnection
    {
        $whatsAppMetaAPIConn->fill($data);
        $whatsAppMetaAPIConn->save();
        return $whatsAppMetaAPIConn->fresh();
    }


    public function delete(WhatsAppMetaAPIConnection $whatsAppMetaAPIConnection): WhatsAppMetaAPIConnection
    {
        $whatsAppMetaAPIConnection->delete();
        return $whatsAppMetaAPIConnection->fresh();
    }


    public function cloneConnectionForUser(
        WhatsAppMetaAPIConnection $sourceConnection,
        User $targetUser
    ): WhatsAppMetaAPIConnection {
        // Eliminar conexión existente del usuario destino (soft delete)
        $existingConnection = $this->findLastByUser($targetUser);
        if ($existingConnection) {
            $this->delete($existingConnection);
        }

        $data = [
            'user_id' => $targetUser->id,
            'waba_id' => $sourceConnection->waba_id,
            'client_id' => $sourceConnection->client_id,
            'waba_name' => $sourceConnection->waba_name,
            'business_id' => $sourceConnection->business_id,
            'phone_number' => $sourceConnection->phone_number,
            'access_token' => $sourceConnection->access_token,
            'business_name' => $sourceConnection->business_name,
            'phone_number_id' => $sourceConnection->phone_number_id,
            'phone_number_verified_name' => $sourceConnection->phone_number_verified_name,
            'access_token_expiration_date' => $sourceConnection->access_token_expiration_date,
            'access_token_last_generation_date' => $sourceConnection->access_token_last_generation_date,
        ];
        return $this->create($data);
    }

}
