<?php

namespace App\Repositories;

use DateTime;
use Exception;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\WhatsAppSending;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WhatsAppSendingMessage;
use App\Repositories\Traits\VoidClearCache;


class WhatsAppSendingMessageRepository implements Repository
{

    use VoidClearCache;


    public function create(array $data): WhatsAppSendingMessage
    {
        $wapMsg = new WhatsAppSendingMessage($data);
        $wapMsg->saveOrFail();
        return $wapMsg->fresh();
    }


    public function update(WhatsAppSendingMessage $wapMsg, array $newAttrs): WhatsAppSendingMessage
    {
        $updatedWapMsg = clone $wapMsg;
        $updatedWapMsg->fill($newAttrs);
        $updatedWapMsg->saveOrFail();
        return $updatedWapMsg->fresh();
    }


    public function cancelMultiple(Collection $wapMsgs): bool
    {
        $ids = $wapMsgs->pluck('id');
        $dateNow = new DateTime('now');
        $updated = WhatsAppSendingMessage::whereIn('id', $ids)
            ->whereNull('sent_date')
            ->whereNull('cancelled_date')
            ->update(['cancelled_date' => $dateNow, 'paused_date' => null])
        ;
        // if (!$updated) {
        //     throw new Exception('whatsapp_message_repository_cancel_error');
        // }
        return $updated;
    }


    public function pauseMultiple(Collection $wapMsgs): bool
    {
        $ids = $wapMsgs->pluck('id');
        $dateNow = new DateTime('now');
        $updated = WhatsAppSendingMessage::whereIn('id', $ids)
            ->whereNull('sent_date')
            ->whereNull('paused_date')
            ->whereNull('cancelled_date')
            ->update(['paused_date' => $dateNow])
        ;
        // if (!$updated) {
        //     throw new Exception('whatsapp_message_repository_pause_error');
        // }
        return $updated;
    }


    public function resumeMultiple(Collection $wapMsgs): bool
    {
        $ids = $wapMsgs->pluck('id');
        $updated = WhatsAppSendingMessage::whereIn('id', $ids)
            ->whereNotNull('paused_date')
            ->update(['paused_date' => null])
        ;
        // if (!$updated) {
        //     throw new Exception('whatsapp_message_repository_resume_error');
        // }
        return $updated;
    }


    public function markMultipleAsDispatched(Collection $wapMsgs): bool
    {
        $ids = $wapMsgs->pluck('id');
        $dateNow = new DateTime('now');
        $updated = WhatsAppSendingMessage::whereIn('id', $ids)
            ->whereNull('dispatched_date')
            ->update(['dispatched_date' => $dateNow, 'last_dispatched_date' => $dateNow])
        ;
        return $updated;
    }


    public function countPeriodSentOrScheduledByUserAndType(
        User $user,
        string $type,
        DateTime $dateStart,
        DateTime $dateEnd
    ): int {
        return WhatsAppSendingMessage::where('type', $type)
            ->where('user_id', $user->id)
            ->whereNotNull('cancelled_date')
            ->where('send_date', '>=', $dateStart)
            ->where('send_date', '<=', $dateEnd)
            ->count()
        ;
    }


    public function countNonAutomationProposalsByLead(Lead $lead): int
    {
        return WhatsAppSendingMessage::where('is_proposal', true)
            ->whereNull('wautomation_log_id')
            ->where('lead_id', $lead->id)
            ->count()
        ;
    }


    // @todo facu: hacer index para esto.
    public function findOneToSaveAsWhatsAppConversationMessage(int $id): ?WhatsAppSendingMessage
    {
        $with = [
            'leadContactPhone',
            'user.whatsAppMetaAPIConnection',
            'whatsAppSending.whatsAppAttachment',
            'whatsAppSending.whatsAppTemplate.whatsAppAttachment',
        ];
        return WhatsAppSendingMessage::with($with)->find($id);
    }


