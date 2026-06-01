<?php

namespace App\Services\API;

use DateTime;
use App\Models\Client;
use App\Models\ClientInteraction;
use Illuminate\Support\Collection;
use App\Repositories\ClientInteractionRepository;


class ClientInteractionService
{

    private $clientInteractionRepository;


    public function __construct(ClientInteractionRepository $clientInteractionRepository)
    {
        $this->clientInteractionRepository = $clientInteractionRepository;
    }


    public function findLastInteractionsFromEachClient(): Collection
    {
        $interactions = $this->clientInteractionRepository->findLastInteractionsFromEachClient();

        return $interactions;
    }


    public function findInteractionsByWeeksAgo(int $weeksAgo): Collection
    {
        if ($weeksAgo <= 0) {
            return collect([]);
        }
        $weekDate = $this->getCurrentWeekDate();
        $weekDates = collect([]);
        for ($i = 0; $i < $weeksAgo; $i++) {
            $weekDates->push(clone $weekDate);
            $weekDate->modify('-7 days');
        }
        return $this->clientInteractionRepository->findInteractionsByWeekDates($weekDates);
    }


    public function countNewInteraction(Client $client): ClientInteraction
    {
        $weekDate = $this->getCurrentWeekDateString();
        $interaction = $this->clientInteractionRepository->findOneOrCreateByClientAndWeekDate($client, $weekDate);
        $interaction->count = $interaction->count + 1;

        return $this->clientInteractionRepository->persist($interaction);
    }


    protected function getCurrentWeekDate(): DateTime
    {
        $date = new DateTime('now');
        $currentDayName = $date->format('l');
        if ($currentDayName != 'Monday') {
            $date = $date->modify('last Monday');
        }
        return $date;
    }


    protected function getCurrentWeekDateString(): string
    {
        $date = $this->getCurrentWeekDate();
        return $date->format('Y-m-d');
    }
}
