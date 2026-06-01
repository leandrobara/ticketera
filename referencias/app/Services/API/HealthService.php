<?php

namespace App\Services\API;

use Exception;
use App\Models\Lead;
use App\Models\Client;
use App\Helpers\RedisHelper;


class HealthService
{

    private $appIsWorker;


    public function __construct(bool $appIsWorker = false)
    {
        $this->appIsWorker = $appIsWorker;
    }


    public function isSupervisorUp(): bool
    {
        $isEnabledEnv = environmentIn(['production', 'prod', 'staging']);
        if (!$this->appIsWorker || !$isEnabledEnv) {
            return true;
        }

        $checkString = 'Active: active (running)';
        $output = shell_exec('service supervisor status 2>&1');
        $isUp = stripos($output, $checkString) !== false;
        return $isUp;
    }


    public function isApacheUp(): bool
    {
        return true;
    }


    public function isRedisUp(): bool
    {
        $redisHelper = resolve(RedisHelper::class, ['clientId' => 999999999]);
        return $redisHelper->redisIsUp();
    }


    public function isElasticUp(): bool
    {
        try {
            $lead = Lead::search()->first();
        } catch (Exception $e) {
            return false;
        }
        return $lead ? true : false;
    }


    public function isDatabaseUp(): bool
    {
        try {
            $lead = Lead::first();
        } catch (Exception $e) {
            return false;
        }
        return $lead ? true : false;
    }

}
