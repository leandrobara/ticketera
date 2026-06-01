<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use App\Models\Lead;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use App\Models\WhatsAppSending;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\ProposalInfoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\ProposalInfoTmpService;
use App\Jobs\WhatsAppEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;


class CreateProposalAfterWAPIMessageSentJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    
    public function __construct(public readonly int $whatsAppSendingMessageId)
    {
    }


    public function handle()
    {
        $wapSendingMsg = WhatsAppSendingMessage::findOrFail($this->whatsAppSendingMessageId);
        $nonValidMessages = $this->getNonValidMessagesIfInvalid($wapSendingMsg);
        if ($nonValidMessages) {
            $this->logProposalNotCreatedMessage($wapSendingMsg, $nonValidMessages);
            return true;
        }

        $lead = Lead::findOrFail($wapSendingMsg->lead_id); // Para fallar si el lead fue borrado
        $existentProposalInfo = resolve(ProposalInfoService::class)->findLastProposalFromLeadIdAndWhatsAppSendingId(
            $wapSendingMsg->lead_id, $wapSendingMsg->whatsapp_sending_id
        );
        if ($existentProposalInfo) {
            // El presupuesto ya fue creado para este envío/mensaje -> No hago nada.
            if ($existentProposalInfo->hasWhatsAppSendingMessage($wapSendingMsg)) {
                return true;
            }

            // El presupuesto ya fue creado para este envío pero NO para este mensaje -> Le agrego el id del mensaje
            $wapMsgIds = $existentProposalInfo->whatsapp_sending_message_ids;
            $wapMsgIds[] = "$wapSendingMsg->id";
            $updateData = ['whatsapp_sending_message_ids' => $wapMsgIds];
            resolve(ProposalInfoService::class)->update($existentProposalInfo, $updateData);
            return true;
        }

        // Busco si hay alguna ProposalInfoTmp con data ya guardada.
        $wapSending = $wapSendingMsg->whatsAppSending;
        $proposalInfoTmp = resolve(ProposalInfoTmpService::class)->findOneByWhatsAppSending($wapSending);
        $proposalAmount = $proposalInfoTmp?->amount ?? 0;
        $proposalDescription = $proposalInfoTmp?->description ?? null;

        // Si llego, el presupuesto no existe para este envío -> Lo creo
        $proposalInfoData = [
            'email_ids' => null,
            'status' => 'opened',
            'amount' => $proposalAmount,
            'user_id' => $wapSendingMsg->user_id,
            'description' => $proposalDescription,
            'client_id' => $wapSendingMsg->client_id,
            'sent_date' => $wapSendingMsg->sent_date,
            'whatsapp_sending_id' => $wapSending->id,
            'whatsapp_sending_message_ids' => ["{$wapSendingMsg->id}"],
        ];
        resolve(ProposalInfoService::class)->create($lead, $proposalInfoData);
    }


    protected function getNonValidMessagesIfInvalid(WhatsAppSendingMessage $wapSendingMsg): array
    {
        $msgsArr = [];
        if (!$wapSendingMsg->success) {
            $msgsArr[] = 'WhatsAppSendingMessage is not success';
        }
        if (!$wapSendingMsg->is_proposal) {
            $msgsArr[] = 'WhatsAppSendingMessage is not proposal';
        }
        if (!$wapSendingMsg->sent_date) {
            $msgsArr[] = 'WhatsAppSendingMessage sent date is null';
        }
        if ($wapSendingMsg->wautomation_log_id) {
            $msgsArr[] = 'WhatsAppSendingMessage is automation';
        }
        if ($wapSendingMsg->cancelled_date) {
            $msgsArr[] = 'WhatsAppSendingMessage was cancelled';
        }

        $wapSending = $wapSendingMsg->whatsAppSending;
        if (!$wapSending) {
            $msgsArr[] = 'WhatsAppSending does not exist';
        }
        if ($wapSending->is_automation) {
            $msgsArr[] = 'WhatsAppSending is automation';
        }
        if ($wapSending->cancelled_date) {
            $msgsArr[] = 'WhatsAppSending was cancelled';
        }
        if (!$wapSending->is_proposal) {
            $msgsArr[] = 'WhatsAppSending is not proposal';
        }

        return $msgsArr;
    }


    protected function logProposalNotCreatedMessage(WhatsAppSendingMessage $wapSendingMsg, array $msgsArr): void
    {
        $this->getInfoLog()->info('- whatsAppSendingMessageId: ' . $wapSendingMsg->id);
        $this->getInfoLog()->info('- ' . implode(' | ', $msgsArr));
        $this->getInfoLog()->info(PHP_EOL . PHP_EOL);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
