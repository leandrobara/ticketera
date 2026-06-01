<?php

namespace App\Helpers;

use DateTime;
use Exception;
use DateTimeZone;
use App\Models\Client;
use InvalidArgumentException;
use App\Models\AutomationTask;
use App\Models\AutomationNewLead;
use App\Models\AutomationProposal;
use Illuminate\Support\Collection;
use App\Models\AutomationEmailSend;
use App\Models\WAutomationSequence;
use App\Models\WAutomationProposal;
use App\Models\WAutomationSequenceStep;
use App\Models\AutomationEmailSendStep;


class MermaidChartHelper
{

    public function buildAutomationsNewLeadMarkdown(Collection $automationsNewLead, array $opts = []): string
    {
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";

        $avoidSubgraphContent = $opts['avoidFlowchartSubgraphContentString'] ?? false;
        if (!$avoidSubgraphContent) {
            $title = ($opts['showTitle'] ?? false) ? "Automatizaciones de nuevos prospectos" : "&nbsp;";
            $markdown .= "subgraph AutomationNewLead[\"{$title}\"]\n";
        }

        foreach ($automationsNewLead as $automationNewLead) {
            $opts['avoidFlowchartTDString'] = true;
            $markdown .= $this->buildAutomationNewLeadMarkdown($automationNewLead, $opts);
        }

        if (!$avoidSubgraphContent) {
            $markdown .= "end\n";
        }

        return $markdown;
    }


    public function buildAutomationNewLeadMarkdown(
        AutomationNewLead $automationNewLead,
        array $opts = []
    ): string {
        $autId = $automationNewLead->id;
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";
        $markdown .= "subgraph NewLead_{$autId} [\"&nbsp;\"]\n";
        $markdown .= "style NewLead_{$autId} fill:#fff,stroke:#bbb,stroke-width:1px\n";

        $actionsLegendsMap = $this->getAutomationNewLeadActionLegends($automationNewLead);
        $conditionLegends = $this->getAutomationNewLeadConditionLegends($automationNewLead);
            
        $previousConditionNodeId = null;
        foreach ($conditionLegends as $conditionIndex => $conditionLegend) {
            $currentConditionNodeId = "A{$autId}_Cond{$conditionIndex}";
            $markdown .= "{$currentConditionNodeId}[\"{$conditionLegend}\"]\n";
            $markdown .= "style {$currentConditionNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
            if ($previousConditionNodeId) {
                $markdown .= "{$previousConditionNodeId} -- Y --> {$currentConditionNodeId}\n";
            }
            $previousConditionNodeId = $currentConditionNodeId;
        }

        $markdown .= "{$previousConditionNodeId} -- Y --> A{$autId}_Check" .
            "{¿Se cumplen<br>todas las condiciones?}\n"
        ;
        $markdown .= "A{$autId}_Check -- ❌ No --> A{$autId}_NotApply" .
            "[\"No aplicar automatización de nuevo prospecto\"]\n"
        ;
        $markdown .= "style A{$autId}_Check fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
        $markdown .= "style A{$autId}_NotApply fill:#f1f1f1,stroke:#aaa,stroke-width:1px\n";

        $actionIndex = 1;
        foreach ($actionsLegendsMap as $automationAttr => $actionLegend) {
            $actionId = "A{$autId}_Action{$actionIndex}";
            $markdown .= "{$actionId}[\"{$actionLegend}\"]\n";
            $markdown .= "style {$actionId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";

            $connection = ($actionIndex === 1) ?
                "A{$autId}_Check -- ✅ Sí --> {$actionId}\n" :
                "{$previousActionId} --> {$actionId}\n"
            ;
            $markdown .= $connection;

            $actionSnippet = $this->getAutomationNewLeadActionSnippet($automationNewLead, $automationAttr);
            if ($actionSnippet) {
                $snippetId = "A{$autId}_Action{$actionIndex}_Snippet";
                $markdown .= "{$actionId} -.- {$snippetId}[\"{$actionSnippet}\"]\n";
                $markdown .= "style {$snippetId} fill:#fdfd96,stroke:#fbc02d,stroke-width:1px\n";
            }
            $actionIndex++;
            $previousActionId = $actionId;
        }

        $markdown .= "direction TB\n\n";
        $markdown .= "end\n\n";
        $markdown .= "lead[\"👤 Ingresa un nuevo prospecto\"] --> NewLead_{$autId}\n";
        $markdown .= "style lead fill:#e6eeff,stroke: #95a9ff,stroke-width:1px\n";
        return $markdown;
    }


    public function buildAutomationsTaskMarkdown(Collection $automationsTask, array $opts = []): string
    {
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";

        $avoidSubgraphContent = $opts['avoidFlowchartSubgraphContentString'] ?? false;
        if (!$avoidSubgraphContent) {
            $title = ($opts['showTitle'] ?? false) ? "Automatizaciones de tareas" : "&nbsp;";
            $markdown .= "subgraph AutomationTask[\"{$title}\"]\n";
        }

        $orderedAutomationsTask = $this->orderAutomationsTaskByTagsAndStatusCount($automationsTask);

        foreach ($orderedAutomationsTask as $automationTask) {
            $opts['avoidFlowchartTDString'] = true;
            $markdown .= $this->buildAutomationTaskMarkdown($automationTask, $opts);
        }

        if (!$avoidSubgraphContent) {
            $markdown .= "end\n";
        }

        return $markdown;
    }


    public function buildAutomationTaskMarkdown(
        AutomationTask $automationTask,
        array $opts = []
    ): string {
        $autId = $automationTask->id;
        $autName = $automationTask->name;
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";
                
        $markdown .= "subgraph AutomationsTask_{$autId} [\"&nbsp;\"]\n";

        $actionsLegendsMap = $this->getAutomationTaskActionLegends($automationTask);
        $conditionLegends = $this->getAutomationTaskConditionLegends($automationTask);

        $previousConditionNodeId = null;
        foreach ($conditionLegends as $conditionIndex => $conditionLegend) {
            $currentConditionNodeId = "A{$autId}_Cond{$conditionIndex}";
            $markdown .= "{$currentConditionNodeId}[\"{$conditionLegend}\"]\n";
            $markdown .= "style {$currentConditionNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
            if ($previousConditionNodeId) {
                $markdown .= "{$previousConditionNodeId} -- Y --> {$currentConditionNodeId}\n";
            }
            $previousConditionNodeId = $currentConditionNodeId;
        }

        $markdown .= "{$previousConditionNodeId} -- Y --> A{$autId}_Check" .
            "{¿Se cumplen<br>todas las condiciones?}\n"
        ;
        $markdown .= "A{$autId}_Check -- ❌ No --> A{$autId}_NotApply" .
            "[\"No aplicar automatización de tareas\"]\n"
        ;
        $markdown .= "style A{$autId}_Check fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
        $markdown .= "style A{$autId}_NotApply fill:#f1f1f1,stroke:#aaa,stroke-width:1px\n";

        $actionIndex = 1;
        foreach ($actionsLegendsMap as $automationAttr => $actionLegend) {
            $actionId = "A{$autId}_Action{$actionIndex}";
            $markdown .= "{$actionId}[\"{$actionLegend}\"]\n";
            $markdown .= "style {$actionId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";

            $connection = ($actionIndex === 1) ?
                "A{$autId}_Check -- ✅ Sí --> {$actionId}\n" :
                "{$previousActionId} --> {$actionId}\n"
            ;
            $markdown .= $connection;

            $actionIndex++;
            $previousActionId = $actionId;
        }
        
        $markdown .= "direction TB\n\n";
        $markdown .= "end\n\n";
        $markdown .= "style AutomationsTask_{$autId} fill:#fff,stroke:#bbb,stroke-width:1px\n";
        return $markdown;
    }


    public function buildAutomationsEmailSendMarkdown(Collection $automationsEmailSend, array $opts = []): string
    {
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";
        
        $avoidSubgraphContent = $opts['avoidFlowchartSubgraphContentString'] ?? false;
        if (!$avoidSubgraphContent) {
            $title = ($opts['showTitle'] ?? false) ? "Automatizaciones de secuencia de emails" : "&nbsp;";
            $markdown .= "subgraph AutomationEmailSequence[\"{$title}\"]\n";
        }

        $orderedAutomationsEmailSend = $this->orderAutomationsEmailSendByStepCount($automationsEmailSend);

        foreach ($orderedAutomationsEmailSend as $automationEmailSend) {
            $opts['avoidFlowchartTDString'] = true;
            $markdown .= $this->buildAutomationEmailSendMarkdown($automationEmailSend, $opts);
        }

        if (!$avoidSubgraphContent) {
            $markdown .= "end\n";
        }

        return $markdown;
    }


    public function buildAutomationEmailSendMarkdown(
        AutomationEmailSend $automationEmailSend,
        array $opts = []
    ): string {
        $autId = $automationEmailSend->id;
        $autName = $automationEmailSend->name;
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";
                
        $markdown .= "subgraph AutomationsEmailSend_{$autId} [\"&nbsp;\"]\n";

        $stepLegends = $this->getAutomationEmailSendStepsLegends($automationEmailSend);
        $conditionLegends = $this->getAutomationEmailSendConditionLegends($automationEmailSend);

        $previousConditionNodeId = null;
        foreach ($conditionLegends as $conditionIndex => $conditionLegend) {
            $currentConditionNodeId = "A{$autId}_Cond{$conditionIndex}";
            $markdown .= "{$currentConditionNodeId}[\"{$conditionLegend}\"]\n";
            $markdown .= "style {$currentConditionNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
            if ($previousConditionNodeId) {
                $markdown .= "{$previousConditionNodeId} -- Y --> {$currentConditionNodeId}\n";
            }
            $previousConditionNodeId = $currentConditionNodeId;
        }

        $markdown .= "{$previousConditionNodeId} -- Y --> A{$autId}_Check{¿Se cumplen<br>todas las condiciones?}\n";
        $markdown .= "A{$autId}_Check -- ❌ No --> A{$autId}_NotApply[\"No enviar secuencia de emails\"]\n";
        $markdown .= "A{$autId}_Check -- ✅ Sí --> A{$autId}_StartSeq[\"✉️ Enviar secuencia de emails\"]\n";

        $subgraphEmailSeqLegend = $stepLegends
            ? "Emails de la secuencia: {$autName}"
            : "Sin emails en la secuencia personalizada"
        ;
        $markdown .= "subgraph EmailSeq_{$autId}[\"&nbsp;&nbsp;$subgraphEmailSeqLegend&nbsp;&nbsp;\"]\ndirection TB\n";

        // Email Sequence
        $previousStepNodeId = "";
        foreach ($stepLegends as $stepLegendIndex => $stepLegend) {
            $currentStepNodeId = "AP{$autId}_Step{$stepLegendIndex}";
            $markdown .= "{$currentStepNodeId}[\"{$stepLegend}\"]\n";
            if ($previousStepNodeId) {
                $markdown .= "{$previousStepNodeId} -- 🔁 --> {$currentStepNodeId}\n";
            }
            $previousStepNodeId = $currentStepNodeId;
        }
        $markdown .= "end\n\n";

        $stepSnippets = $this->getAutomationEmailSendStepSnippet($automationEmailSend);
        foreach ($stepSnippets as $key => $snippetLegend) {
            $doNotSendWeekendsLegend = "No enviar emails fines de semana.\nSe enviará el siguiente lunes";
            $markdown .= "EmailSeq_{$autId} -.- EmailSeqNote_{$autId}_{$key}([\"$snippetLegend\"])\n";
            $markdown .= "style EmailSeqNote_{$autId}_{$key} fill:#fdfd96,stroke:#fbC02d,stroke-width:1px\n";
        }
        
        $markdown .= "A{$autId}_StartSeq -.-> EmailSeq_{$autId}\n";
        $markdown .= "style A{$autId}_Check fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
        $markdown .= "style A{$autId}_NotApply fill:#f1f1f1,stroke:#aaa,stroke-width:1px\n";
        $markdown .= "style A{$autId}_StartSeq fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
        $markdown .= "style AutomationsEmailSend_{$autId} fill:#fff,stroke:#bbb,stroke-width:1px\n";

        $markdown .= "direction TB\n\n";
        $markdown .= "end\n\n";
        $markdown .= "style EmailSeq_{$autId} fill:#f3f2f7,stroke:#333333,stroke-width:1px\n";
        
        return $markdown;
    }


