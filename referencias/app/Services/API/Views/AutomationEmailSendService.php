<?php
namespace App\Services\API\Views;

use App\Models\AutomationEmailSend;
use App\Helpers\MermaidChartHelper;
use Illuminate\Database\Eloquent\Collection;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\Cache\AutomationEmailSendRepositoryCache;
use App\Repositories\Automations\AutomationEmailSendRepository;


class AutomationEmailSendService
{

    use GetClientFromRequest;


    public function __construct(
        protected MermaidChartHelper $mermaidChartHelper,
        protected AutomationEmailSendRepository | AutomationEmailSendRepositoryCache $automationEmailSendRepository
    ) {
    }


    public function findAutomationsByClient(): Collection
    {
        return $this->automationEmailSendRepository->findByClient($this->getClient());
    }


    public function getFlowChartMarkdownString(AutomationEmailSend $automation,): string
    {
        $markdown = $this->mermaidChartHelper->buildAutomationEmailSendMarkdown($automation);
        return $markdown;
    }

}
