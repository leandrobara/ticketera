<?php

namespace App\Services\API\Automations;

use App\Models\Client;
use Illuminate\Support\Collection;
use App\Helpers\MermaidChartHelper;
use App\Services\API\Automations\AutomationTaskService;
use App\Services\API\Automations\AutomationNewLeadService;
use App\Services\API\Automations\AutomationProposalService;
use App\Services\API\Automations\AutomationEmailSendService;
use App\Services\API\WAutomations\WAutomationSequenceService;
use App\Services\API\WAutomations\WAutomationProposalService;


class AutomationFlowChartService
{

    public function __construct(
        private MermaidChartHelper $mermaidChartHelper,
        private AutomationTaskService $automationTaskService,
        private AutomationNewLeadService $automationNewLeadService,
        private AutomationProposalService $automationProposalService,
        private AutomationEmailSendService $automationEmailSendService,
        private WAutomationSequenceService $wAutomationSequenceService,
        private WAutomationProposalService $wAutomationProposalService,
    ) {

    }


    public function getFlowChartsMarkdownString(Client $client, string $flowChartType): string
    {
        $markdown = '';
        if ($flowChartType == 'automation_new_lead') {
            $markdown = $this->buildAutomationsNewLeadMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_task') {
            $markdown = $this->buildAutomationsTaskMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_proposal') {
            $markdown = $this->buildAutomationsProposalMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'wautomation_proposal') {
            $markdown = $this->buildWAutomationsProposalMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_email_send') {
            $markdown = $this->buildAutomationsEmailSendMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'wautomation_sequence') {
            $markdown = $this->buildWAutomationsSequenceMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_task_related_wautomation_sequence') {
            $markdown = $this->buildAutomationTaskRelatedWAutomationSequenceMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_task_related_automation_email_send') {
            $markdown = $this->buildAutomationsTaskRelatedAutomationEmailSendMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_new_lead_related_automation_email_send') {
            $markdown = $this->buildAutomationNewLeadRelatedAutomationEmailSendMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_new_lead_related_wautomation_sequence') {
            $markdown = $this->buildAutomationNewLeadRelatedWAutomationSequenceMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_task_related_email_send_wautomation_sequence') {
            $markdown = $this->buildAutomationTaskRelatedEmailSendAndWAutomationSequenceMarkdown($client);
            return $markdown;
        }
        if ($flowChartType == 'automation_new_lead_related_email_send_wautomation_sequence') {
            $markdown = $this->buildAutomationNewLeadRelatedEmailSendAndWAutomationSequenceMarkdown($client);
            return $markdown;
        }

        return $markdown;
    }