    public function buildWAutomationsSequenceMarkdown(Collection $wAutomationsSequence, array $opts = []): string
    {
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";
        
        $avoidSubgraphContent = $opts['avoidFlowchartSubgraphContentString'] ?? false;
        if (!$avoidSubgraphContent) {
            $title = ($opts['showTitle'] ?? false) ? "Automatizaciones de secuencia de WhatsaApp" : "&nbsp;";
            $markdown .= "subgraph WAutomationSequence[\"$title\"]\n";
        }

        $orderedWAutomationsSequence = $this->orderWAutomationsSequenceByStepCount($wAutomationsSequence);

        foreach ($orderedWAutomationsSequence as $wAutomationSequence) {
            $opts = ['avoidFlowchartTDString' => true];
            $markdown .= $this->buildWAutomationSequenceMarkdown($wAutomationSequence, $opts);
        }

        if (!$avoidSubgraphContent) {
            $markdown .= "end\n";
        }

        return $markdown;
    }


    public function buildWAutomationSequenceMarkdown(
        WAutomationSequence $wAutomationSequence,
        array $opts = []
    ): string {
        $autId = $wAutomationSequence->id;
        $autName = $wAutomationSequence->name;
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? '' : "flowchart TD;\n";
        
        $markdown .= "subgraph WhatsAppSequence{$autId} [\"&nbsp;\"]\n";

        $stepLegends = $this->getWAutomationSequenceStepsLegends($wAutomationSequence);
        $conditionLegends = $this->getWAutomationSequenceConditionLegends($wAutomationSequence);
        $stepActionsAfterSend = $this->getWAutomationSequenceStepActionsAfterSendLegends($wAutomationSequence);

        $previousConditionNodeId = null;
        foreach ($conditionLegends as $conditionIndex => $conditionLegend) {
            $currentConditionNodeId = "A{$autId}_Cond{$conditionIndex}";
            $markdown .= "{$currentConditionNodeId}[\"{$conditionLegend}\"]\n";
            $markdown .= "style {$currentConditionNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
            if ($previousConditionNodeId) {
                $markdown .= "{$previousConditionNodeId} -- Y --> {$currentConditionNodeId}\n";
            }
            $previousConditionNodeId = $currentConditionNodeId;
        }

        $markdown .= "{$previousConditionNodeId} -- Y --> A{$autId}_Check{¿Se cumplen<br>todas las condiciones?}\n";
        $markdown .= "A{$autId}_Check -- ❌ No --> A{$autId}_NotApply[\"No enviar secuencia de WhatsApp\"]\n";
        $markdown .= "A{$autId}_Check -- ✅ Sí --> A{$autId}_StartSeq[\"📲 Enviar secuencia de WhatsApp\"]\n";

        $subgraphWAutomSeqLegend = $stepLegends
            ? "Mensajes de la secuencia{$autName}"
            : "Sin mensajes en la secuencia personalizada"
        ;
        $markdown .= "subgraph WAutSeq_{$autId}[\"&nbsp;&nbsp;$subgraphWAutomSeqLegend&nbsp;&nbsp;\"]\ndirection TB\n";

        // Sequence
        $previousStepNodeId = "";
        foreach ($stepLegends as $stepId => $stepLegend) {
            $currentStepNodeId = "AP{$autId}_Step{$stepId}";
            $markdown .= "{$currentStepNodeId}[\"{$stepLegend}\"]\n";
            if ($previousStepNodeId) {
                $markdown .= "{$previousStepNodeId} -- 🔁 --> {$currentStepNodeId}\n";
            }
            $previousStepNodeId = $currentStepNodeId;

            // Sequence actions after send
            if ($stepActionsAfterSend[$stepId] ?? null) {
                $subExtraStepsLegend = "Acciones luego de enviar el mensaje";
                $markdown .= "subgraph Sub_ExtraSteps_{$stepId}[\"&nbsp;&nbsp;{$subExtraStepsLegend}&nbsp;&nbsp;\"]\n";

                $previousAfterSendNodeId = "";
                foreach ($stepActionsAfterSend[$stepId] as $index => $actionLegend) {
                    $currentAfterSendNodeId = "AP{$autId}_ExtraStep_{$stepId}_{$index}";
                    $markdown .= "style {$currentAfterSendNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
                    $markdown .= "{$currentAfterSendNodeId}[\"{$actionLegend}\"]\n";

                    if ($previousAfterSendNodeId) {
                        $markdown .= "{$previousAfterSendNodeId} --> {$currentAfterSendNodeId}\n";
                    }
                    $previousAfterSendNodeId = $currentAfterSendNodeId;
                }
                $markdown .= "direction TB\n\n";
                $markdown .= "end\n";
                $markdown .= "{$currentStepNodeId} -.-> Sub_ExtraSteps_{$stepId}\n";
                $markdown .= "style Sub_ExtraSteps_{$stepId} fill:#e3f2fd,stroke:#1565c0,stroke-width:1px\n";
            }
        }

        $markdown .= "end\n\n";

        $stepSnippets = $this->getWAutomationSequenceSnippet($wAutomationSequence);
        foreach ($stepSnippets as $key => $snippetLegend) {
            $doNotSendWeekendsLegend = "No enviar mensajes fines de semana.\nSe enviará el siguiente lunes";
            $markdown .= "WAutSeq_{$autId} -.- WAutSeqNote_{$autId}_{$key}([\"$snippetLegend\"])\n";
            $markdown .= "style WAutSeqNote_{$autId}_{$key} fill:#fdfd96,stroke:#fbc02d,stroke-width:1px\n";
        }
        
        $markdown .= "A{$autId}_StartSeq -.-> WAutSeq_{$autId}\n";
        $markdown .= "style A{$autId}_Check fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
        $markdown .= "style A{$autId}_NotApply fill:#f1f1f1,stroke:#aaa,stroke-width:1px\n";
        $markdown .= "style A{$autId}_StartSeq fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
        $markdown .= "style WhatsAppSequence{$autId} fill:#fff,stroke:#bbb,stroke-width:1px\n";

        $markdown .= "direction TB\n\n";
        $markdown .= "end\n\n";
        $markdown .= "style WAutSeq_{$autId} fill:#f3f2f7,stroke:#333333,stroke-width:1px\n";
                
        return $markdown;
    }


    public function buildConnectionsBetweenTaskTagsAndEmailSend(
        Collection $automationsTask,
        Collection $automationsEmailSend
    ): string {
        $markdown = '';
    
        foreach ($automationsTask as $automationTask) {
            if ($automationTask->tagsToAssign->isEmpty()) {
                continue;
            }
    
            foreach ($automationTask->tagsToAssign as $tag) {
                $automationTaskTagId = $tag->id;
    
                foreach ($automationsEmailSend as $automationEmailSend) {
                    if ($automationEmailSend->triggeringTags->isEmpty()) {
                        continue;
                    }
    
                    $emailSendTriggeringTagIds = $automationEmailSend->triggeringTags->pluck('id')->toArray();
    
                    if (!in_array($automationTaskTagId, $emailSendTriggeringTagIds)) {
                        continue;
                    }
    
                    // Crear un nodo intermedio único por cada Task + Tag + EmailSend
                    $relationNodeId = "TagNode_{$automationTask->id}_{$automationTaskTagId}_{$automationEmailSend->id}";
                    $tagFormatted = $this->getTagsOrStatusMarkdown(collect([$tag]));
    
                    $markdown .= "{$relationNodeId}[\"{$tagFormatted}\"]\n";
                    $markdown .= "style {$relationNodeId} fill:#ffffff,stroke:#ffffff,stroke-width:1px\n";

                    // Conectar cada Task con su propio nodo intermedio
                    $markdown .= "AutomationsTask_{$automationTask->id} --- {$relationNodeId}\n";

                    // Conectar el nodo intermedio con la automatización de email correspondiente
                    $markdown .= "{$relationNodeId} --> AutomationsEmailSend_{$automationEmailSend->id}\n";
                }
            }
        }
    
        return $markdown;
    }


    public function buildConnectionsBetweenTaskStatusAndEmailSend(
        Collection $automationsTask,
        Collection $automationsEmailSend
    ): string {
        $markdown = '';
    
        foreach ($automationsTask as $automationTask) {
            if (!$automationTask->statusToAssign) {
                continue;
            }
    
            $automationTaskStatusId = $automationTask->statusToAssign->id;
    
            foreach ($automationsEmailSend as $automationEmailSend) {
                if ($automationEmailSend->triggeringStatus->isEmpty()) {
                    continue;
                }

                $automationEmailSendId = $automationEmailSend->id;
    
                $emailSendTriggeringStatusIds = $automationEmailSend->triggeringStatus->pluck('id')->toArray();
    
                if (!in_array($automationTaskStatusId, $emailSendTriggeringStatusIds)) {
                    continue;
                }
    
                // Crear un nodo intermedio único por cada Task + Status + EmailSend
                $relationNodeId = "StatusNode_{$automationTask->id}_{$automationTaskStatusId}_{$automationEmailSendId}";
                $statusFormatted = $this->getTagsOrStatusMarkdown(collect([$automationTask->statusToAssign]));
    
                $markdown .= "{$relationNodeId}[\"{$statusFormatted}\"]\n";
                $markdown .= "style {$relationNodeId} fill:#ffffff,stroke:#ffffff,stroke-width:1px\n";
    
                // Conectar cada Task con su propio nodo intermedio
                $markdown .= "AutomationsTask_{$automationTask->id} --- {$relationNodeId}\n";
    
                // Conectar el nodo intermedio con la automatización de email correspondiente
                $markdown .= "{$relationNodeId} --> AutomationsEmailSend_{$automationEmailSendId}\n";
            }
        }
    
        return $markdown;
    }


