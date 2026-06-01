<?php

namespace App\Services\API\Automations;

use Exception;
use Throwable;
use App\Models\User;
use App\Models\Client;
use App\Services\API\TagService;
use App\Models\AutomationProposal;
use Illuminate\Support\Facades\DB;
use App\Helpers\MermaidChartHelper;
use App\Services\API\EmailTemplateService;
use App\Services\Traits\GetUserFromRequest;
use App\Models\AutomationProposalResendRule;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\Automations\AutomationProposalDTO;
use App\Models\AutomationProposalInteractionRule;
use App\Models\AutomationProposalModifyLeadAfterSendRule;
use App\Repositories\Automations\AutomationProposalRepository;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Exceptions\Services\Automations\AutomationProposalException;


class AutomationProposalService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        protected readonly TagService $tagService,
        protected readonly MermaidChartHelper $mermaidChartHelper,
        protected readonly EmailTemplateService $emailTemplateService,
        protected readonly EmailEventsDispatcherService $emailEventsDispatcherService,
        protected readonly AutomationProposalRepository $automationProposalRepository,
        protected readonly AutomationProposalResendService $automationProposalResendService,
        protected readonly AutomationProposalInteractionService $automationProposalInteractionService,
        protected readonly AutomationProposalModifyLeadAfterSendService $automationProposalModifyLeadAfterSendService,
    ) {
    }


    public function findAutomationProposalByClient(Client $client): ?AutomationProposal
    {
        return $this->automationProposalRepository->findByClient($client);
    }


    public function saveAutomationProposal(AutomationProposalDTO $dto): AutomationProposal
    {
        try {
            DB::beginTransaction();

            $client = $this->getClient();
            $automationProposal = $this->findAutomationProposalByClient($client);

            if ($automationProposal->enabled !== $dto->enabled) {
                $automationProposal = $this->automationProposalRepository->update($automationProposal, $dto);
            }

            $dto->client = $client;

            if ($dto->automationProposalResendDTO) {
                $automationProposalResendDTO = $dto->automationProposalResendDTO;
                $automationProposalResendDTO->client = $client;
                $automationProposalResendDTO->automationProposal = $automationProposal;
                $this->automationProposalResendService->save(
                    $dto->automationProposalResendDTO
                );
            }

            if ($dto->automationProposalInteractionDTO) {
                $automationProposalInteractionDTO = $dto->automationProposalInteractionDTO;
                $automationProposalInteractionDTO->client = $client;
                $automationProposalInteractionDTO->automationProposal = $automationProposal;
                $this->automationProposalInteractionService->save(
                    $dto->automationProposalInteractionDTO
                );
            }

            if ($dto->automationProposalModifyLeadAfterSendDTO) {
                $automationProposalModifyLeadAfterSendDTO = $dto->automationProposalModifyLeadAfterSendDTO;
                $automationProposalModifyLeadAfterSendDTO->client = $client;
                $automationProposalModifyLeadAfterSendDTO->automationProposal = $automationProposal;
                $this->automationProposalModifyLeadAfterSendService->save(
                    $dto->automationProposalModifyLeadAfterSendDTO
                );
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if (!$automationProposal->enabled) {
            $this->emailEventsDispatcherService->dispatchSendDisabledAutomationEmailAlertJob(
                $automationProposal, $this->getUser()
            );
        }
        return $automationProposal;
    }


    public function createNewClientDefault(Client $client, User $user): AutomationProposal
    {
        $attrs = ['client_id' => $client->id];
        $autProp = AutomationProposal::factory()->create($attrs);
        $attrs['automation_proposal_id'] = $autProp->id;

        $tpl = $this->emailTemplateService->createNewClientDefaultProposalResend($user);
        $resendRule = AutomationProposalResendRule::factory()
            ->newClientDefault()
            ->create($attrs + ['send_email_template_id' => $tpl->id]);

        $afterSendRule = AutomationProposalModifyLeadAfterSendRule::factory()
            ->newClientDefault()
            ->create($attrs);

        $sentProposalTag = $this->tagService->getOrCreateSentProposalTag($client);
        $openProposalTag = $this->tagService->getOrCreateOpenedProposalTag($client);
        $interactionRule = AutomationProposalInteractionRule::factory()
            ->newClientDefault()
            ->create($attrs + ['add_tags_ids' => [$sentProposalTag->id, $openProposalTag->id]]);

        return $autProp;
    }


    public function getFlowChartMarkdownString(?AutomationProposal $automationProposal = null): string
    {
        if (!$automationProposal) {
            $message = "No hay automatización de presupuesto";
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $markdown = $this->mermaidChartHelper->buildAutomationProposalMarkdown($automationProposal);
        return $markdown;
    }

}
