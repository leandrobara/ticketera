<?php

namespace App\Services\API\WAutomations;

use Exception;
use Throwable;
use App\Models\User;
use App\Models\Client;
use App\Services\API\TagService;
use Illuminate\Support\Facades\DB;
use App\Models\WAutomationProposal;
use App\Helpers\MermaidChartHelper;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\WhatsAppTemplateService;
use App\Models\WAutomationProposalResendRule;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAutomations\WAutomationProposalDTO;
use App\Models\WAutomationProposalInteractionRule;
use App\Models\WAutomationProposalModifyLeadAfterSendRule;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Repositories\WAutomations\WAutomationProposalRepository;


class WAutomationProposalService
{

    use GetClientFromRequest, GetUserFromRequest;

    public function __construct(
        protected readonly TagService $tagService,
        protected readonly MermaidChartHelper $mermaidChartHelper,
        protected readonly WhatsAppTemplateService $whatsAppTemplateService,
        protected readonly EmailEventsDispatcherService $emailEventsDispatcherService,
        protected readonly WAutomationProposalRepository $wAutomationProposalRepository,
        protected readonly WAutomationProposalResendService $wAutomationProposalResendService,
        protected readonly WAutomationProposalModifyLeadAfterSendService $wAutomationProposalModifyLeadAfterSendService,
    ) {
    }


    public function findWAutomationProposalByClient(Client $client): ?WAutomationProposal
    {
        return $this->wAutomationProposalRepository->findByClient($client);
    }


    public function saveWAutomationProposal(WAutomationProposalDTO $dto): WAutomationProposal
    {
        $client = $this->getClient();
        $dto->client = $client;
        $wAutomationProposal = $this->findWAutomationProposalByClient($client);

        try {
            DB::beginTransaction();

            if ($wAutomationProposal->enabled !== $dto->enabled) {
                $wAutomationProposal = $this->wAutomationProposalRepository->update($wAutomationProposal, $dto);
            }
            if ($dto->wAutomationProposalResendDTO) {
                $wAutomationProposalResendDTO = $dto->wAutomationProposalResendDTO;
                $wAutomationProposalResendDTO->client = $client;
                $wAutomationProposalResendDTO->wAutomationProposal = $wAutomationProposal;
                $this->wAutomationProposalResendService->save($dto->wAutomationProposalResendDTO);
            }

            if ($dto->wAutomationProposalModifyLeadAfterSendDTO) {
                $wAutomationProposalModifyLeadAfterSendDTO = $dto->wAutomationProposalModifyLeadAfterSendDTO;
                $wAutomationProposalModifyLeadAfterSendDTO->client = $client;
                $wAutomationProposalModifyLeadAfterSendDTO->wAutomationProposal = $wAutomationProposal;
                $this->wAutomationProposalModifyLeadAfterSendService->save(
                    $dto->wAutomationProposalModifyLeadAfterSendDTO
                );
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if (!$wAutomationProposal->enabled) {
            $this->emailEventsDispatcherService->dispatchSendDisabledAutomationEmailAlertJob(
                $wAutomationProposal, $this->getUser()
            );
        }

        return $wAutomationProposal;
    }


    public function createNewClientDefault(Client $client): WAutomationProposal
    {
        $attrs = ['client_id' => $client->id];
        $autProp = WAutomationProposal::factory()->create($attrs);
        $attrs['wautomation_proposal_id'] = $autProp->id;

        $tpl = $this->whatsAppTemplateService->createNewClientDefaultProposalResend($client);
        $resendRule = WAutomationProposalResendRule::factory()
            ->newClientDefault()
            ->create($attrs + ['send_whatsapp_template_id' => $tpl->id]);

        $afterSendRule = WAutomationProposalModifyLeadAfterSendRule::factory()
            ->newClientDefault()
            ->create($attrs);

        return $autProp;
    }


    public function getFlowChartMarkdownString(?WAutomationProposal $wAutomationProposal = null): string
    {
        if (!$wAutomationProposal) {
            $message = "No hay automatización de presupuesto de WhatsApp";
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $markdown = $this->mermaidChartHelper->buildWAutomationProposalMarkdown($wAutomationProposal);
        return $markdown;
    }

}
