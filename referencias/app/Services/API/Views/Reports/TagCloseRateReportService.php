<?php

namespace App\Services\API\Views\Reports;

use App\Models\Tag;
use App\Models\Client;
use App\Services\API\TagService;
use Illuminate\Support\Collection;
use App\Services\API\LeadService;
use App\Services\API\LeadSaleService;
use App\Services\Traits\GetClientFromRequest;


class TagCloseRateReportService
{

    use GetClientFromRequest;

    private $tagService;
    private $leadService;
    private $leadSaleService;


    public function __construct(
        TagService $tagService,
        LeadService $leadService,
        LeadSaleService $leadSaleService
    ) {
        $this->tagService = $tagService;
        $this->leadService = $leadService;
        $this->leadSaleService = $leadSaleService;
    }


    public function list(array $opts = []): Collection
    {
        $client = $this->getClient();

        $tagsFilters = ['client' => $client];
        
        $filterTagIds = $opts['filters']['tag_id'] ?? null;
        $filterUserIds = $opts['filters']['user_id'] ?? null;
        $filterTagCategoryIds = $opts['filters']['tag_category_id'] ?? null;
        $filterChannelIds = $opts['filters']['acquisition_channel_id'] ?? null;

        if ($filterTagCategoryIds) {
            $tagsFilters['tag_category_id'] = $opts['filters']['tag_category_id'];
        }
        if ($filterTagIds) {
            $tagsFilters['id'] = $opts['filters']['tag_id'];
        }
        $tagsOptions = [
            'filters' => $tagsFilters,
            'with' => ['tagCategory'], // Evito cargar leads acá por performance de memoria.
        ];
        // \Illuminate\Support\Facades\DB::enableQueryLog();
        $tags = $this->tagService->list($tagsOptions);
        // dd(\Illuminate\Support\Facades\DB::getQueryLog());

        foreach ($tags as $tag) {
            $relation = $tag->leads();
            if ($filterUserIds) {
                $relation->whereIn('Leads.user_id', $filterUserIds);
            }
            if ($filterChannelIds) {
                $relation->whereIn('Leads.acquisition_channel_id', $filterChannelIds);
            }
            $leadIds = $relation->pluck('Leads.id');
            $tag->leads_ids = $leadIds->toArray();
            //Cargo en custom_, para evitar llamar al relation leadsCount que existe en el modelo
            $tag->custom_leads_count = $leadIds->count();
            $tag->unsetRelation('leads');
        }

        $report = [];
        foreach ($tags as $tag) {
            $tagId = $tag->id;
            $row = $report[$tagId] ?? null;
            if (!$row) {
                $report[$tagId] = ['tag' => $tag];
            }
            $report[$tagId]['leads_count'] = $tag->custom_leads_count;
            $leadSaleOpts = ['client' => $client, 'filters' => [], 'fields' => ['id', 'lead_id']];
            
            $leadSales = new Collection();
            if ($tag->leads_ids) {
                $leadSaleOpts['filters']['lead_id'] = $tag->leads_ids;
                $leadSales = $this->leadSaleService->list($leadSaleOpts);
            }
            $report[$tagId]['total_sales_count'] = $leadSales->count();
            $report[$tagId]['unique_sales_count'] = $leadSales->pluck('lead_id')->unique()->count();
        }

        // To clear tag_id array indexes.
        $report = collect($report)->values();
        
        $tagFilterIsApplied = $tagsFilters['id'] ?? false;
        $tagCategoryFilterIsApplied = $tagsFilters['tag_category_id'] ?? false;
        if (!$tagFilterIsApplied && !$tagCategoryFilterIsApplied) {
            $leadsWithNoTags = $this->leadService->findByClientWithNoTags(
                $client, ['fields' => ['id', 'user_id', 'acquisition_channel_id']]
            );
            if ($filterChannelIds) {
                $leadsWithNoTags = $leadsWithNoTags->filter(function ($lead) use ($filterChannelIds) {
                    return in_array($lead['acquisition_channel_id'], $filterChannelIds);
                });
            }
            if ($filterUserIds) {
                $leadsWithNoTags = $leadsWithNoTags->filter(function ($lead) use ($filterUserIds) {
                    return in_array($lead['user_id'], $filterUserIds);
                });
            }
            $leadSales = $this->leadSaleService->findByClientAndLeads($client, $leadsWithNoTags, ['id', 'lead_id']);
            $report->push([
                'tag' => null,
                'leads_count' => $leadsWithNoTags->count(),
                'total_sales_count' => $leadSales->count(),
                'unique_sales_count' => $leadSales->pluck('lead_id')->unique()->count(),
            ]);
        }

        return $report;
    }

}