    private function buildAutomationsNewLeadMarkdown(Client $client): string
    {
        $automationsNewLead = $this->automationNewLeadService->findAutomationsByClientId($client->id);
            
        if ($automationsNewLead->isEmpty()) {
            $message = "No hay automatizaciones de nuevos prospectos";
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $markdown = $this->mermaidChartHelper->buildAutomationsNewLeadMarkdown($automationsNewLead);
        return $markdown;
    }


    private function buildAutomationsTaskMarkdown(Client $client): string
    {
        $automationsTask = $this->automationTaskService->findAutomationsByClient($client);
            
        if ($automationsTask->isEmpty()) {
            $message = "No hay automatizaciones de tareas";
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $markdown = $this->mermaidChartHelper->buildAutomationsTaskMarkdown($automationsTask);
        return $markdown;
    }


    private function buildAutomationsProposalMarkdown(Client $client): string
    {
        $automationProposal = $this->automationProposalService->findAutomationProposalByClient($client);
            
        if (!$automationProposal) {
            $message = "No hay automatización de presupuesto";
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $markdown = $this->mermaidChartHelper->buildAutomationProposalMarkdown($automationProposal);
        return $markdown;
    }


    private function buildWAutomationsProposalMarkdown(Client $client): string
    {
        $wAutomationProposal = $this->wAutomationProposalService->findWAutomationProposalByClient($client);
            
        if (!$wAutomationProposal) {
            $message = "No hay automatización de presupuesto de WhatsApp";
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $markdown = $this->mermaidChartHelper->buildWAutomationProposalMarkdown($wAutomationProposal);
        return $markdown;
    }


    private function buildAutomationsEmailSendMarkdown(Client $client): string
    {
        $automationsEmailSend = $this->automationEmailSendService->findAutomationsByClient($client);

        if ($automationsEmailSend->isEmpty()) {
            $message = "No hay automatizaciones de secuencias de email";
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $automationsEmailSend = $automationsEmailSend->filter(function ($a) {
            return $a->automationEmailSendSteps->isNotEmpty();
        });

        $markdown = $this->mermaidChartHelper->buildAutomationsEmailSendMarkdown($automationsEmailSend);
        return $markdown;
    }


    private function buildWAutomationsSequenceMarkdown(Client $client): string
    {
        $wAutomationsSequence = $this->wAutomationSequenceService->findByClient($client);

        if ($wAutomationsSequence->isEmpty()) {
            $message = "No hay automatizaciones de secuencias de WhatsApp";
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }
    
        $wAutomationsSequence = $wAutomationsSequence->filter(function ($a) {
            return $a->wAutomationSequenceSteps->isNotEmpty();
        });

        $markdown = $this->mermaidChartHelper->buildWAutomationsSequenceMarkdown($wAutomationsSequence);
        return $markdown;
    }


    private function buildAutomationsTaskRelatedAutomationEmailSendMarkdown(Client $client): string
    {
        $automationsTask = $this->automationTaskService
            ->findAutomationsByClient($client)
            ->where('trigger_type', 'after_task_expiration')
        ;
        
        $automationsEmailSend = $this->automationEmailSendService->findAutomationsByClient($client);

        $filteredAutomationsEmailSend = $this->filterAutomationsEmailSendRelatedWithAutomationsTask(
            $automationsEmailSend, $automationsTask
        );

        if ($filteredAutomationsEmailSend->isEmpty()) {
            $message = "No existen relaciones entre las automatizaciones de tareas con 
                automatizaciones de secuencia de emails"
            ;
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $filteredAutomationsTask = $this->filterAutomationsTaskRelatedWithAutomationsEmailSend(
            $automationsTask, $filteredAutomationsEmailSend
        );

        $markdown = $this->mermaidChartHelper->buildAutomationsTaskMarkdown(
            $filteredAutomationsTask, ['showTitle' => true]
        );
        $markdown .= $this->mermaidChartHelper->buildAutomationsEmailSendMarkdown(
            $filteredAutomationsEmailSend, ['avoidFlowchartTDString' => true, 'showTitle' => true]
        );

        // build connections
        $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenTaskTagsAndEmailSend(
            $filteredAutomationsTask, $filteredAutomationsEmailSend
        );

        $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenTaskStatusAndEmailSend(
            $filteredAutomationsTask, $filteredAutomationsEmailSend
        );

        return $markdown;
    }


    private function buildAutomationTaskRelatedWAutomationSequenceMarkdown(Client $client): string
    {
        $automationsTask = $this->automationTaskService
            ->findAutomationsByClient($client)
            ->where('trigger_type', 'after_task_expiration')
        ;

        $wAutomationsSequence = $this->wAutomationSequenceService->findByClient($client);
            
        $filteredWAutomationsSequence = $this->filterWAutomationsSequenceRelatedWithAutomationsTask(
            $wAutomationsSequence, $automationsTask
        );

        if ($filteredWAutomationsSequence->isEmpty()) {
            $message = "No existen relaciones entre las automatizaciones de 
                tareas y las secuencias de WhatsApp"
            ;
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $filteredAutomationsTask = $this->filterAutomationsTaskRelatedWithWAutomationsSequence(
            $automationsTask, $filteredWAutomationsSequence
        );

        $markdown = $this->mermaidChartHelper->buildAutomationsTaskMarkdown(
            $filteredAutomationsTask, ['showTitle' => true]
        );
        $markdown .= $this->mermaidChartHelper->buildWAutomationsSequenceMarkdown(
            $filteredWAutomationsSequence, ['avoidFlowchartTDString' => true, 'showTitle' => true]
        );

        // build connections
        $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenTaskTagsAndWAutomationsSequence(
            $filteredAutomationsTask, $filteredWAutomationsSequence
        );

        $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenTaskStatusAndWAutomationsSequence(
            $filteredAutomationsTask, $filteredWAutomationsSequence
        );

        return $markdown;
    }


    private function buildAutomationNewLeadRelatedAutomationEmailSendMarkdown(Client $client): string
    {
        $automationsEmailSend = $this->automationEmailSendService->findAutomationsByClient($client);
        $automationsNewLead = $this->automationNewLeadService->findAutomationsByClientId($client->id);

        $filteredAutomationsEmailSend = $this->filterAutomationsEmailSendRelatedWithAutomationsNewLead(
            $automationsEmailSend, $automationsNewLead
        );

        if ($filteredAutomationsEmailSend->isEmpty()) {
            $message = "No existen relaciones entre las automatizaciones de nuevos prospectos con 
                automatizaciones de secuencia de emails"
            ;
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $filteredAutomationsNewLead = $this->filterAutomationsNewLeadRelatedWithAutomationsEmailSend(
            $automationsNewLead, $filteredAutomationsEmailSend
        );
        $markdown = $this->mermaidChartHelper->buildAutomationsNewLeadMarkdown(
            $filteredAutomationsNewLead, ['showTitle' => true]
        );
        $markdown .= $this->mermaidChartHelper->buildAutomationsEmailSendMarkdown(
            $filteredAutomationsEmailSend, ['avoidFlowchartTDString' => true, 'showTitle' => true]
        );

        // build connections
        $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenNewLeadAndEmailSend(
            $filteredAutomationsNewLead, $filteredAutomationsEmailSend
        );

        return $markdown;
    }


    private function buildAutomationNewLeadRelatedWAutomationSequenceMarkdown(Client $client): string
    {
        $wAutomationsSequence = $this->wAutomationSequenceService->findByClient($client);
        $automationsNewLead = $this->automationNewLeadService->findAutomationsByClientId($client->id);
            
        $filteredWAutomationsSequence = $this->filterWAutomationsSequenceRelatedWithAutomationsNewLead(
            $wAutomationsSequence, $automationsNewLead
        );
        if ($filteredWAutomationsSequence->isEmpty()) {
            $message = "No existen relaciones entre las automatizaciones de 
                nuevos prospectos y las secuencias de WhatsApp"
            ;
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $filteredAutomationsNewLead = $this->filterAutomationsNewLeadRelatedWithWAutomationsSequence(
            $automationsNewLead, $filteredWAutomationsSequence
        );

        $markdown = $this->mermaidChartHelper->buildAutomationsNewLeadMarkdown(
            $filteredAutomationsNewLead, ['showTitle' => true]
        );
        $markdown .= $this->mermaidChartHelper->buildWAutomationsSequenceMarkdown(
            $filteredWAutomationsSequence, ['avoidFlowchartTDString' => true, 'showTitle' => true]
        );
        // build connections
        $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenNewLeadAndWAutomationsSequence(
            $filteredAutomationsNewLead, $filteredWAutomationsSequence
        );
        return $markdown;
    }


    private function buildAutomationNewLeadRelatedEmailSendAndWAutomationSequenceMarkdown(Client $client): string
    {
        $wAutomationsSequence = $this->wAutomationSequenceService->findByClient($client);
        $automationsEmailSend = $this->automationEmailSendService->findAutomationsByClient($client);
        $automationsNewLead = $this->automationNewLeadService->findAutomationsByClientId($client->id);
        
        $filteredWAutomationsSequence = $this->filterWAutomationsSequenceRelatedWithAutomationsNewLead(
            $wAutomationsSequence, $automationsNewLead
        );
        $filteredAutomationsEmailSend = $this->filterAutomationsEmailSendRelatedWithAutomationsNewLead(
            $automationsEmailSend, $automationsNewLead
        );
        if ($filteredAutomationsEmailSend->isEmpty() && $filteredWAutomationsSequence->isEmpty()) {
            $message = "No hay relaciones entre automatizaciones de nuevos prospectos
                con automatizaciones de secuencia de emails y
                con automatizaciones de secuencia de WhatsApp"
            ;
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $filteredAutomationsNewLead = $this->filterAutomationsNewLeadRelatedWithAutsEmailSendOrWAutsSequence(
            $automationsNewLead, $filteredAutomationsEmailSend, $filteredWAutomationsSequence
        );
        
        $markdown = $this->mermaidChartHelper->buildAutomationsNewLeadMarkdown(
            $filteredAutomationsNewLead, ['showTitle' => true]
        );
        if ($filteredAutomationsEmailSend->isNotEmpty()) {
            $markdown .= $this->mermaidChartHelper->buildAutomationsEmailSendMarkdown(
                $filteredAutomationsEmailSend, ['avoidFlowchartTDString' => true, 'showTitle' => true]
            );
        }
        if ($filteredWAutomationsSequence->isNotEmpty()) {
            $markdown .= $this->mermaidChartHelper->buildWAutomationsSequenceMarkdown(
                $filteredWAutomationsSequence, ['avoidFlowchartTDString' => true, 'showTitle' => true]
            );
        }
        
        // build connections
        if ($filteredAutomationsEmailSend->isNotEmpty()) {
            $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenNewLeadAndEmailSend(
                $filteredAutomationsNewLead, $filteredAutomationsEmailSend
            );
        }
        if ($filteredWAutomationsSequence->isNotEmpty()) {
            $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenNewLeadAndWAutomationsSequence(
                $filteredAutomationsNewLead, $filteredWAutomationsSequence
            );
        }
        return $markdown;
    }


    // debug 1
    private function buildAutomationTaskRelatedEmailSendAndWAutomationSequenceMarkdown(Client $client): string
    {
        $wAutomationsSequence = $this->wAutomationSequenceService->findByClient($client);
        $automationsEmailSend = $this->automationEmailSendService->findAutomationsByClient($client);
        $automationsTask = $this->automationTaskService
            ->findAutomationsByClient($client)
            ->where('trigger_type', 'after_task_expiration')
        ;
        
        $filteredWAutomationsSequence = $this->filterWAutomationsSequenceRelatedWithAutomationsTask(
            $wAutomationsSequence, $automationsTask
        );
        $filteredAutomationsEmailSend = $this->filterAutomationsEmailSendRelatedWithAutomationsTask(
            $automationsEmailSend, $automationsTask
        );
        if ($filteredAutomationsEmailSend->isEmpty() && $filteredWAutomationsSequence->isEmpty()) {
            $message = "No hay relaciones entre automatizaciones de tareas
                con automatizaciones de secuencia de emails y
                con automatizaciones de secuencia de WhatsApp"
            ;
            $markdown = $this->mermaidChartHelper->buildEmptyNode($message);
            return $markdown;
        }

        $filteredAutomationsTask = $this->filterAutomationsTaskRelatedWithAutsEmailSendOrWAutsSequence(
            $automationsTask, $filteredAutomationsEmailSend, $filteredWAutomationsSequence
        );
        
        $markdown = $this->mermaidChartHelper->buildAutomationsTaskMarkdown(
            $filteredAutomationsTask, ['showTitle' => true]
        );
        if ($filteredAutomationsEmailSend->isNotEmpty()) {
            $markdown .= $this->mermaidChartHelper->buildAutomationsEmailSendMarkdown(
                $filteredAutomationsEmailSend, ['avoidFlowchartTDString' => true, 'showTitle' => true]
            );
        }
        if ($filteredWAutomationsSequence->isNotEmpty()) {
            $markdown .= $this->mermaidChartHelper->buildWAutomationsSequenceMarkdown(
                $filteredWAutomationsSequence, ['avoidFlowchartTDString' => true, 'showTitle' => true]
            );
        }
        
        // build connections
        if ($filteredAutomationsEmailSend->isNotEmpty()) {
            $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenTaskTagsAndEmailSend(
                $filteredAutomationsTask, $filteredAutomationsEmailSend
            );
    
            $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenTaskStatusAndEmailSend(
                $filteredAutomationsTask, $filteredAutomationsEmailSend
            );
        }
        if ($filteredWAutomationsSequence->isNotEmpty()) {
            $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenTaskTagsAndWAutomationsSequence(
                $filteredAutomationsTask, $filteredWAutomationsSequence
            );
    
            $markdown .= $this->mermaidChartHelper->buildConnectionsBetweenTaskStatusAndWAutomationsSequence(
                $filteredAutomationsTask, $filteredWAutomationsSequence
            );
        }
        return $markdown;
    }


    private function filterAutomationsEmailSendRelatedWithAutomationsTask(
        Collection $automationsEmailSend,
        Collection $automationsTask
    ): Collection {
        $taskTagIds = $automationsTask->pluck('tags_ids_to_assign')->flatten()->unique();
        $taskStatusId = $automationsTask->pluck('status_id_to_assign')->flatten()->unique();
    
        $filteredAutEmailSendTagMatches = $automationsEmailSend->filter(
            fn ($aut) => collect($aut->triggering_tags_ids)->intersect($taskTagIds)->isNotEmpty()
        );

        $filteredAutEmailSendStatusMatches = $automationsEmailSend->filter(
            fn ($aut) => collect($aut->triggering_status_ids)->intersect($taskStatusId)->isNotEmpty()
        );

        return $filteredAutEmailSendStatusMatches->merge($filteredAutEmailSendTagMatches);
    }


    private function filterAutomationsTaskRelatedWithAutomationsEmailSend(
        Collection $automationsTask,
        Collection $filteredAutomationsEmailSend
    ): Collection {
        $emailSendTagIds = $filteredAutomationsEmailSend->pluck('triggering_tags_ids')->flatten()->unique();
        $emailSendStatusIds = $filteredAutomationsEmailSend->pluck('triggering_status_ids')->flatten()->unique();
        
        $filteredAutTaskTagMatches = $automationsTask->filter(
            fn ($aut) => collect($aut->tags_ids_to_assign)->intersect($emailSendTagIds)->isNotEmpty()
        );

        $filteredAutTaskStatusMatches = $automationsTask->filter(
            fn ($aut) => collect($aut->status_id_to_assign)->intersect($emailSendStatusIds)->isNotEmpty()
        );
        
        return $filteredAutTaskStatusMatches->merge($filteredAutTaskTagMatches);
    }


    private function filterAutomationsEmailSendRelatedWithAutomationsNewLead(
        Collection $automationsEmailSend,
        Collection $automationsNewLead
    ): Collection {
        $newLeadTagIds = $automationsNewLead->pluck('add_tags_ids')->flatten()->unique();
        $newLeadStatusIds = $automationsNewLead->pluck('status_id_to_assign')->filter()->unique();

        $filteredByTag = $automationsEmailSend->filter(
            fn ($aut) => collect($aut->triggering_tags_ids)->intersect($newLeadTagIds)->isNotEmpty()
        );
        $filteredByStatus = $automationsEmailSend->filter(
            fn ($aut) => collect($aut->triggering_status_ids)->intersect($newLeadStatusIds)->isNotEmpty()
        );
        return $filteredByTag->merge($filteredByStatus)->unique('id');
    }


    private function filterAutomationsNewLeadRelatedWithAutomationsEmailSend(
        Collection $automationsNewLead,
        Collection $filteredAutomationsEmailSend
    ): Collection {
        $emailSendTagIds = $filteredAutomationsEmailSend->pluck('triggering_tags_ids')->flatten()->unique();
        $emailSendStatusIds = $filteredAutomationsEmailSend->pluck('triggering_status_ids')->flatten()->unique();

        $filteredByTag = $automationsNewLead->filter(
            fn ($aut) => collect($aut->add_tags_ids)->intersect($emailSendTagIds)->isNotEmpty()
        );
        $filteredByStatus = $automationsNewLead->filter(
            fn ($aut) => in_array($aut->status_id_to_assign, $emailSendStatusIds->toArray())
        );
        return $filteredByTag->merge($filteredByStatus)->unique('id');
    }


    private function filterAutomationsNewLeadRelatedWithWAutomationsSequence(
        Collection $automationsNewLead,
        Collection $wAutomationsSequence
    ): Collection {
        $wAutomationSequenceTagIds = $wAutomationsSequence->pluck('triggering_tags_ids')->flatten()->unique();
        $wAutomationSequenceStatusIds = $wAutomationsSequence->pluck('triggering_status_ids')->flatten()->unique();

        $filteredByTag = $automationsNewLead->filter(
            fn ($aut) => collect($aut->add_tags_ids)->intersect($wAutomationSequenceTagIds)->isNotEmpty()
        );
        $filteredByStatus = $automationsNewLead->filter(
            fn ($aut) => in_array($aut->status_id_to_assign, $wAutomationSequenceStatusIds->toArray())
        );
        return $filteredByTag->merge($filteredByStatus)->unique('id');
    }


    private function filterWAutomationsSequenceRelatedWithAutomationsTask(
        Collection $wAutomationsSequence,
        Collection $automationsTask
    ): Collection {
        $taskTagIds = $automationsTask->pluck('tags_ids_to_assign')->flatten()->unique();
        $taskStatusId = $automationsTask->pluck('status_id_to_assign')->flatten()->unique();

        $filteredAutTaskTagMatches = $wAutomationsSequence->filter(
            fn ($aut) => collect($aut->triggering_tags_ids)->intersect($taskTagIds)->isNotEmpty()
        );

        $filteredAutTaskStatusMatches = $wAutomationsSequence->filter(
            fn ($aut) => collect($aut->triggering_status_ids)->intersect($taskStatusId)->isNotEmpty()
        );

        return $filteredAutTaskStatusMatches->merge($filteredAutTaskTagMatches);
    }


    private function filterAutomationsTaskRelatedWithWAutomationsSequence(
        Collection $automationsTask,
        Collection $wAutomationsSequence
    ): Collection {
        $wAutomationSequenceTagIds = $wAutomationsSequence->pluck('triggering_tags_ids')->flatten()->unique();
        $wAutomationSequenceStatusIds = $wAutomationsSequence->pluck('triggering_status_ids')->flatten()->unique();

        $filteredAutTaskTagMatches = $automationsTask->filter(
            fn ($aut) => collect($aut->tags_ids_to_assign)->intersect($wAutomationSequenceTagIds)->isNotEmpty()
        );

        $filteredAutTaskStatusMatches = $automationsTask->filter(
            fn ($aut) => collect($aut->status_id_to_assign)->intersect($wAutomationSequenceStatusIds)->isNotEmpty()
        );

        return $filteredAutTaskStatusMatches->merge($filteredAutTaskTagMatches);
    }


    private function filterWAutomationsSequenceRelatedWithAutomationsNewLead(
        Collection $wAutomationsSequence,
        Collection $automationsNewLead
    ): Collection {
        $newLeadTagIds = $automationsNewLead->pluck('add_tags_ids')->flatten()->unique();
        $filteredWAutomationSequence = $wAutomationsSequence->filter(
            fn ($aut) => collect($aut->triggering_tags_ids)->intersect($newLeadTagIds)->isNotEmpty()
        );
        return $filteredWAutomationSequence;
    }


    private function filterAutomationsTaskRelatedWithAutsEmailSendOrWAutsSequence(
        Collection $automationsTask,
        Collection $automationsEmailSend,
        Collection $wAutomationsSequence
    ): Collection {
        $filteredAutTaskRelatedWithWAutomationsSequence = $this->filterAutomationsTaskRelatedWithWAutomationsSequence(
            $automationsTask, $wAutomationsSequence
        );

        $filteredAutTaskRelatedWithAutomationsEmailSend = $this->filterAutomationsTaskRelatedWithAutomationsEmailSend(
            $automationsTask, $automationsEmailSend
        );

        return $filteredAutTaskRelatedWithWAutomationsSequence->merge($filteredAutTaskRelatedWithAutomationsEmailSend);
    }


    private function filterAutomationsNewLeadRelatedWithAutsEmailSendOrWAutsSequence(
        Collection $automationsNewLead,
        Collection $automationsEmailSend,
        Collection $wAutomationsSequence
    ): Collection {

        $filteredAutNewLeadWithWAutSequence = $this->filterAutomationsNewLeadRelatedWithWAutomationsSequence(
            $automationsNewLead, $wAutomationsSequence
        );

        $filteredAutNewLeadWithAutEmailSend = $this->filterAutomationsNewLeadRelatedWithAutomationsEmailSend(
            $automationsNewLead, $automationsEmailSend
        );

        return $filteredAutNewLeadWithWAutSequence->merge($filteredAutNewLeadWithAutEmailSend);
    }

}