    public function buildConnectionsBetweenTaskTagsAndWAutomationsSequence(
        Collection $automationsTask,
        Collection $wAutomationsSequence
    ): string {
        $markdown = '';
    
        foreach ($automationsTask as $automationTask) {
            if ($automationTask->tagsToAssign->isEmpty()) {
                continue;
            }
    
            foreach ($automationTask->tagsToAssign as $tag) {
                $automationTaskTagId = $tag->id;
    
                foreach ($wAutomationsSequence as $wAutomationSequence) {
                    if ($wAutomationSequence->triggeringTags->isEmpty()) {
                        continue;
                    }
    
                    $wAutomationSequenceTriggeringTagIds = $wAutomationSequence->triggeringTags->pluck('id')->toArray();
    
                    if (!in_array($automationTaskTagId, $wAutomationSequenceTriggeringTagIds)) {
                        continue;
                    }
    
                    // Crear un nodo intermedio único por cada Task + Tag + EmailSend
                    $relationNodeId = "TagNode_{$automationTask->id}_{$automationTaskTagId}_{$wAutomationSequence->id}";
                    $tagFormatted = $this->getTagsOrStatusMarkdown(collect([$tag]));
    
                    $markdown .= "{$relationNodeId}[\"{$tagFormatted}\"]\n";
                    $markdown .= "style {$relationNodeId} fill:#ffffff,stroke:#ffffff,stroke-width:1px\n";

                    // Conectar cada Task con su propio nodo intermedio
                    $markdown .= "AutomationsTask_{$automationTask->id} --- {$relationNodeId}\n";

                    // Conectar el nodo intermedio con la automatización de email correspondiente
                    $markdown .= "{$relationNodeId} --> WhatsAppSequence{$wAutomationSequence->id}\n";
                }
            }
        }
    
        return $markdown;
    }


    public function buildConnectionsBetweenTaskStatusAndWAutomationsSequence(
        Collection $automationsTask,
        Collection $wAutomationsSequence
    ): string {
        $markdown = '';
    
        foreach ($automationsTask as $automationTask) {
            if (!$automationTask->statusToAssign) {
                continue;
            }

            $automationTaskStatusId = $automationTask->statusToAssign->id;
    
            foreach ($wAutomationsSequence as $wAutomationSequence) {
                if ($wAutomationSequence->triggeringStatus->isEmpty()) {
                    continue;
                }

                $wAutomationSequenceId = $wAutomationSequence->id;

                $wAutomationSequenceTriggeringStatusIds = $wAutomationSequence->triggeringStatus
                    ->pluck('id')
                    ->toArray()
                ;
    
                if (!in_array($automationTaskStatusId, $wAutomationSequenceTriggeringStatusIds)) {
                    continue;
                }
    
                // Crear un nodo intermedio único por cada Task + Status + EmailSend
                $relationNodeId = "StatusNode_{$automationTask->id}_{$automationTaskStatusId}_{$wAutomationSequenceId}";
                $statusFormatted = $this->getTagsOrStatusMarkdown(collect([$automationTask->statusToAssign]));
    
                $markdown .= "{$relationNodeId}[\"{$statusFormatted}\"]\n";
                $markdown .= "style {$relationNodeId} fill:#ffffff,stroke:#ffffff,stroke-width:1px\n";
    
                // Conectar cada Task con su propio nodo intermedio
                $markdown .= "AutomationsTask_{$automationTask->id} --- {$relationNodeId}\n";
    
                // Conectar el nodo intermedio con la automatización de email correspondiente
                $markdown .= "{$relationNodeId} --> WhatsAppSequence{$wAutomationSequenceId}\n";
            }
        }
    
        return $markdown;
    }


    public function buildConnectionsBetweenNewLeadAndEmailSend(
        Collection $automationsNewLead,
        Collection $automationsEmailSend
    ): string {
        $markdown = '';

        foreach ($automationsNewLead as $automationNewLead) {
            // Conexiones por etiquetas
            if ($automationNewLead->tagsToAdd->isNotEmpty()) {
                foreach ($automationNewLead->tagsToAdd as $tag) {
                    $tagId = $tag->id;

                    foreach ($automationsEmailSend as $automationEmailSend) {
                        if ($automationEmailSend->triggeringTags->isEmpty()) {
                            continue;
                        }

                        $emailSendTriggeringTagIds = $automationEmailSend->triggeringTags->pluck('id')->toArray();

                        if (!in_array($tagId, $emailSendTriggeringTagIds)) {
                            continue;
                        }

                        $relationNodeId = "TagNode_{$automationNewLead->id}_{$tagId}_{$automationEmailSend->id}";
                        $tagFormatted = $this->getTagsOrStatusMarkdown(collect([$tag]));

                        $markdown .= "{$relationNodeId}[\"{$tagFormatted}\"]\n";
                        $markdown .= "style {$relationNodeId} fill:#ffffff,stroke:#ffffff,stroke-width:1px\n";
                        $markdown .= "NewLead_{$automationNewLead->id} --- {$relationNodeId}\n";
                        $markdown .= "{$relationNodeId} --> AutomationsEmailSend_{$automationEmailSend->id}\n";
                    }
                }
            }

            // Conexiones por estado asignado
            if ($automationNewLead->statusToAssign) {
                $status = $automationNewLead->statusToAssign;
                $statusId = $status->id;
                $statusFormatted = $this->getTagsOrStatusMarkdown(collect([$status]));

                foreach ($automationsEmailSend as $automationEmailSend) {
                    if ($automationEmailSend->triggeringStatus->isEmpty()) {
                        continue;
                    }

                    $emailSendTriggeringStatusIds = $automationEmailSend->triggeringStatus->pluck('id')->toArray();
                    if (!in_array($statusId, $emailSendTriggeringStatusIds)) {
                        continue;
                    }

                    $relationNodeId = "StatusNode_{$automationNewLead->id}_{$statusId}_{$automationEmailSend->id}";

                    $markdown .= "{$relationNodeId}[\"{$statusFormatted}\"]\n";
                    $markdown .= "style {$relationNodeId} fill:#ffffff,stroke:#ffffff,stroke-width:1px\n";
                    $markdown .= "NewLead_{$automationNewLead->id} --- {$relationNodeId}\n";
                    $markdown .= "{$relationNodeId} --> AutomationsEmailSend_{$automationEmailSend->id}\n";
                }
            }
        }

        return $markdown;
    }


    public function buildConnectionsBetweenNewLeadAndWAutomationsSequence(
        Collection $automationsNewLead,
        Collection $wAutomationsSequence
    ): string {
        $markdown = '';

        foreach ($automationsNewLead as $automationNewLead) {
            // Conexiones por etiquetas
            if ($automationNewLead->tagsToAdd->isNotEmpty()) {
                foreach ($automationNewLead->tagsToAdd as $tag) {
                    $tagId = $tag->id;

                    foreach ($wAutomationsSequence as $wAutomationSequence) {
                        if ($wAutomationSequence->triggeringTags->isEmpty()) {
                            continue;
                        }

                        $triggeringTagIds = $wAutomationSequence->triggeringTags->pluck('id')->toArray();

                        if (!in_array($tagId, $triggeringTagIds)) {
                            continue;
                        }

                        $relationNodeId = "TagNode_{$automationNewLead->id}_{$tagId}_{$wAutomationSequence->id}";
                        $tagFormatted = $this->getTagsOrStatusMarkdown(collect([$tag]));

                        $markdown .= "{$relationNodeId}[\"{$tagFormatted}\"]\n";
                        $markdown .= "style {$relationNodeId} fill:#ffffff,stroke:#ffffff,stroke-width:1px\n";

                        $markdown .= "NewLead_{$automationNewLead->id} --- {$relationNodeId}\n";
                        $markdown .= "{$relationNodeId} --> WhatsAppSequence{$wAutomationSequence->id}\n";
                    }
                }
            }

            // Conexiones por estado
            if ($automationNewLead->statusToAssign) {
                $status = $automationNewLead->statusToAssign;
                $statusId = $status->id;
                $statusFormatted = $this->getTagsOrStatusMarkdown(collect([$status]));

                foreach ($wAutomationsSequence as $wAutomationSequence) {
                    if ($wAutomationSequence->triggeringStatus->isEmpty()) {
                        continue;
                    }

                    $triggeringStatusIds = $wAutomationSequence->triggeringStatus->pluck('id')->toArray();

                    if (!in_array($statusId, $triggeringStatusIds)) {
                        continue;
                    }

                    $relationNodeId = "StatusNode_{$automationNewLead->id}_{$statusId}_{$wAutomationSequence->id}";

                    $markdown .= "{$relationNodeId}[\"{$statusFormatted}\"]\n";
                    $markdown .= "style {$relationNodeId} fill:#ffffff,stroke:#ffffff,stroke-width:1px\n";
                    $markdown .= "NewLead_{$automationNewLead->id} --- {$relationNodeId}\n";
                    $markdown .= "{$relationNodeId} --> WhatsAppSequence{$wAutomationSequence->id}\n";
                }
            }
        }

        return $markdown;
    }
 

    public function buildEmptyNode(string $message): string
    {
        $markdown = "flowchart TD;\n";
        $markdown .= "emptyNode[\"{$message}\"]\n";
        $markdown .= "style emptyNode fill:#fdfd96,stroke:#fbc02d,stroke-width:1px\n";
        return $markdown;
    }


    private function getAutomationNewLeadActionSnippet(
        AutomationNewLead $automation,
        string $automationAttrName
    ): ?string {
        if ($automationAttrName == 'send_grouped_email' && $automation->$automationAttrName) {
            $groupedEmailSubject = $this->sanitizeStr($automation->grouped_email_subject);
            $groupedEmailSubject = $this->breakLongStr($groupedEmailSubject, 50);
            $groupedEmailSubject = $this->shortenStr($groupedEmailSubject, 150);
            return "<b>Asunto del email:</b>\n{$groupedEmailSubject}";
        }
        if ($automationAttrName == 'send_grouped_whatsapp_message' && $automation->$automationAttrName) {
            $msgText = $this->sanitizeStr($automation->grouped_whatsapp_message_text);
            $msgText = $this->breakLongStr($msgText, 50);
            $msgText = $this->shortenStr($msgText, 150);
            return "<b>Mensaje de WhatsApp:</b>\n{$msgText}";
        }
        if ($automationAttrName == 'auto_reply_email_template_id' && $automation->$automationAttrName) {
            $templateTitle = $this->sanitizeStr($automation->autoReplyEmailTemplate->title);
            $templateTitle = $this->breakLongStr($templateTitle, 50);
            $templateTitle = $this->shortenStr($templateTitle, 150);
            return "<b>Plantilla de email:</b>\n{$templateTitle}";
        }
        if ($automationAttrName == 'auto_reply_ask_phone_email_template_id' && $automation->$automationAttrName) {
            $templateTitle = $this->sanitizeStr($automation->askPhoneEmailTemplate->title);
            $templateTitle = $this->breakLongStr($templateTitle, 50);
            $templateTitle = $this->shortenStr($templateTitle, 150);
            return "<b>Plantilla de email:</b>\n{$templateTitle}";
        }
        return null;
    }


