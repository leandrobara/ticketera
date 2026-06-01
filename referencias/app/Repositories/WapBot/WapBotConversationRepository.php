<?php

namespace App\Repositories\WapBot;

use DateTime;
use Carbon\Carbon;
use App\Models\WapBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\MongoDB\WapBot\WapBotConversation;
use App\Repositories\Criteria\Sort\MongoSortCriteria;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;

/**
 * MongoDB
 */
class WapBotConversationRepository
{

    public function find(string $id): WapBotConversation
    {
        return WapBotConversation::find($id);
    }
    

    public function findLatestConversation(
        int $clientId,
        string $botMetaPhoneNumberId,
        string $customerPhoneNumber
    ): ?WapBotConversation {
        return WapBotConversation::where('clientId', $clientId)
            ->where('botMetaPhoneNumberId', $botMetaPhoneNumberId)
            ->where('customerPhoneNumber', $customerPhoneNumber)
            ->orderByDesc('lastActivityAt')
            ->orderByDesc('createdAt')
            ->first()
        ;
    }


    public function hasReceivedNewMessages(WapBotConversation $conversation, string $currentLastMetaMessageId): bool
    {
        $conversation = WapBotConversation::where('_id', $conversation->id)
            ->select(['lastMetaMessageId'])
            ->first()
        ;
        if (!$conversation) {
            return false;
        }
        return $conversation->lastMetaMessageId !== $currentLastMetaMessageId;
    }


    public function findWithPendingFollowUp(
        WapBot $wapBot,
        DateTime $lastActivityDateStart,
        DateTime $lastActivityDateEnd,
    ): Collection {
        $query = WapBotConversation::where('clientId', $wapBot->client_id)
            ->where('botMetaPhoneNumberId', $wapBot->meta_phone_number_id)
            ->where('isEnded', false)
            ->where('type', WapBotConversation::TYPE_BOT_CONVERSATION)
            ->whereBetween('lastActivityAt', [$lastActivityDateStart, $lastActivityDateEnd])
            ->where(function ($query) {
                $query->whereNull('followUpMessage1')->orWhere('followUpMessage1', 'exists', false);
            })
        ;
        // DB::connection('mongodb_wap_bot')->enableQueryLog();
        $wapBots = $query->get();
        // $mongoQueryLog = DB::connection('mongodb_wap_bot')->getQueryLog();
        // dump('$mongoQueryLog', $mongoQueryLog);
        return $wapBots;
    }


    public function findConversationsForAutoLeadCreation(
        WapBot $wapBot,
        DateTime $lastActivityDateStart,
        DateTime $lastActivityDateEnd,
    ): Collection {
        // dump($lastActivityDateStart, $lastActivityDateEnd, $wapBot->meta_phone_number_id);
        return WapBotConversation::where('clientId', $wapBot->client_id)
            ->where('botMetaPhoneNumberId', $wapBot->meta_phone_number_id)
            ->where('isEnded', false)
            ->where('type', WapBotConversation::TYPE_BOT_CONVERSATION)
            ->whereNull('leadId')
            ->whereBetween('lastActivityAt', [$lastActivityDateStart, $lastActivityDateEnd])
            ->get()
        ;
    }


    // Método ágil para hacer esto, para luego chequear si hay más mensajes antes de responder.
    public function update(WapBotConversation $conversation, array $updateData): bool
    {
        return (bool) WapBotConversation::where('_id', $conversation->id)->update($updateData);
    }


    public function create(WapBotConversation $newWapBotConversation): WapBotConversation
    {
        $newWapBotConversation->save();
        return $newWapBotConversation->fresh();
    }


    public function save(WapBotConversation $conversation): WapBotConversation
    {
        $conversation->save();
        return $conversation->fresh();
    }


    public function delete(WapBotConversation $newWapBotConversation): bool
    {
        return (bool) $newWapBotConversation->delete();
    }


    public function findSeedConversationByPhoneNumber(
        int $clientId,
        string $botMetaPhoneNumberId,
        string $customerPhoneNumber
    ): ?WapBotConversation {
        return WapBotConversation::where('clientId', $clientId)
            ->where('botMetaPhoneNumberId', $botMetaPhoneNumberId)
            ->where('customerPhoneNumber', $customerPhoneNumber)
            ->where('type', WapBotConversation::TYPE_HISTORY_SEED)
            ->first()
        ;
    }


    public function findActiveNonSeedConversations(
        int $clientId,
        string $botMetaPhoneNumberId,
        string $customerPhoneNumber
    ): Collection {
        return WapBotConversation::where('clientId', $clientId)
            ->where('botMetaPhoneNumberId', $botMetaPhoneNumberId)
            ->where('customerPhoneNumber', $customerPhoneNumber)
            ->where('type', WapBotConversation::TYPE_BOT_CONVERSATION)
            ->where('isEnded', false)
            ->get()
        ;
    }


    public function findByClientAndLead(int $clientId, int $leadId): ?WapBotConversation
    {
        return WapBotConversation::where('clientId', $clientId)
            ->where('leadId', $leadId)
            ->orderByDesc('createdAt')
            ->first()
        ;
    }


    public function cursorByClientWithLeadAndReferralData(
        int $clientId,
        array $columns = ['_id', 'clientId', 'leadId', 'referralData', 'createdAt']
    ): LazyCollection {
        return WapBotConversation::query()
            ->where('clientId', $clientId)
            ->where('leadId', 'exists', true)
            ->where('leadId', '!=', null)
            ->where('referralData', 'exists', true)
            ->where('referralData', '!=', null)
            ->orderBy('leadId')
            ->orderByDesc('createdAt')
            ->select($columns)
            ->cursor()
        ;
    }


    public function listPaginated(int $clientId, array $opts = []): LengthAwarePaginator
    {
        $relationshipsToEagerLoad = $opts['with'] ?? [];
        $queryBuilder = WapBotConversation::query();
        $queryBuilder->where('clientId', $clientId);
        
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }
        $queryBuilder = $this->applyFilters($queryBuilder, $opts['filters']);

        $sort = $opts['sort'] ?? null;
        if ($sort && is_a($sort, MongoSortCriteria::class)) {
            $queryBuilder = $sort->applySort($queryBuilder);
        } else {
            $queryBuilder->orderBy('createdAt', 'desc');
        }
        
        if ($opts['withTrashed'] ?? false) {
            $queryBuilder->withTrashed();
        }
 
        // \Illuminate\Support\Facades\DB::connection('mongodb_wap_bot')->enableQueryLog();
        $paginated = $queryBuilder->paginate($opts['limit'], ['*'], 'page', $opts['page']);
        // dd(\Illuminate\Support\Facades\DB::connection('mongodb_wap_bot')->getQueryLog());
        return $paginated;
    }


    protected function applyFilters($queryBuilder, array $filters)
    {
        foreach ($filters as $key => $value) {
            if (isset($filters[$key])) {
                if ($filters[$key] instanceof MongoFilterCriteria) {
                    $queryBuilder = $filters[$key]->filterMongoQuery($queryBuilder);
                } elseif (is_array($value)) {
                    $queryBuilder->whereIn($key, $value);
                } else {
                    $queryBuilder->where($key, $value);
                }
            }
        }
        return $queryBuilder;
    }

}
