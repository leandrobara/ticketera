<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\ClientInteraction;
use Illuminate\Support\Collection;


class ClientInteractionRepository
{
    public function findLastInteractionsFromEachClient(): Collection
    {
        $interactions = ClientInteraction::with(['client'])
            ->join('Clients', 'Clients.id', '=', 'ClientInteractions.client_id')
            ->orderBy('week_date', 'DESC')
            ->orderBy('Clients.leads_client_id', 'ASC')
            ->groupBy('Clients.leads_client_id')
            ->get()
        ;

        return $interactions;
    }


    public function findInteractionsByWeekDates(Collection $weekDates): Collection
    {
        if ($weekDates->isEmpty()) {
            return collect([]);
        }
        $weekDatesStr = $weekDates->map(function ($d) {
            return $d->format('Y-m-d');
        });
        $interactions = ClientInteraction::whereIn('week_date', $weekDatesStr)
            ->with(['client'])
            ->orderBy('week_date', 'DESC')
            ->get()
        ;

        return $interactions;
    }


    public function persist(ClientInteraction $clientInteraction): ClientInteraction
    {
        $clientInteraction->saveOrFail();

        return $clientInteraction;
    }


    public function findOneOrCreateByClientAndWeekDate(Client $client, string $weekDate): ClientInteraction
    {
        $interaction = ClientInteraction::firstOrCreate(
            ['client_id' => $client->id, 'week_date' => $weekDate], ['count' => 0]
        );

        return $interaction;
    }

}