    private function getAutomationEmailSendStepSnippet(AutomationEmailSend $automation): array
    {
        $snipet = [];
        $hasAutomationEmailSendSteps = $automation->automationEmailSendSteps->isEmpty();
        if ($hasAutomationEmailSendSteps) {
            return $snipet;
        }
        if ($automation->do_not_send_weekends) {
            $snipet[] = "No enviar emails fines de semana.\nSe enviará el siguiente lunes";
        }
        if ($automation->cancel_if_sequence_was_sent) {
            $snipet[] = "Cancelar secuencia si ya fue enviada en el pasado";
        }
        return $snipet;
    }


    private function getWAutomationSequenceSnippet(WAutomationSequence $automation): array
    {
        $snipet = [];
        $hasWAutomationSequenceSteps = $automation->wAutomationSequenceSteps->isEmpty();
        if ($hasWAutomationSequenceSteps) {
            return $snipet;
        }
        if ($automation->do_not_send_weekends) {
            $snipet[] = "No enviar mensajes fines de semana.\nSe enviará el siguiente lunes";
        }
        if ($automation->cancel_if_sequence_was_sent) {
            $snipet[] = "Cancelar secuencia si ya fue enviada en el pasado";
        }
        return $snipet;
    }


    private function getAutomationNewLeadActionLegends(AutomationNewLead $automation): array
    {
        $actions = [];

        $tagsToAdd = $automation->tagsToAdd;
        $addNewNote = $automation->add_new_note;
        $addNewTask = $automation->add_new_task;
        $usersToAssign = $automation->usersToAssign;
        $assignQuality = $automation->assign_quality;
        $doNotSendEmail = $automation->do_not_send_email;
        $sendGroupedEmail = $automation->send_grouped_email;
        $leadCustomFieldsMapping = $automation->leadCustomFieldsMapping;
        $acquisitionChannelToAdd = $automation->acquisitionChannelToAdd;
        $statusToAssign = $automation->statusToAssign ?? null;
        $autoReplyEmailTemplateId = $automation->auto_reply_email_template_id;
        $doNotSendWhatsappMessage = $automation->do_not_send_whatsapp_message;
        $sendGroupedWhatsappMessage = $automation->send_grouped_whatsapp_message;
        $autoReplyAskPhoneEmailTemplateId = $automation->auto_reply_ask_phone_email_template_id;

        if ($usersToAssign->isNotEmpty()) {
            $usersToAssignCount = $usersToAssign->count();

            if ($usersToAssignCount === 1) {
                $user = $usersToAssign->first();
                $name = $user->name ?? '';
                $lastName = $user->last_name ?? '';
                $actions['usersToAssign'] = "👤 Asignar a <b>{$name} {$lastName}</b>";
            } elseif ($usersToAssignCount > 1) {
                $actions['usersToAssign'] = "👤 Asignar a 1 entre <b>{$usersToAssignCount} usuarios</b>";
            }
        }

        if ($tagsToAdd->isNotEmpty()) {
            $tags = $this->getTagsOrStatusMarkdown($tagsToAdd);
            $actions['tagsToAdd'] = "Agregar <b>etiquetas</b>:\n\n{$tags}";
        }

        if ($assignQuality !== null) {
            $qualityLabels = [
                0 => "Se asignó la calidad baja de 0 estrella",
                1 => "Se asignó la calidad baja de ⭐ estrella",
                2 => "Se asignó la calidad media de ⭐⭐ estrellas",
                3 => "Se asignó la calidad alta de ⭐⭐⭐ estrellas"
            ];
            $actions['assign_quality'] = $qualityLabels[$assignQuality];
        }

        if ($acquisitionChannelToAdd) {
            $actions['acquisitionChannelToAdd'] = "Asignar el canal de adquisición: {$acquisitionChannelToAdd->name}";
        }

        if ($statusToAssign) {
            $status = $this->getTagsOrStatusMarkdown(collect([$statusToAssign]));
            $actions['statusToAssign'] = "Asignar el siguiente <b>estado</b>:\n\n{$status}";
        }

        if ($addNewNote) {
            $actions['add_new_note'] = "Asignar una nota";
        }

        if ($addNewTask) {
            $actions['add_new_task'] = "Asignar una tarea";
        }

        if ($leadCustomFieldsMapping->isNotEmpty()) {
            $actions['leadCustomFieldsMapping'] = "Mapear campos de formulario con campos personalizados";
        }

        $actions['send_grouped_email'] = $sendGroupedEmail
            ? "✉️ Enviar un email con este tipo\nde prospectos agrupados a las 18 hs."
            : (!$doNotSendEmail ? "✉️ Enviar un email notificando al usuario" : null);

        // $actions['send_grouped_whatsapp_message'] = $sendGroupedWhatsappMessage
        //     ? "📲 Enviar un mensaje de WhatsApp\ncon este tipo de prospectos agrupados a las 18 hs."
        //     : (!$doNotSendWhatsappMessage ? "📲 Enviar un mensaje de WhatsApp notificando al usuario" : null);

        if ($autoReplyAskPhoneEmailTemplateId) {
            $actions['auto_reply_ask_phone_email_template_id'] = "✉️ Enviar email si el prospecto no dejó un teléfono";
        }

        if ($autoReplyEmailTemplateId) {
            $actions['auto_reply_email_template_id'] = "✉️ Enviar email de auto respuesta";
        }

        return array_filter($actions);
    }


    private function getAutomationTaskActionLegends(AutomationTask $automation): array
    {
        $actions = [];

        if ($automation->trigger_type == 'after_task_expiration') {
            $actions = $this->getAutomationTaskAfterExpirationActionLegends($automation);
        }
        if ($automation->trigger_type == 'after_sale' ||
            $automation->trigger_type == 'after_tag_change' ||
            $automation->trigger_type == 'after_status_change'
        ) {
            $actions = $this->getAutomationTaskAfterEventActionLegends($automation);
        }

        return $actions;
    }


    private function getAutomationTaskAfterEventActionLegends(AutomationTask $automation): array
    {
        $actions = [];

        $createLegend = $this->getTaskTemplateCreateLegend($automation);
        $limitDateLegend = $this->getTaskTemplateLimitDateLegend($automation);
        
        $taskTemplateTitle = $automation->taskTemplate->title;
        $taskTemplateTitle = $this->sanitizeStr($taskTemplateTitle);
        $taskTemplateTitle = $this->breakLongStr($taskTemplateTitle, 60);

        $taskTemplateName = $automation->taskTemplate->template_name;
        $taskTemplateName = $this->sanitizeStr($taskTemplateName);
        $taskTemplateName = $this->breakLongStr($taskTemplateName, 60);

        $taskTemplateDescription = $automation->taskTemplate->description;
        $taskTemplateDescription = $this->sanitizeStr($taskTemplateDescription);
        $taskTemplateDescription = $this->breakLongStr($taskTemplateDescription, 60);

        $actionLegend = "{$createLegend}\n";
        $actionLegend .= "Plantilla de tarea: <b>{$taskTemplateName}</b>\n";
        $actionLegend .= "Título de tarea: <b>{$taskTemplateTitle}</b>\n";
        $actionLegend .= "Vence: <b>{$limitDateLegend}</b>\n";
        $actionLegend .= "Descripción: {$taskTemplateDescription}";

        $actions[] = $actionLegend;

        return $actions;
    }


    private function getAutomationTaskAfterExpirationActionLegends(AutomationTask $automation): array
    {
        $actions = [];

        if ($automation->tagsToAssign->isNotEmpty()) {
            $tags = $this->getTagsOrStatusMarkdown($automation->tagsToAssign);
            $actions[] = "Se asignarán las siguientes <b>etiquetas</b>\nal prospecto asociado:\n\n{$tags}";
        }
        if ($automation->statusToAssign) {
            $status = $this->getTagsOrStatusMarkdown(collect([$automation->statusToAssign]));
            $actions[] = "Se asignará el siguiente <b>estado</b>\nal prospecto asociado:\n\n{$status}";
        }

        return $actions;
    }


    private function getAutomationProposalActionLegends(AutomationProposal $automation): array
    {
        $actions = [];

        $interactionRule = $automation->interactionRule;
        $modifyLeadAfterSendRule = $automation->modifyLeadAfterSendRule;
        $resendRule = $automation->resendRule;

        // Acciones cuando se abre el presupuesto (interactionRule)
        if ($interactionRule) {
            if ($interactionRule->add_opened_proposal_tag ?? false) {
                $tags = $this->getTagsOrStatusMarkdown($interactionRule->tagsToAdd);
                $actions['interaction_add_opened_tag'] = "Si el prospecto abre el presupuesto,\n" .
                    "asignarle automáticamente las <b>etiquetas</b>:\n\n{$tags}"
                ;
            } elseif ($interactionRule->tagsToAdd->isNotEmpty()) {
                $tags = $this->getTagsOrStatusMarkdown($interactionRule->tagsToAdd);
                $actions['interaction_add_tags'] = "Si el prospecto abre el presupuesto,\n" .
                    "asignarle automáticamente las <b>etiquetas</b>:\n\n{$tags}"
                ;
            }

            if ($interactionRule->statusToAssign) {
                $status = $this->getTagsOrStatusMarkdown(collect([$interactionRule->statusToAssign]));
                $actions['interaction_assign_status'] = "Si el prospecto abre el presupuesto,\n" .
                    "asignarle automáticamente el siguiente <b>estado</b>:\n\n{$status}"
                ;
            }

            if ($interactionRule->send_notification_email_to_user) {
                $qualityCondition = "";
                if ($interactionRule->notify_only_if_lead_quality_is_gt === 1) {
                    $qualityCondition = "\n<b>solo si la calidad del prospecto tiene 2 estrellas o más</b>";
                }
                if ($interactionRule->notify_only_if_lead_quality_is_gt === 2) {
                    $qualityCondition = "\n<b>solo si la calidad del prospecto es de 3 estrellas</b>";
                }
                $actions['interaction_send_notification'] = "Si el prospecto abre el presupuesto,\n" .
                    "enviar una notificación vía email al usuario asignado {$qualityCondition}"
                ;
            }
        }

        // Acciones cuando se envía el presupuesto original (modifyLeadAfterSendRule)
        if ($modifyLeadAfterSendRule) {
            if ($modifyLeadAfterSendRule->add_sent_proposal_tag ?? false) {
                $tags = $this->getTagsOrStatusMarkdown($modifyLeadAfterSendRule->tagsToAdd);
                $actions['modify_add_sent_tag'] = "Al enviar el presupuesto original al prospecto,\n" .
                    "asignarle automáticamente las <b>etiquetas</b>:\n\n{$tags}"
                ;
            } elseif ($modifyLeadAfterSendRule->tagsToAdd->isNotEmpty()) {
                $tags = $this->getTagsOrStatusMarkdown($modifyLeadAfterSendRule->tagsToAdd);
                $actions['modify_add_tags'] = "Al enviar el presupuesto original al prospecto,\n" .
                    "asignarle automáticamente las <b>etiquetas</b>:\n\n{$tags}"
                ;
            }

            if ($modifyLeadAfterSendRule->statusToAssign) {
                $status = $this->getTagsOrStatusMarkdown(collect([$modifyLeadAfterSendRule->statusToAssign]));
                $actions['modify_assign_status'] = "Al enviar el presupuesto original al prospecto,\n" .
                    "asignarle automáticamente el siguiente <b>estado</b>:\n\n{$status}"
                ;
            }
        }

        // Reenvío de presupuesto (resendRule)
        if ($resendRule && $resendRule->enabled) {
            $resendActions = $this->getAutomationProposalResendActionLegends($resendRule, $automation->client);
            foreach ($resendActions as $key => $action) {
                $actions["resend_{$key}"] = $action;
            }
        }

        return array_filter($actions);
    }


