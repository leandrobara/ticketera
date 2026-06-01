<?php

namespace App\Repositories;

use Exception;
use App\Models\Client;
use App\Models\ClientFacebookPage;
use App\Exceptions\DatabaseException;


class ClientFacebookPageRepository
{

    public function findAllByClient(Client $client)
    {
        return ClientFacebookPage::where('client_id', $client->id)->get();
    }


    public function findOneByPageId(string $pageId): ?ClientFacebookPage
    {
        return ClientFacebookPage::where(['page_id' => $pageId])->first();
    }


    public function findOneByClientAndPageId(Client $client, string $pageId): ?ClientFacebookPage
    {
        return ClientFacebookPage::where(['client_id' => $client->id, 'page_id' => $pageId])->first();
    }


    public function findWithTrashedByClientAndPageId(Client $client, string $pageId)
    {
        return ClientFacebookPage::withTrashed()->where(['client_id' => $client->id, 'page_id' => $pageId])->first();
    }


    public function insert(Client $client, $data)
    {
        $clientFacebookPage = $this->findOneByClientAndPageId($client, $data['page_id']);
        if ($clientFacebookPage) {
            return $this->update($clientFacebookPage, $data['page_token']);
        }
        $clientFacebookPage = new ClientFacebookPage($data);
        $clientFacebookPage->saveOrFail();
        return $clientFacebookPage;
    }


    public function update(ClientFacebookPage $clientFacebookPage, $pageToken)
    {
        $clientFacebookPage->page_token = $pageToken;
        $clientFacebookPage->save();
        return $clientFacebookPage->fresh();
    }


    public function updateNameAndAbout(
        ClientFacebookPage $clientFacebookPage,
        string $name,
        ?string $about
    ): ClientFacebookPage {
        $clientFacebookPage->name = $name;
        $clientFacebookPage->about = $about;
        $clientFacebookPage->save();
        return $clientFacebookPage->fresh();
    }


    public function delete(ClientFacebookPage $clientFacebookPage): ClientFacebookPage
    {
        $clientFacebookPage->delete();
        return $clientFacebookPage->fresh();
    }

}
