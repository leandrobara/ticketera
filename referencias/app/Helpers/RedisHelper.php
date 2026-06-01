<?php

namespace App\Helpers;

use Exception;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\GetClientFromRequest;


class RedisHelper
{

    use GetClientFromRequest;

    protected $redis;
    protected $prefix;
    protected $clientId;
    protected $clientHashKey;
    protected $redisIsUp = false;
    // $client --> setted at GetClientFromRequest trait


    /**
     * Las claves se componen por:
     * $clientHashKey -> scope que indica de que cliente es la clave.
     * $key -> clave parcial del objeto o collection a cachear.
     * Entonces cuando se combinan, se usa $scopedKey:
     * - Client:999:ClaveParcialCualquiera
     */
    public function __construct(?int $clientId = null, string $connectionName = 'cache')
    {
        if (!config('app.enable_redis_cache')) {
            return;
        }

        try {
            $this->redis = Redis::connection($connectionName);
            $this->redisIsUp = true;
            $this->prefix = config('database.redis.options.prefix');
        } catch (Exception $exception) {
            report($exception);
            $this->redisIsUp = false;
        }

        // FIX THIS MESS
        if ($clientId) {
            $this->setClientId($clientId);
        }
    }


    public function store(string $key, $element, array $opts = []): bool
    {
        $this->validateClientIsSetted();

        $ttlSeconds = $opts['ttlSeconds'] ?? 86400;

        $scopedKey = $this->getScopedKey($key);
        // dump('REDISHELPER STORE | $scopedKey', $scopedKey);
        // dump('REDISHELPER STORE | serialize($element)', serialize($element));
        $ok = $this->redis->set($scopedKey, serialize($element));
        $this->redis->expire($scopedKey, $ttlSeconds);
        return $ok;
    }


    public function get(string $key, array $opts = [])
    {
        $this->validateClientIsSetted();

        $scopedKey = $this->getScopedKey($key);
        $element = $this->redis->get($scopedKey);
        // dump('REDISHELPER GET | $scopedKey', $scopedKey);
        // dump('REDISHELPER GET | $element', $element);
        return ($element !== null) ? unserialize($element) : null;
    }


    public function exists(string $key): bool
    {
        $this->validateClientIsSetted();
        return $this->redis->exists($this->getScopedKey($key));
    }


    public function delete(string $key): bool
    {
        $this->validateClientIsSetted();

        $scopedKey = $this->getScopedKey($key);
        $ok = $this->redis->del($scopedKey);
        return $ok ? true : false;
    }


    // Esto se usa para borrar el cache de un repo para todos los clientes
    public function deleteForAllClientsByPartialKey(string $partialKey): bool
    {
        $ok = true;
        $searchTerm = '*:' . $partialKey . ':*';
        $keys = $this->redis->keys($searchTerm);
        foreach ($keys as $key) {
            $key = str_replace($this->prefix, '', $key);
            $ok = $ok && $this->redis->del($key);
        }
        return $ok;
    }


    public function deleteForClientByPartialKey(string $partialKey, ?int $clientId = null): bool
    {
        $ok = true;
        $keys = $this->getAllMatchesWithPartialKey($partialKey, $clientId);
        foreach ($keys as $key) {
            $key = str_replace($this->prefix, '', $key);
            $ok = $ok && $this->redis->del($key);
        }
        return $ok;
    }


    public function deleteByKeyMatching(string $key, ?int $clientId = null): bool
    {
        $ok = true;
        $keys = $this->getAllMatchesWithKey($key, $clientId);
        foreach ($keys as $key) {
            $key = str_replace($this->prefix, '', $key);
            $ok = $ok && $this->redis->del($key);
        }
        return $ok;
    }


    public function deleteAll(): bool
    {
        $this->redis->flushDB();
        return true;
    }


    public function deleteAllClientCache(?int $clientId = null): bool
    {
        $keys = $this->getAllClientKeys($clientId);

        $ok = true;
        foreach ($keys as $key) {
            $key = str_replace($this->prefix, '', $key);
            $ok = $ok && $this->redis->del($key);
        }
        return $ok;
    }


    public function getAllClientKeys(?int $clientId = null): array
    {
        if ($clientId) {
            $clientHashKey = $this->buildClientHashKey($clientId);
        } else {
            $this->validateClientIsSetted();
            $clientHashKey = $this->clientHashKey;
        }

        return $this->redis->keys($clientHashKey . ':*');
    }


    public function getAllMatchesWithPartialKey(string $partialKey, ?int $clientId = null): array
    {
        if ($clientId) {
            $clientHashKey = $this->buildClientHashKey($clientId);
        } else {
            $this->validateClientIsSetted();
            $clientHashKey = $this->clientHashKey;
        }

        $scopedPartialKey = $this->getScopedKey($partialKey, $clientHashKey);
        $searchTerm = '*' . $scopedPartialKey . ':*';
        return $this->redis->keys($searchTerm);
    }


    public function getAllMatchesWithKey(string $key, ?int $clientId = null): array
    {
        if ($clientId) {
            $clientHashKey = $this->buildClientHashKey($clientId);
        } else {
            $this->validateClientIsSetted();
            $clientHashKey = $this->clientHashKey;
        }

        $scopedPartialKey = $this->getScopedKey($key, $clientHashKey);
        $searchTerm = '*' . $scopedPartialKey . '*';
        return $this->redis->keys($searchTerm);
    }


    public function getScopedKey(string $key, ?string $clientHashKey = null): string
    {
        if ($clientHashKey) {
            $scopedKey = "{$clientHashKey}:{$key}";
        } else {
            $scopedKey = "{$this->clientHashKey}:{$key}";
        }
        return $scopedKey;
    }


    public function setClientId(int $clientId): RedisHelper
    {
        $this->clientId = $clientId;
        $this->clientHashKey = $this->buildClientHashKey($clientId);
        return $this;
    }


    protected function validateClientIsSetted(): void
    {
        if (!$this->clientId) {
            throw new Exception('RedisHelper: clientId is not setted');
        }
    }


    protected function buildClientHashKey(int $clientId): string
    {
        return "Client:$clientId";
    }


    public function redisIsUp(): bool
    {
        return $this->redisIsUp;
    }


    public function redisIsDown(): bool
    {
        return !$this->redisIsUp;
    }


    public function __call(string $methodName, array $arguments)
    {
        return call_user_func_array([$this->redis, $methodName], $arguments);
    }

}