    private function getAutomationProposalResendActionLegends($resendRule, ?Client $client = null): array
    {
        $actions = [];

        $sendDelayDays = $resendRule->send_delay_days;
        $sendHour = $resendRule->send_hour;
        $clientForTimezone = $client ?? $resendRule->client;
        $clientSendHour = $this->getFormattedSendHourByClientTimeZone($clientForTimezone, $sendHour);
        
        $delayLegend = ($sendDelayDays == 1) ? "al día siguiente" : "a los {$sendDelayDays} días";
        $hourLegend = $clientSendHour ? "a las {$clientSendHour} hs." : "";
        
        $resendLegend = "Reenviar el presupuesto automáticamente\n{$delayLegend} de enviado el original";
        if ($hourLegend) {
            $resendLegend .= ", {$hourLegend}";
        }
        $actions['resend'] = $resendLegend;

        // Las acciones de plantilla y adjuntos ahora se mostrarán como snippets, no como acciones separadas

        return $actions;
    }


    private function getAutomationProposalResendActionSnippets($resendRule): array
    {
        $snippets = [];
        
        // Snippet para la plantilla de email
        if ($resendRule->sendEmailTemplate) {
            $templateTitle = $this->sanitizeStr($resendRule->sendEmailTemplate->title);
            $templateTitle = $this->breakLongStr($templateTitle, 50);
            $templateTitle = $this->shortenStr($templateTitle, 150);
            $snippets[] = "<b>Plantilla de email:</b>\n{$templateTitle}";
        }
        
        // Snippet para los archivos adjuntos
        if ($resendRule->add_original_attachments) {
            $snippets[] = "Se incluirán los archivos adjuntos\ndel presupuesto original";
        }
        
        return $snippets;
    }


    private function getAutomationEmailSendConditionLegends(AutomationEmailSend $automationEmailSend): array
    {
        $conditions = [];
        
        $isEmailSequenceTriggeredByTag = $automationEmailSend->triggeringTags->isNotEmpty();
        $isEmailSequenceTriggeredByStatus = $automationEmailSend->triggeringStatus->isNotEmpty();

        if ($automationEmailSend->isAfterSaleType) {
            $conditions[] = "💰 Se crea una venta";
        }
        if ($automationEmailSend->isAfterSentProposalType) {
            $conditions[] = "📧 Se envía un presupuesto vía email";
        }
        if ($isEmailSequenceTriggeredByTag) {
            $tags = $this->getTagsOrStatusMarkdown($automationEmailSend->triggeringTags);
            $conditions[] = "Se asigna al prospecto las <b>etiquetas</b>: \n\n{$tags}";
        }
        if ($isEmailSequenceTriggeredByStatus) {
            $tags = $this->getTagsOrStatusMarkdown($automationEmailSend->triggeringStatus);
            $conditions[] = "Se asigna al prospecto los <b>estados</b>: \n\n{$tags}";
        }
        if ($automationEmailSend->cancellingTags->isNotEmpty()) {
            $tags = $this->getTagsOrStatusMarkdown($automationEmailSend->cancellingTags);
            $conditions[] = "El prospecto no tiene asignada alguna <b>etiquetas</b> " .
                "que cancelan la secuencia:\n\n{$tags}"
            ;
        }
        if ($automationEmailSend->cancellingStatus->isNotEmpty()) {
            $status = $this->getTagsOrStatusMarkdown($automationEmailSend->cancellingStatus);
            $conditions[] = "El prospecto no tiene asignado alguno de los <b>estados</b> " .
                "que cancelan la secuencia:\n\n{$status}"
            ;
        }
        if ($automationEmailSend->cancel_if_sequence_was_sent) {
            $conditions[] = "La secuencia no fue enviada en el pasado";
        }
        return array_filter($conditions);
    }


    private function getWAutomationSequenceConditionLegends(WAutomationSequence $wAutomationSequence): array
    {
        $conditions = [];
        
        $isWAutomationSequenceTriggeredByTag = $wAutomationSequence->triggeringTags->isNotEmpty();
        $isWAutomationSequenceTriggeredByStatus = $wAutomationSequence->triggeringStatus->isNotEmpty();

        if ($wAutomationSequence->isAfterSaleType) {
            $conditions[] = "💰 Se crea una venta";
        }
        if ($wAutomationSequence->isAfterSentProposalType) {
            $conditions[] = "📱 Se envía un presupuesto vía WhatsApp";
        }
        if ($isWAutomationSequenceTriggeredByTag) {
            $tags = $this->getTagsOrStatusMarkdown($wAutomationSequence->triggeringTags);
            $conditions[] = "Se asigna al prospecto las <b>etiquetas</b>: \n\n{$tags}";
        }
        if ($isWAutomationSequenceTriggeredByStatus) {
            $tags = $this->getTagsOrStatusMarkdown($wAutomationSequence->triggeringStatus);
            $conditions[] = "Se asigna al prospecto los <b>estados</b>: \n\n{$tags}";
        }
        if ($wAutomationSequence->cancellingTags->isNotEmpty()) {
            $tags = $this->getTagsOrStatusMarkdown($wAutomationSequence->cancellingTags);
            $conditions[] = "El prospecto no tiene asignada alguna <b>etiquetas</b> " .
                "que cancelan la secuencia:\n\n{$tags}"
            ;
        }
        if ($wAutomationSequence->cancellingStatus->isNotEmpty()) {
            $status = $this->getTagsOrStatusMarkdown($wAutomationSequence->cancellingStatus);
            $conditions[] = "El prospecto no tiene asignado alguno de los <b>estados</b> " .
                "que cancelan la secuencia:\n\n{$status}"
            ;
        }
        if ($wAutomationSequence->cancel_if_sequence_was_sent) {
            $conditions[] = "La secuencia no fue enviada en el pasado";
        }
        return array_filter($conditions);
    }


    private function getAutomationEmailSendStepsLegends(AutomationEmailSend $automationEmailSend): array
    {
        $steps = [];
        $triggeringType = $automationEmailSend->triggeringTags->isNotEmpty() ? 'etiquetas' : 'estados';

        foreach ($automationEmailSend->automationEmailSendSteps as $step) {
            $sendHour = $step->send_hour;
            $delayDays = $step->send_delay_days;
            $delayMinutes = $step->send_delay_minutes;
            $clientSendHour = $this->getFormattedSendHourByClientTimeZone($automationEmailSend->client, $sendHour);
            
            $sendHourLegend = "<b>a las {$clientSendHour} hs.</b>";
            $delayDaysLegend = ($delayDays == 1) ? "<b>Al día siguiente</b>" : "<b>A los {$delayDays} días</b>";
            $delayMinutesLegend = ($delayMinutes == 1) ? "<b>Al minuto</b>" : "<b>A los {$delayMinutes} minutos</b>";

            $emailTemplateTitle = $this->sanitizeStr($step->sendEmailTemplate->title);
            $emailTemplateTitle = $this->breakLongStr($emailTemplateTitle);

            $stepLegend = $delayDays
                ? "{$delayDaysLegend} de la asignación de {$triggeringType}, {$sendHourLegend}"
                : "{$delayMinutesLegend} de la asignación de {$triggeringType}."
            ;
            $stepLegend .= "\nPlantilla <b>{$emailTemplateTitle}</b>";
            $steps[] = $stepLegend;
        }
        return $steps;
    }


    private function getWAutomationSequenceStepsLegends(WAutomationSequence $wAutomationSequence): array
    {
        $steps = [];

        $triggeringType = $wAutomationSequence->triggeringTags->isNotEmpty() ? 'etiquetas' : 'estados';

        foreach ($wAutomationSequence->wAutomationSequenceSteps as $step) {
            $stepId = $step->id;
            $sendHour = $step->send_hour;
            $delayDays = $step->send_delay_days;
            $delayMinutes = $step->send_delay_minutes;
            $clientSendHour = $this->getFormattedSendHourByClientTimeZone($wAutomationSequence->client, $sendHour);
            
            $sendHourLegend = "<b>a las {$clientSendHour} hs.</b>";
            $delayDaysLegend = ($delayDays == 1) ? "<b>Al día siguiente</b>" : "<b>A los {$delayDays} días</b>";
            $delayMinutesLegend = ($delayMinutes == 1) ? "<b>Al minuto</b>" : "<b>A los {$delayMinutes} minutos</b>";

            $emailTemplateTitle = $this->sanitizeStr($step->sendWhatsAppTemplate->title);
            $emailTemplateTitle = $this->breakLongStr($emailTemplateTitle);

            $stepLegend = $delayDays
                ? "{$delayDaysLegend} de la asignación de {$triggeringType}, {$sendHourLegend}"
                : "{$delayMinutesLegend} de la asignación de {$triggeringType}."
            ;
            $stepLegend .= "\nPlantilla <b>{$emailTemplateTitle}</b>";
            $steps[$stepId] = $stepLegend;
        }

        return $steps;
    }


    private function getWAutomationSequenceStepActionsAfterSendLegends(WAutomationSequence $wAutomationSequence): array
    {
        $stepActions = [];

        foreach ($wAutomationSequence->wAutomationSequenceSteps as $step) {
            $stepId = $step->id;
            if ($step->tagsToAdd->isNotEmpty()) {
                $tags = $this->getTagsOrStatusMarkdown($step->tagsToAdd);
                $stepActions[$stepId][] = "Asignar <b>etiquetas</b>:\n\n{$tags}";
            }
            if ($step->statusToAdd) {
                $status = $this->getTagsOrStatusMarkdown(collect([$step->statusToAdd]));
                $stepActions[$stepId][] = "Asignar <b>estado</b>: {$status}";
            }
        }

        return $stepActions;
    }


