<?php

namespace App\Repositories\Cache;

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use App\Models\NewsNotification;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;


class NewsNotificationRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findNotViewedByUser(User $user): Collection
    {
        $key = $this->getMethodRedisKey('not-viewed:user-' . $user->id);
        $newsNotifications = $this->findOrStoreFromCache($user->client_id, $key, __FUNCTION__, func_get_args());
        return $newsNotifications;
    }

}