    public function findStuckedByClient(Client $client): Collection
    {
        $query = WhatsAppSendingMessage::query()
            ->where('client_id', $client->id)
            ->whereNull('success')
            ->whereNull('sent_date')
            ->whereNull('paused_date')
            ->whereNull('cancelled_date')
            ->whereNotNull('dispatched_date')
            ->where('dispatched_date', '<', new DateTime('1 hour ago'))
            ->whereIn('type', [WhatsAppSendingMessage::WAPI_TYPE, WhatsAppSendingMessage::WAP_SENDER_TYPE])
        ;
        // dd($query->toSql());
        return $query->get();
    }


    public function findLastOneSentWAPIByUser(User $user): ?WhatsAppSendingMessage
    {
        $query = WhatsAppSendingMessage::query()
            ->where('success', true)
            ->whereNull('paused_date')
            ->whereNotNull('sent_date')
            ->whereNull('cancelled_date')
            ->where('user_id', $user->id)
            ->whereNotNull('dispatched_date')
            ->where('type', WhatsAppSendingMessage::WAPI_TYPE)
            ->limit(1)
            ->orderBy('sent_date', 'desc')
        ;
        return $query->first();
    }


    public function findWAPSenderScheduledToSendBetweenSendDates(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $opts = [],
    ): Collection {
        $query = WhatsAppSendingMessage::query()
            ->whereNull('success')
            ->whereNull('sent_date')
            ->whereNull('paused_date')
            ->whereNull('cancelled_date')
            ->whereNull('dispatched_date')
            ->where('client_id', $client->id)
            ->where('send_date', '<', $dateEnd)
            ->where('send_date', '>', $dateStart)
            ->whereColumn('send_date', '>', 'created_at')
            ->where('type', WhatsAppSendingMessage::WAP_SENDER_JOB_TYPE)
            ->orderBy('send_date', 'asc')
        ;
        $limit = $opts['limit'] ?? null;
        if ($limit) {
            $query->limit($limit);
        }
        return $query->get();
    }


    public function findWhatsAppMetaAPIScheduledToSendBetweenSendDates(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $opts = [],
    ): Collection {
        $query = WhatsAppSendingMessage::query()
            ->whereNull('success')
            ->whereNull('sent_date')
            ->whereNull('paused_date')
            ->whereNull('cancelled_date')
            ->whereNull('dispatched_date')
            ->where('client_id', $client->id)
            ->where('send_date', '<', $dateEnd)
            ->where('send_date', '>', $dateStart)
            ->whereColumn('send_date', '>', 'created_at')
            ->where('type', WhatsAppSendingMessage::WHATSAPP_META_API_TYPE)
            ->orderBy('send_date', 'asc')
        ;
        $limit = $opts['limit'] ?? null;
        if ($limit) {
            $query->limit($limit);
        }
        return $query->get();
    }


    public function findFailedWAPSenderScheduledMessagesToRetry(
        User $user,
        DateTime $dateStart,
        array $errorsEnabledToRetry,
        array $opts = [],
    ): Collection {
        $query = WhatsAppSendingMessage::query()
            ->where('success', false)
            ->whereNotNull('sent_date')
            ->where('user_id', $user->id)
            ->whereNull('paused_date')
            ->whereNull('cancelled_date')
            ->whereNotNull('dispatched_date')
            ->where('send_date', '>', $dateStart)
            ->where('client_id', $user->client_id)
            ->whereColumn('send_date', '>', 'created_at')
            ->where('type', WhatsAppSendingMessage::WAP_SENDER_JOB_TYPE)
            ->where(function ($q) use ($errorsEnabledToRetry) {
                foreach ($errorsEnabledToRetry as $error) {
                    $q->orWhereRaw('error_message LIKE ?', ["%{$error}%"]);
                }
            })
            ->orderBy('send_date', 'asc')
        ;
        $limit = $opts['limit'] ?? null;
        if ($limit) {
            $query->limit($limit);
        }
        // DB::enableQueryLog(); $query->get(); dd(DB::getQueryLog());
        return $query->get();
    }


    public function findOneByMetaId(string $metaId): ?WhatsAppSendingMessage
    {
        $metaHashId = WhatsAppSendingMessage::buildMetaIdHash($metaId);
        $query = WhatsAppSendingMessage::query()->where('meta_id', $metaId)->where('meta_id_hash', $metaHashId);
        return $query->first();
    }

}