    private function getAutomationNewLeadConditionLegends(AutomationNewLead $automation): array
    {
        $conditions = [];

        $formFieldsToMatch = $automation->formFieldsToMatch;
        $triggeringLandings = $automation->triggeringLandings;
        $triggeringLeadType = $automation->triggering_lead_type;
        $utmParametersToMatch = $automation->utmParametersToMatch;
        $triggerIfPhoneRepeatead = $automation->trigger_if_phone_repeatead;
        $triggerIfEmailRepeatead = $automation->trigger_if_email_repeatead;
        $leadCustomFieldsMatch = $automation->leadCustomFieldsMatch ?? collect();
        $trackingParametersToMatch = $automation->trackingParametersToMatch ?? collect();

        $leadTypeMap = [
            'chat' => 'Chat Robot Web',
            'api' => 'Ingresados vía API',
            'web_form' => 'Formulario Web',
            'whatsapp_form' => 'Formulario WhatsApp',
            'facebook_form' => 'Formulario Facebook',
            'wap_bot_chat' => 'Chat Clienty Wap Bot',
            'form' => 'Formulario tipo Web, WhatsApp o Facebook',
            'manual_bulk' => 'Ingresado manualmente de forma masiva',
            'manual' => 'Ingresado manualmente (de forma individual o masiva)',
            'manual_individual' => 'Ingresado manualmente de forma individual',
            'form_or_chat' => 'Todos los formularios y chat robot web (excluye API y manuales)',
        ];

        if ($triggeringLeadType && isset($leadTypeMap[$triggeringLeadType])) {
            $leadType = $leadTypeMap[$triggeringLeadType];
            $conditions[] = "⬇️ Método de ingreso:\n{$leadType}";
        }
        if ($triggeringLandings->isEmpty()) {
            $conditions[] = "Landing de origen: Todas";
        } else {
            $landingUrls = $triggeringLandings->pluck('url')->implode('<br>');
            $conditions[] = "Landing de origen:<br>{$landingUrls}";
        }
        if ($triggerIfEmailRepeatead) {
            $conditions[] = "El prospecto tiene un email repetido en otro prospecto";
        }
        if ($triggerIfPhoneRepeatead) {
            $conditions[] = "El prospecto tiene un teléfono repetido en otro prospecto";
        }
        if ($formFieldsToMatch->isNotEmpty()) {
            $conditions[] = "Aplican condiciones de campos de formulario";
        }
        if ($utmParametersToMatch->isNotEmpty()) {
            $conditions[] = "Aplican condiciones de parámetros UTM";
        }
        if ($trackingParametersToMatch->isNotEmpty()) {
            $conditions[] = "Aplican condiciones de parámetros de tracking";
        }
        if ($leadCustomFieldsMatch->isNotEmpty()) {
            $conditions[] = "Aplican condiciones de campos personalizados";
        }

        return $conditions;
    }


    private function getAutomationTaskConditionLegends(AutomationTask $automation): array
    {
        $conditions = [];
        
        $triggerType = $automation->trigger_type;

        $triggeringTags = $automation->triggeringTags;
        $triggeringStatus = $automation->triggeringStatus;

        $cancellingTags = $automation->cancellingTags;
        $cancellingStatus = $automation->cancellingStatus;

        $allowingTags = $automation->allowingTags;
        $allowingStatus = $automation->allowingStatus;

        if ($triggerType == 'after_sale') {
            $conditions[] = "🚀 Luego de una venta cerrada";
        }
        if ($triggerType == 'after_tag_change') {
            $tags = $this->getTagsOrStatusMarkdown($triggeringTags);
            $conditions[] = "Luego de asignarle al prospecto alguna de estas <b>etiquetas</b>: \n\n{$tags}";
        }
        if ($triggerType == 'after_status_change') {
            $status = $this->getTagsOrStatusMarkdown($triggeringStatus);
            $conditions[] = "Luego de asignarle al prospecto alguno de estos <b>estados</b>: \n\n{$status}";
        }
        if ($triggerType == 'after_task_expiration') {
            $conditions[] = "⏰ Luego de vencer una tarea";
            if ($allowingStatus->isNotEmpty()) {
                $status = $this->getTagsOrStatusMarkdown($allowingStatus);
                $conditions[] = "Aplica solo si tiene asignado alguna de estas <b>etiquetas</b>: \n\n{$status}";
            }
            if ($allowingTags->isNotEmpty()) {
                $tags = $this->getTagsOrStatusMarkdown($allowingTags);
                $conditions[] = "Aplica solo si tiene asignado alguno de estos <b>estados</b>: \n\n{$tags}";
            }
        }
        if ($cancellingTags->isNotEmpty()) {
            $tags = $this->getTagsOrStatusMarkdown($cancellingTags);
            $conditions[] = "El prospecto no tiene asignada alguna de las <b>etiquetas</b> " .
                "que cancelan la acción:\n\n{$tags}"
            ;
        }
        if ($cancellingStatus->isNotEmpty()) {
            $status = $this->getTagsOrStatusMarkdown($cancellingStatus);
            $conditions[] = "El prospecto no tiene asignado alguno de los <b>estados</b> " .
                "que cancelan la acción:\n\n{$status}"
            ;
        }

        $conditions[] = "🟢 La automatización de tarea está habilitada";

        return $conditions;
    }


    private function getAutomationProposalConditionLegends(AutomationProposal $automation): array
    {
        $conditions[] = "🟢 La automatización de presupuesto está habilitada";

        return $conditions;
    }


    private function getAutomationProposalResendConditionLegends(AutomationProposal $automation): array
    {
        $conditions = [];
        $resendRule = $automation->resendRule;

        if (!$resendRule || !$resendRule->enabled) {
            return $conditions;
        }

        // Solo mostrar condiciones de cancelación si está activo el bloque de cancelación de reenvío automático
        if ($resendRule->cancelling_enabled) {
            if ($resendRule->cancellingTags->isNotEmpty()) {
                $tags = $this->getTagsOrStatusMarkdown($resendRule->cancellingTags);
                $conditions[] = "El prospecto no tiene asignada alguna de las <b>etiquetas</b> " .
                    "que cancelan el reenvío:\n\n{$tags}"
                ;
            }
            if ($resendRule->cancellingStatusList->isNotEmpty()) {
                $status = $this->getTagsOrStatusMarkdown($resendRule->cancellingStatusList);
                $conditions[] = "El prospecto no tiene asignado alguno de los <b>estados</b> " .
                    "que cancelan el reenvío:\n\n{$status}"
                ;
            }

            // Si está seleccionada la opción de cancelar si el email fue abierto
            if ($resendRule->cancel_if_proposal_was_opened) {
                $conditions[] = "El email de presupuesto <b>no fue abierto</b>";
            }

            // Si está seleccionada la opción de cancelar si ya fue enviado otro presupuesto
            if ($resendRule->cancel_if_proposal_was_already_sent) {
                $conditions[] = "Anteriormente al prospecto <b>no se le envió otro presupuesto</b>";
            }
        }

        return $conditions;
    }



    private function getTaskTemplateCreateLegend(AutomationTask $automation): string
    {
        $createDays = $automation->create_delay_days;
        $isRecurrent = $automation->is_recurrent;
        $createHour = $this->getFormattedSendHourByClientTimeZone($automation->client, $automation->create_hour);

        $createHourLegend = "{$createHour} hs.";
        $frequencyLegend = $isRecurrent ? "de forma recurrente" : "una sola vez";
        $createDaysLegend = $isRecurrent ?
            ($createDays == 1 ? "cada 1 día" : "cada {$createDays} días") :
            ($createDays == 1 ? "al día siguiente" : "a los {$createDays} días")
        ;

        return "📆 Se crea una tarea <b>{$frequencyLegend}</b> {$createDaysLegend} a las {$createHourLegend}</b>";
    }


    private function getTaskTemplateLimitDateLegend(AutomationTask $automation): string
    {
        $limitDays = $automation->taskTemplate->limit_date_days;
        $daysLegend = "A los {$limitDays} días de ser creada";
        if ($limitDays == 0) {
            $daysLegend = "El mismo día que es creada";
        }
        if ($limitDays == 1) {
            $daysLegend = "Al día siguiente de ser creada";
        }

        $limitHour = $automation->taskTemplate->limit_date_hour;
        $hourLegend = $limitHour ? "a las {$limitHour} hs." : "a las 23:59 hs.";
        return "{$daysLegend}, {$hourLegend}";
    }


    private function getTagsOrStatusMarkdown($tags): string
    {
        $formattedTags = $tags->map(function ($tag) {
            $textColorRgba = $this->hexToRgb($tag->text_color);
            $backColorRgba = $this->hexToRgb($tag->background_color);
            $style = "padding:5px;border-radius:4px;color:{$textColorRgba};background-color:{$backColorRgba}";
            return "<span style='{$style}'>{$tag->name}</span>";
        });

        $formattedTags = $formattedTags->chunk(5)->map(function ($chunk) {
            return $chunk->implode(' ') . "\n\n";
        })->implode('');

        return trim($formattedTags);
    }


    private function orderAutomationsTaskByTagsAndStatusCount(Collection $automations)
    {
        return $automations->sortByDesc(function ($automation) {
            $arrays = [
                $automation->allowing_tags_ids,
                $automation->tags_ids_to_assign,
                $automation->allowing_status_ids,
                $automation->cancelling_tags_ids,
                $automation->triggering_tags_ids,
                $automation->triggering_status_ids,
                $automation->cancelling_status_ids,
            ];

            return array_sum(array_map(fn($arr) => count($arr), $arrays));
        })->values();
    }


    private function orderAutomationsEmailSendByStepCount(Collection $automationsEmailSend)
    {
        return $automationsEmailSend->sortByDesc(
            fn ($automation) => $automation->automationEmailSendSteps->count()
        )->values();
    }


