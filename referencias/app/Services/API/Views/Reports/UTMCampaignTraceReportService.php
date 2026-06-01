<?php

namespace App\Services\API\Views\Reports;

use DateTime;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\AcquisitionChannelService;


class UTMCampaignTraceReportService
{

    public function __construct(protected readonly AcquisitionChannelService $acquisitionChannelService)
    {
    }


    public function list(Client $client, array $options = [])
    {
        $filters = $options['filters'] ?? [];
        $userIds = $filters['user_id'] ?? [];
        $dateEnd = $filters['date_end'] ?? null;
        $dateStart = $filters['date_start'] ?? null;
        $dateEndStr = $dateEnd ? $dateEnd->format('Y-m-d H:i:s') : null;
        $acquisitionChannelIds = $filters['acquisition_channel_id'] ?? [];
        $dateStartStr = $dateStart ? $dateStart->format('Y-m-d H:i:s') : null;
        
        if ($acquisitionChannelIds) {
            $acquisitionChannels = $this->acquisitionChannelService->findByClientAndIds(
                $client, $acquisitionChannelIds
            );
        } else {
            $acquisitionChannels = $this->acquisitionChannelService->findAllByClient($client);
            $acquisitionChannels->push(null);
        }

        $report = new Collection();
        foreach ($acquisitionChannels as $acquisitionChannel) {
            $acquisitionChannelId = $acquisitionChannel?->id;
            $acquisitionChannelName = $acquisitionChannel?->name;

            $leadsQuery = DB::table('Leads')
                ->whereNull('deleted_at')
                ->where('client_id', $client->id)
                ->where('acquisition_channel_id', $acquisitionChannelId);
            ;
            if ($userIds) {
                $leadsQuery->whereIn('user_id', $userIds);
            }
            if ($dateEndStr) {
                $leadsQuery->whereRaw("lead_created_at <= '{$dateEndStr}'");
            }
            if ($dateStartStr) {
                $leadsQuery->whereRaw("lead_created_at >= '{$dateStartStr}'");
            }

            $channelLeads = $leadsQuery
                ->select(['id', 'acquisition_channel_id', 'utm_campaign', 'quality'])
                ->get(['id', 'acquisition_channel_id', 'utm_campaign', 'quality'])
            ;

            $leadsSales = new Collection();
            $leadsProposals = new Collection();
            $channelLeads->chunk(2000)
                ->each(function ($channelLeadsChunk) use (&$leadsSales, &$leadsProposals, $client) {
                    $leadsSalesChunk = DB::table('LeadsSales')
                        ->whereNull('deleted_at')
                        ->where('client_id', $client->id)
                        ->whereIn('lead_id', $channelLeadsChunk->pluck('id'))
                        ->select(['id', 'lead_id'])
                        ->get(['id', 'lead_id'])
                    ;
                    $leadsSales = $leadsSales->merge($leadsSalesChunk);

                    $leadsProposalsChunk = DB::table('ProposalsInfo')
                        ->whereNull('deleted_at')
                        ->where('client_id', $client->id)
                        ->whereIn('lead_id', $channelLeadsChunk->pluck('id'))
                        ->select(['id', 'lead_id'])
                        ->get(['id', 'lead_id'])
                    ;
                    $leadsProposals = $leadsProposals->merge($leadsProposalsChunk);
                    // echo "Memory usage: " . (memory_get_usage()) . " bytes\n<br>";
                })
            ;

            $utmCampaignNames = $channelLeads->pluck('utm_campaign')->unique();
            foreach ($utmCampaignNames as $utmCampaignName) {
                $utmCampaignLeads = $channelLeads->where('utm_campaign', $utmCampaignName);
                $utmCampaignLeadIds = $utmCampaignLeads->pluck('id');
                $utmCampaignLeadsCount = $utmCampaignLeadIds->unique()->count();

                $utmCampaignSales = $leadsSales->whereIn('lead_id', $utmCampaignLeadIds);
                $utmCampaignProposals = $leadsProposals->whereIn('lead_id', $utmCampaignLeadIds);

                $utmCampaignTotalSalesCount = $utmCampaignSales->count();
                $utmCampaignTotalProposalsCount = $utmCampaignProposals->count();

                $utmCampaignLeadsWithSalesCount = $utmCampaignSales->pluck('lead_id')->unique()->count();
                $utmCampaignLeadsWithSentProposalsCount = $utmCampaignProposals->pluck('lead_id')->unique()->count();

                $qualityAvg = $utmCampaignLeads->avg('quality');
                $closeRateAvg = $utmCampaignLeadsWithSentProposalsCount
                    ? ($utmCampaignLeadsWithSalesCount * 100) / $utmCampaignLeadsWithSentProposalsCount
                    : 0
                ;
                if ($closeRateAvg > 100) {
                    $closeRateAvg = 100;
                }

                $reportRow = [
                    'close_rate_avg' => $closeRateAvg,
                    'leads_quality_avg' => $qualityAvg,
                    'utm_campaign' => $utmCampaignName,
                    'leads_count' => $utmCampaignLeadsCount,
                    'acquisition_channel_id' => $acquisitionChannelId,
                    'total_sales_count' => $utmCampaignTotalSalesCount,
                    'acquisition_channel_name' => $acquisitionChannelName,
                    'total_proposals_count' => $utmCampaignTotalProposalsCount,
                    'leads_with_sales_count' => $utmCampaignLeadsWithSalesCount,
                    'leads_with_proposals_count' => $utmCampaignLeadsWithSentProposalsCount,
                ];
                $report->push($reportRow);
            }
        }
        return $report;
    }

}
