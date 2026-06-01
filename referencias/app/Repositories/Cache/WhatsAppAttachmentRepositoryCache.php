<?php

namespace App\Repositories\Cache;

use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\WhatsAppAttachment;
use App\Exceptions\DatabaseException;


class WhatsAppAttachmentRepositoryCache extends RepositoryBaseCache implements Repository
{
}