    private function orderWAutomationsSequenceByStepCount(Collection $wAutomationsSequence)
    {
        return $wAutomationsSequence->sortByDesc(
            fn ($automation) => $automation->wAutomationSequenceSteps->count()
        )->values();
    }


    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }
        if (!preg_match('/^[a-fA-F0-9]{6}$/', $hex)) {
            return 'rgb(0, 0, 0)';
        }
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
        return "rgb($r, $g, $b)";
    }


    private function getFormattedSendHourByClientTimeZone(Client $client, ?string $sendHour): string
    {
        if (!$sendHour) {
            return '';
        }
        $clientTz = new DateTimeZone($client->timezone);
        $sendHourArr = explode(':', $sendHour);
        $hour = (int) $sendHourArr[0];
        $minutes = (int) $sendHourArr[1];

        // Set date (with hour and minute) with Client TZ
        $date = (new DateTime())->setTime($hour, $minutes, 0)->setTimezone($clientTz);

        return $date->format('H:i');
    }


    private function breakLongStr(string $text, int $maxLength = 60): string
    {
        $lineLength = 0;
        $formattedText = "";
        $words = explode(" ", $text);

        foreach ($words as $word) {
            if ($lineLength + strlen($word) > $maxLength) {
                $formattedText = rtrim($formattedText) . "\n";
                $lineLength = 0;
            }

            $formattedText .= $word . " ";
            $lineLength += strlen($word) + 1;
        }

        return trim($formattedText);
    }


    private function sanitizeStr(?string $str): string
    {
        return preg_replace('/[^a-zA-Z0-9\s\p{L}.,!?()-]/u', '', $str ?? '');
    }


    private function shortenStr(string $str, int $maxLength = 60): string
    {
        if (strlen($str) > $maxLength) {
            return trim(substr($str, 0, $maxLength)) . '...';
        }

        return $str;
    }


    public function buildAutomationProposalMarkdown(
        AutomationProposal $automationProposal,
        array $opts = []
    ): string {
        $autId = $automationProposal->id;
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";
        $markdown .= "subgraph Proposal_{$autId} [\"&nbsp;\"]\n";
        $markdown .= "style Proposal_{$autId} fill:#fff,stroke:#bbb,stroke-width:1px\n";

        $actionsLegendsMap = $this->getAutomationProposalActionLegends($automationProposal);
        $conditionLegends = $this->getAutomationProposalConditionLegends($automationProposal);
            
        $previousConditionNodeId = null;
        foreach ($conditionLegends as $conditionIndex => $conditionLegend) {
            $currentConditionNodeId = "A{$autId}_Cond{$conditionIndex}";
            $markdown .= "{$currentConditionNodeId}[\"{$conditionLegend}\"]\n";
            $markdown .= "style {$currentConditionNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
            if ($previousConditionNodeId) {
                $markdown .= "{$previousConditionNodeId} -- Y --> {$currentConditionNodeId}\n";
            }
            $previousConditionNodeId = $currentConditionNodeId;
        }

        $markdown .= "{$previousConditionNodeId} -- Y --> A{$autId}_Check" .
            "{¿Se cumplen<br>todas las condiciones?}\n"
        ;
        $markdown .= "A{$autId}_Check -- ❌ No --> A{$autId}_NotApply" .
            "[\"No aplicar automatización de presupuesto\"]\n"
        ;
        $markdown .= "style A{$autId}_Check fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
        $markdown .= "style A{$autId}_NotApply fill:#f1f1f1,stroke:#aaa,stroke-width:1px\n";

        // Separar acciones normales de acciones de reenvío
        $normalActions = [];
        $resendActions = [];
        foreach ($actionsLegendsMap as $automationAttr => $actionLegend) {
            if (strpos($automationAttr, 'resend_') === 0) {
                $resendActions[$automationAttr] = $actionLegend;
            } else {
                $normalActions[$automationAttr] = $actionLegend;
            }
        }

        $actionIndex = 1;
        $previousActionId = null;

        // Procesar acciones normales
        foreach ($normalActions as $automationAttr => $actionLegend) {
            $actionId = "A{$autId}_Action{$actionIndex}";
            $markdown .= "{$actionId}[\"{$actionLegend}\"]\n";
            $markdown .= "style {$actionId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";

            $connection = ($actionIndex === 1) ?
                "A{$autId}_Check -- ✅ Sí --> {$actionId}\n" :
                "{$previousActionId} --> {$actionId}\n"
            ;
            $markdown .= $connection;
            $actionIndex++;
            $previousActionId = $actionId;
        }

        // Procesar acciones de reenvío dentro de un subgraph
        if (!empty($resendActions)) {
            $resendRule = $automationProposal->resendRule;
            $subgraphResendLegend = "Reenvío de presupuesto";
            $markdown .= "subgraph ResendSeq_{$autId}" .
                "[\"&nbsp;&nbsp;$subgraphResendLegend&nbsp;&nbsp;\"]\ndirection TB\n"
            ;

            // Condiciones de reenvío
            $resendConditionLegends = $this->getAutomationProposalResendConditionLegends($automationProposal);
            $previousResendConditionNodeId = null;
            $resendConditionIndex = 0;
            
            foreach ($resendConditionLegends as $conditionLegend) {
                $currentResendConditionNodeId = "A{$autId}_ResendCond{$resendConditionIndex}";
                $markdown .= "{$currentResendConditionNodeId}[\"{$conditionLegend}\"]\n";
                $markdown .= "style {$currentResendConditionNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
                
                if ($previousResendConditionNodeId) {
                    $markdown .= "{$previousResendConditionNodeId} -- Y --> {$currentResendConditionNodeId}\n";
                }
                $previousResendConditionNodeId = $currentResendConditionNodeId;
                $resendConditionIndex++;
            }

            // Nodo de decisión para condiciones de reenvío
            $resendStartNodeId = null;
            if ($previousResendConditionNodeId) {
                $markdown .= "{$previousResendConditionNodeId} -- Y --> A{$autId}_ResendCheck" .
                    "{¿Se cumplen<br>todas las condiciones<br>de reenvío?}\n"
                ;
                $markdown .= "A{$autId}_ResendCheck -- ❌ No --> A{$autId}_ResendNotApply" .
                    "[\"No reenviar presupuesto\"]\n"
                ;
                $markdown .= "style A{$autId}_ResendCheck fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
                $markdown .= "style A{$autId}_ResendNotApply fill:#f1f1f1,stroke:#aaa,stroke-width:1px\n";
                $resendStartNodeId = "A{$autId}_ResendCheck";
            }

            // Acciones de reenvío
            $resendActionIndex = 1;
            $previousResendActionId = "";
            $firstResendActionId = null;
            foreach ($resendActions as $automationAttr => $actionLegend) {
                $resendActionId = "A{$autId}_ResendAction{$resendActionIndex}";
                $markdown .= "{$resendActionId}[\"{$actionLegend}\"]\n";
                $markdown .= "style {$resendActionId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
                
                // Guardar el ID de la primera acción para conectar desde fuera del subgraph si no hay condiciones
                if ($resendActionIndex === 1) {
                    $firstResendActionId = $resendActionId;
                }
                
                // Cconectar desde el nodo de decisión; si no, conectar directamente desde el bloque anterior
                if ($resendActionIndex === 1) {
                    if ($resendStartNodeId) {
                        $markdown .= "{$resendStartNodeId} -- ✅ Sí --> {$resendActionId}\n";
                    }
                    // Si no hay condiciones, la conexión se hará después del loop, desde fuera del subgraph
                } else {
                    $markdown .= "{$previousResendActionId} --> {$resendActionId}\n";
                }
                
                // Agregar snippets para la acción de reenvío
                if ($automationAttr == 'resend_resend') {
                    $snippets = $this->getAutomationProposalResendActionSnippets($resendRule);
                    $snippetIndex = 1;
                    foreach ($snippets as $snippet) {
                        $snippetId = "A{$autId}_ResendAction{$resendActionIndex}_Snippet{$snippetIndex}";
                        $markdown .= "{$resendActionId} -.- {$snippetId}([\"{$snippet}\"])\n";
                        $markdown .= "style {$snippetId} fill:#fdfd96,stroke:#fbc02d,stroke-width:1px\n";
                        $snippetIndex++;
                    }
                }
                
                $previousResendActionId = $resendActionId;
                $resendActionIndex++;
            }
            $markdown .= "end\n\n";

            // Conectar el bloque anterior al subgraph o directamente a la primera acción si no hay condiciones
            if ($resendStartNodeId) {
                // Hay condiciones: conectar al subgraph
                if ($previousActionId) {
                    $markdown .= "{$previousActionId} -.-|\"🔁\"| ResendSeq_{$autId}\n";
                } else {
                    $markdown .= "A{$autId}_Check -- ✅ Sí -.-> ResendSeq_{$autId}\n";
                }
            } else {
                // No hay condiciones: conectar directamente a la primera acción de reenvío
                if ($firstResendActionId) {
                    if ($previousActionId) {
                        $markdown .= "{$previousActionId} --> {$firstResendActionId}\n";
                    } else {
                        $markdown .= "A{$autId}_Check -- ✅ Sí --> {$firstResendActionId}\n";
                    }
                }
            }
            $markdown .= "style ResendSeq_{$autId} fill:#f3f2f7,stroke:#333333,stroke-width:1px\n";
        }

        $markdown .= "direction TB\n\n";
        $markdown .= "end\n\n";
        $markdown .= "proposal[\"📄 Se envía un presupuesto\"] --> Proposal_{$autId}\n";
        $markdown .= "style proposal fill:#e6eeff,stroke: #95a9ff,stroke-width:1px\n";
        return $markdown;
    }


    public function buildWAutomationProposalMarkdown(
        WAutomationProposal $wAutomationProposal,
        array $opts = []
    ): string {
        $autId = $wAutomationProposal->id;
        $markdown = ($opts['avoidFlowchartTDString'] ?? false) ? "" : "flowchart TD;\n";
        $markdown .= "subgraph WProposal_{$autId} [\"&nbsp;\"]\n";
        $markdown .= "style WProposal_{$autId} fill:#fff,stroke:#bbb,stroke-width:1px\n";

        $actionsLegendsMap = $this->getWAutomationProposalActionLegends($wAutomationProposal);
        $conditionLegends = $this->getWAutomationProposalConditionLegends($wAutomationProposal);
            
        $previousConditionNodeId = null;
        foreach ($conditionLegends as $conditionIndex => $conditionLegend) {
            $currentConditionNodeId = "A{$autId}_Cond{$conditionIndex}";
            $markdown .= "{$currentConditionNodeId}[\"{$conditionLegend}\"]\n";
            $markdown .= "style {$currentConditionNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
            if ($previousConditionNodeId) {
                $markdown .= "{$previousConditionNodeId} -- Y --> {$currentConditionNodeId}\n";
            }
            $previousConditionNodeId = $currentConditionNodeId;
        }

        $markdown .= "{$previousConditionNodeId} -- Y --> A{$autId}_Check" .
            "{¿Se cumplen<br>todas las condiciones?}\n"
        ;
        $markdown .= "A{$autId}_Check -- ❌ No --> A{$autId}_NotApply" .
            "[\"No aplicar automatización de presupuesto de WhatsApp\"]\n"
        ;
        $markdown .= "style A{$autId}_Check fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
        $markdown .= "style A{$autId}_NotApply fill:#f1f1f1,stroke:#aaa,stroke-width:1px\n";

        // Separar acciones normales de acciones de reenvío
        $normalActions = [];
        $resendActions = [];
        foreach ($actionsLegendsMap as $automationAttr => $actionLegend) {
            if (strpos($automationAttr, 'resend_') === 0) {
                $resendActions[$automationAttr] = $actionLegend;
            } else {
                $normalActions[$automationAttr] = $actionLegend;
            }
        }

        $actionIndex = 1;
        $previousActionId = null;

        // Procesar acciones normales
        foreach ($normalActions as $automationAttr => $actionLegend) {
            $actionId = "A{$autId}_Action{$actionIndex}";
            $markdown .= "{$actionId}[\"{$actionLegend}\"]\n";
            $markdown .= "style {$actionId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";

            $connection = ($actionIndex === 1) ?
                "A{$autId}_Check -- ✅ Sí --> {$actionId}\n" :
                "{$previousActionId} --> {$actionId}\n"
            ;
            $markdown .= $connection;
            $actionIndex++;
            $previousActionId = $actionId;
        }

        // Procesar acciones de reenvío dentro de un subgraph
        if (!empty($resendActions)) {
            $resendRule = $wAutomationProposal->resendRule;
            $subgraphResendLegend = "Reenvío de presupuesto por WhatsApp";
            $markdown .= "subgraph ResendSeq_{$autId}" .
                "[\"&nbsp;&nbsp;$subgraphResendLegend&nbsp;&nbsp;\"]\ndirection TB\n"
            ;

            // Condiciones de reenvío
            $resendConditionLegends = $this->getWAutomationProposalResendConditionLegends($wAutomationProposal);
            $previousResendConditionNodeId = null;
            $resendConditionIndex = 0;
            
            foreach ($resendConditionLegends as $conditionLegend) {
                $currentResendConditionNodeId = "A{$autId}_ResendCond{$resendConditionIndex}";
                $markdown .= "{$currentResendConditionNodeId}[\"{$conditionLegend}\"]\n";
                $markdown .= "style {$currentResendConditionNodeId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
                
                if ($previousResendConditionNodeId) {
                    $markdown .= "{$previousResendConditionNodeId} -- Y --> {$currentResendConditionNodeId}\n";
                }
                $previousResendConditionNodeId = $currentResendConditionNodeId;
                $resendConditionIndex++;
            }

            // Nodo de decisión para condiciones de reenvío
            $resendStartNodeId = null;
            if ($previousResendConditionNodeId) {
                $markdown .= "{$previousResendConditionNodeId} -- Y --> A{$autId}_ResendCheck" .
                    "{¿Se cumplen<br>todas las condiciones<br>de reenvío?}\n"
                ;
                $markdown .= "A{$autId}_ResendCheck -- ❌ No --> A{$autId}_ResendNotApply" .
                    "[\"No reenviar presupuesto\"]\n"
                ;
                $markdown .= "style A{$autId}_ResendCheck fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
                $markdown .= "style A{$autId}_ResendNotApply fill:#f1f1f1,stroke:#aaa,stroke-width:1px\n";
                $resendStartNodeId = "A{$autId}_ResendCheck";
            }

            // Acciones de reenvío
            $resendActionIndex = 1;
            $previousResendActionId = "";
            $firstResendActionId = null;
            foreach ($resendActions as $automationAttr => $actionLegend) {
                $resendActionId = "A{$autId}_ResendAction{$resendActionIndex}";
                $markdown .= "{$resendActionId}[\"{$actionLegend}\"]\n";
                $markdown .= "style {$resendActionId} fill:#c0d3ff,stroke:#6a85ff,stroke-width:1px\n";
                
                // Guardar el ID de la primera acción para conectar desde fuera del subgraph si no hay condiciones
                if ($resendActionIndex === 1) {
                    $firstResendActionId = $resendActionId;
                }
                
                // Conectar desde el nodo de decisión; si no, conectar directamente desde el bloque anterior
                if ($resendActionIndex === 1) {
                    if ($resendStartNodeId) {
                        $markdown .= "{$resendStartNodeId} -- ✅ Sí --> {$resendActionId}\n";
                    }
                    // Si no hay condiciones, la conexión se hará después del loop, desde fuera del subgraph
                } else {
                    $markdown .= "{$previousResendActionId} --> {$resendActionId}\n";
                }
                
                // Agregar snippets para la acción de reenvío
                if ($automationAttr == 'resend_resend') {
                    $snippets = $this->getWAutomationProposalResendActionSnippets($resendRule);
                    $snippetIndex = 1;
                    foreach ($snippets as $snippet) {
                        $snippetId = "A{$autId}_ResendAction{$resendActionIndex}_Snippet{$snippetIndex}";
                        $markdown .= "{$resendActionId} -.- {$snippetId}([\"{$snippet}\"])\n";
                        $markdown .= "style {$snippetId} fill:#fdfd96,stroke:#fbc02d,stroke-width:1px\n";
                        $snippetIndex++;
                    }
                }
                
                $previousResendActionId = $resendActionId;
                $resendActionIndex++;
            }
            $markdown .= "end\n\n";

            // Conectar el bloque anterior al subgraph o directamente a la primera acción si no hay condiciones
            if ($resendStartNodeId) {
                // Hay condiciones: conectar al subgraph
                if ($previousActionId) {
                    $markdown .= "{$previousActionId} -.-|\"🔁\"| ResendSeq_{$autId}\n";
                } else {
                    $markdown .= "A{$autId}_Check -- ✅ Sí -.-> ResendSeq_{$autId}\n";
                }
            } else {
                // No hay condiciones: conectar directamente a la primera acción de reenvío
                if ($firstResendActionId) {
                    if ($previousActionId) {
                        $markdown .= "{$previousActionId} --> {$firstResendActionId}\n";
                    } else {
                        $markdown .= "A{$autId}_Check -- ✅ Sí --> {$firstResendActionId}\n";
                    }
                }
            }
            $markdown .= "style ResendSeq_{$autId} fill:#f3f2f7,stroke:#333333,stroke-width:1px\n";
        }

        $markdown .= "direction TB\n\n";
        $markdown .= "end\n\n";
        $markdown .= "proposal[\"📄 Se envía un presupuesto\"] --> WProposal_{$autId}\n";
        $markdown .= "style proposal fill:#e6eeff,stroke: #95a9ff,stroke-width:1px\n";
        return $markdown;
    }


    private function getWAutomationProposalActionLegends(WAutomationProposal $automation): array
    {
        $actions = [];

        $modifyLeadAfterSendRule = $automation->modifyLeadAfterSendRule;
        $resendRule = $automation->resendRule;

        // Acciones cuando se envía el presupuesto original (modifyLeadAfterSendRule)
        if ($modifyLeadAfterSendRule) {
            if ($modifyLeadAfterSendRule->add_sent_proposal_tag ?? false) {
                $tags = $this->getTagsOrStatusMarkdown($modifyLeadAfterSendRule->tagsToAdd);
                $actions['modify_add_sent_tag'] = "Al enviar el presupuesto original al prospecto,\n" .
                    "asignarle automáticamente las <b>etiquetas</b>:\n\n{$tags}"
                ;
            } elseif ($modifyLeadAfterSendRule->tagsToAdd->isNotEmpty()) {
                $tags = $this->getTagsOrStatusMarkdown($modifyLeadAfterSendRule->tagsToAdd);
                $actions['modify_add_tags'] = "Al enviar el presupuesto original al prospecto,\n" .
                    "asignarle automáticamente las <b>etiquetas</b>:\n\n{$tags}"
                ;
            }

            if ($modifyLeadAfterSendRule->statusToAssign) {
                $status = $this->getTagsOrStatusMarkdown(collect([$modifyLeadAfterSendRule->statusToAssign]));
                $actions['modify_assign_status'] = "Al enviar el presupuesto original al prospecto,\n" .
                    "asignarle automáticamente el siguiente <b>estado</b>:\n\n{$status}"
                ;
            }
        }

        // Reenvío de presupuesto (resendRule)
        if ($resendRule && $resendRule->enabled) {
            $resendActions = $this->getWAutomationProposalResendActionLegends($resendRule, $automation->client);
            foreach ($resendActions as $key => $action) {
                $actions["resend_{$key}"] = $action;
            }
        }

        return array_filter($actions);
    }


    private function getWAutomationProposalResendActionLegends($resendRule, ?Client $client = null): array
    {
        $actions = [];

        $sendDelayDays = $resendRule->send_delay_days;
        $sendHour = $resendRule->send_hour;
        $clientForTimezone = $client ?? $resendRule->client;
        $clientSendHour = $this->getFormattedSendHourByClientTimeZone($clientForTimezone, $sendHour);
        
        $delayLegend = ($sendDelayDays == 1) ? "al día siguiente" : "a los {$sendDelayDays} días";
        $hourLegend = $clientSendHour ? "a las {$clientSendHour} hs." : "";
        
        $resendLegend = "Reenviar el presupuesto automáticamente por WhatsApp\n{$delayLegend} de enviado el original";
        if ($hourLegend) {
            $resendLegend .= ", {$hourLegend}";
        }
        $actions['resend'] = $resendLegend;

        return $actions;
    }


    private function getWAutomationProposalResendActionSnippets($resendRule): array
    {
        $snippets = [];
        
        // Snippet para la plantilla de WhatsApp
        if ($resendRule->sendWhatsAppTemplate) {
            $templateTitle = $this->sanitizeStr($resendRule->sendWhatsAppTemplate->title);
            $templateTitle = $this->breakLongStr($templateTitle, 50);
            $templateTitle = $this->shortenStr($templateTitle, 150);
            $snippets[] = "<b>Plantilla de WhatsApp:</b>\n{$templateTitle}";
        }
        
        // Snippet para los archivos adjuntos
        if ($resendRule->add_original_attachments) {
            $snippets[] = "Se incluirá el archivo adjunto\ndel presupuesto original";
        }

        // Snippet para no enviar fines de semana
        if ($resendRule->do_not_send_weekends) {
            $snippets[] = "No enviar mensajes fines de semana.\nSe enviará el siguiente lunes";
        }
        
        return $snippets;
    }


    private function getWAutomationProposalConditionLegends(WAutomationProposal $automation): array
    {
        $conditions[] = "🟢 La automatización de presupuesto de WhatsApp está habilitada";

        return $conditions;
    }


    private function getWAutomationProposalResendConditionLegends(WAutomationProposal $automation): array
    {
        $conditions = [];
        $resendRule = $automation->resendRule;

        if (!$resendRule || !$resendRule->enabled) {
            return $conditions;
        }

        // Solo mostrar condiciones de cancelación si está activo el bloque de cancelación de reenvío automático
        if ($resendRule->cancelling_enabled) {
            if ($resendRule->cancellingTags->isNotEmpty()) {
                $tags = $this->getTagsOrStatusMarkdown($resendRule->cancellingTags);
                $conditions[] = "El prospecto no tiene asignada alguna de las <b>etiquetas</b> " .
                    "que cancelan el reenvío:\n\n{$tags}"
                ;
            }
            if ($resendRule->cancellingStatusList->isNotEmpty()) {
                $status = $this->getTagsOrStatusMarkdown($resendRule->cancellingStatusList);
                $conditions[] = "El prospecto no tiene asignado alguno de los <b>estados</b> " .
                    "que cancelan el reenvío:\n\n{$status}"
                ;
            }

            // Si está seleccionada la opción de cancelar si ya fue enviado otro presupuesto
            if ($resendRule->cancel_if_proposal_was_already_sent) {
                $conditions[] = "Anteriormente al prospecto <b>no se le envió otro presupuesto</b>";
            }
        }

        return $conditions;
    }

}