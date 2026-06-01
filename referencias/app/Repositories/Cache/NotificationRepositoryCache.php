<?php

namespace App\Repositories\Cache;

use App\Models\Client;
use Illuminate\Support\Str;
use App\Models\Notification;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Model;


class NotificationRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function listClientNotifications(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $redisOpts = ['storeEmpty' => true];
        $notifications = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args(), $redisOpts);
        return $notifications;
    }
    
}
