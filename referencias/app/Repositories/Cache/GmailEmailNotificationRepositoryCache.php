<?php

namespace App\Repositories\Cache;

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\GmailEmailNotification;
use Illuminate\Database\Eloquent\Model;


class GmailEmailNotificationRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findNotViewedResponsesByUser(User $user): Collection
    {
        $key = $this->getMethodRedisKey('not-viewed:user-' . $user->id);
        $landings = $this->findOrStoreFromCache($user->client_id, $key, __FUNCTION__, func_get_args());
        return $landings;
    }

}
