<?php

namespace App\Repositories\Cache;

use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Model;
use App\Models\WhatsAppMetaAPIConnection;


class WhatsAppMetaAPIConnectionRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findById(int $id): ?WhatsAppMetaAPIConnection
    {
        $key = $this->getMethodRedisKey((string) $id);
        $universalClientIdCacheKey = config('app.wap_meta_api_conn_universal_client_id_mock');
        return $this->findOrStoreFromCache($universalClientIdCacheKey, $key, __FUNCTION__, func_get_args());
    }


    /**
     * Búsqueda global (sin client_id). Usa app.wap_meta_api_conn_universal_client_id_mock como clientId.
     */
    public function findActiveConnection(Client $client, string $phoneNumberId): ?WhatsAppMetaAPIConnection
    {
        $key = $this->getMethodRedisKey($phoneNumberId);
        return $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
    }


    public function findActiveByPhoneNumberId(string $phoneNumberId): ?WhatsAppMetaAPIConnection
    {
        $key = $this->getMethodRedisKey($phoneNumberId);
        $universalClientIdCacheKey = config('app.wap_meta_api_conn_universal_client_id_mock');
        return $this->findOrStoreFromCache($universalClientIdCacheKey, $key, __FUNCTION__, func_get_args());
    }


    public function findActiveByPhoneNumber(string $phoneNumber): ?WhatsAppMetaAPIConnection
    {
        $key = $this->getMethodRedisKey('phoneNumber-' . $phoneNumber);
        $universalClientIdCacheKey = config('app.wap_meta_api_conn_universal_client_id_mock');
        return $this->findOrStoreFromCache($universalClientIdCacheKey, $key, __FUNCTION__, func_get_args());
    }


    /**
    * @Override Sobrescrito para también limpiar el caché global (app.wap_meta_api_conn_universal_client_id_mock)
    */
    public function create(/*array */$data): Model
    {
        $model = $this->repository->create($data);
        $this->clearCacheForClient($model->client_id);
        $this->clearCacheForClient(config('app.wap_meta_api_conn_universal_client_id_mock'));
        return $model;
    }


    /**
    * @Override Sobrescrito para también limpiar el caché global (app.wap_meta_api_conn_universal_client_id_mock)
    */
    public function update(/*Model */$whatsAppMetaAPIConn, /*array */$data): Model
    {
        $updatedWhatsAppMetaAPIConn = $this->repository->update($whatsAppMetaAPIConn, $data);
        $this->clearCacheForClient($whatsAppMetaAPIConn->client_id);
        $this->clearCacheForClient(config('app.wap_meta_api_conn_universal_client_id_mock'));
        return $updatedWhatsAppMetaAPIConn;
    }


    /**
    * @Override Sobrescrito para también limpiar el caché global (app.wap_meta_api_conn_universal_client_id_mock)
    */
    public function delete(/*Model */$whatsAppMetaAPIConn): Model
    {
        $deletedWhatsAppMetaAPIConn = $this->repository->delete($whatsAppMetaAPIConn);
        $this->clearCacheForClient($whatsAppMetaAPIConn->client_id);
        $this->clearCacheForClient(config('app.wap_meta_api_conn_universal_client_id_mock'));
        return $deletedWhatsAppMetaAPIConn;
    }

}

