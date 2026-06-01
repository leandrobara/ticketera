<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\UserNotification;
use App\Exceptions\DatabaseException;


class UserNotificationRepository
{

    public function findById(int $id): ?UserNotification
    {
        return UserNotification::where(['id' => $id])->get()->first();
    }


    public function findLastUnsubscribeByClientId(int $clientId): ?UserNotification
    {
        return UserNotification::where('notification_type', 'unsubscribe')
            ->where('client_id', $clientId)
            ->orderBy('id', 'desc')
            ->first()
        ;
    }


    public function create(Client $client, User $user, array $data = []): UserNotification
    {
        $data['user_id'] = $user->id;
        $data['client_id'] = $client->id;
        $userNotification = new UserNotification($data);
        $userNotification->saveOrFail();
        return $userNotification->fresh();
    }


    public function update(UserNotification $userNotification, array $data): UserNotification
    {
        $userNotification->fill($data);
        $userNotification->saveOrFail();
        return $userNotification->fresh();
    }

}