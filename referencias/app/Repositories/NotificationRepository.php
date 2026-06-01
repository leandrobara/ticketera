<?php

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\Notification;
use Illuminate\Support\Collection;
use App\Repositories\Traits\VoidClearCache;


class NotificationRepository implements Repository
{
    
    use VoidClearCache;


    public function create(array $data): Notification
    {
        $notification = new Notification($data);
        $notification->saveOrFail();
        return $notification;
    }


    public function findOneByTypeAndUser(string $type, User $user): ?Notification
    {
        return Notification::where('user_id', $user->id)->where('type', $type)->first();
    }


    public function findOneByTypeAndClient(string $type, Client $client): ?Notification
    {
        return Notification::where('client_id', $client->id)->where('type', $type)->first();
    }


    public function findByTypeAndClient(string $type, Client $client): Collection
    {
        return Notification::where('client_id', $client->id)->where('type', $type)->get();
    }


    public function findEmailSendingNotEnabledByUser(User $user): Collection
    {
        $type = Notification::TYPE_USER_EMAIL_SENDING_NOT_ENABLED;
        $notifications = Notification::where('user_id', $user->id)->where('type', $type)->get();
        return $notifications;
    }


    public function findWAPINotSyncedByUser(User $user): Collection
    {
        $type = Notification::TYPE_USER_WAPI_NOT_SYNCED;
        $notifications = Notification::where('user_id', $user->id)->where('type', $type)->get();
        return $notifications;
    }


    public function findWhatsAppMetaAPINotSyncedByUser(User $user): Collection
    {
        $type = Notification::TYPE_USER_WHATSAPP_META_API_NOT_SYNCED;
        $notifications = Notification::where('user_id', $user->id)->where('type', $type)->get();
        return $notifications;
    }


    public function listClientNotifications(Client $client): Collection
    {
        $types = [
            Notification::TYPE_USER_WAPI_NOT_SYNCED,
            Notification::TYPE_USER_EMAIL_SENDING_NOT_ENABLED,
            Notification::TYPE_USER_WHATSAPP_META_API_NOT_SYNCED,
        ];
        $notifications = Notification::where('client_id', $client->id)
            ->with('user')
            ->whereIn('type', $types)
            ->groupBy(['user_id', 'type'])
            ->get()
        ;
        return $notifications;
    }


    public function delete(Notification $notification, array $opts = []): Notification
    {
        $deletedBySystem = $opts['deletedBySystem'] ?? true;
        $notification->deleted_by_system = $deletedBySystem;
        $notification->deleted_at = Carbon::now();
        $notification->save();
        return $notification->fresh();
    }

}
