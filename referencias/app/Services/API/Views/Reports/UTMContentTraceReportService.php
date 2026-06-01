<?php

namespace App\Services\API\Views\Reports;

use DateTime;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\AcquisitionChannelService;


class UTMContentTraceReportService
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
        
        $acquisitionChannels = new Collection([null]);
        if ($acquisitionChannelIds) {
            $acquisitionChannels = $this->acquisitionChannelService->findByClientAndIds(
                $client, $acquisitionChannelIds
            );
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
                ->select(['id', 'acquisition_channel_id', 'utm_campaign', 'utm_medium', 'utm_content', 'quality'])
                ->get(['id', 'acquisition_channel_id', 'utm_campaign', 'utm_medium', 'utm_content', 'quality'])
            ;

            $leadsSales = new Collection();
            $leadsProposals = new Collection();
            $channelLeads
                ->chunk(2000)
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
                })
            ;

            $utmCampaignNames = $channelLeads->pluck('utm_campaign')->unique();
            foreach ($utmCampaignNames as $utmCampaignName) {
                $utmCampaignLeads = $channelLeads->where('utm_campaign', $utmCampaignName);
                $utmMediumNames = $utmCampaignLeads->pluck('utm_medium')->unique();

                foreach ($utmMediumNames as $utmMediumName) {
                    $utmMediumLeads = $utmCampaignLeads->where('utm_medium', $utmMediumName);
                    $utmContentNames = $utmMediumLeads->pluck('utm_content')->unique();

                    foreach ($utmContentNames as $utmContentName) {
                        $utmContentLeads = $utmMediumLeads->where('utm_content', $utmContentName);

                        $utmContentLeadIds = $utmContentLeads->pluck('id');
                        $utmContentLeadsCount = $utmContentLeadIds->unique()->count();

                        $utmContentSales = $leadsSales->whereIn('lead_id', $utmContentLeadIds);
                        $utmContentProposals = $leadsProposals->whereIn('lead_id', $utmContentLeadIds);

                        $utmContentTotalSalesCount = $utmContentSales->count();
                        $utmContentTotalProposalsCount = $utmContentProposals->count();

                        $utmContentLeadsWithSalesCount = $utmContentSales->pluck('lead_id')->unique()->count();
                        $utmContentLeadsWithSentProposalsCount = $utmContentProposals
                            ->pluck('lead_id')
                            ->unique()
                            ->count()
                        ;

                        $qualityAvg = $utmContentLeads->avg('quality');
                        $closeRateAvg = $utmContentLeadsWithSentProposalsCount
                            ? ($utmContentLeadsWithSalesCount * 100) / $utmContentLeadsWithSentProposalsCount
                            : 0
                        ;

                        $reportRow = [
                            'utm_medium' => $utmMediumName,
                            'utm_content' => $utmContentName,
                            'utm_campaign' => $utmCampaignName,
                            'leads_quality_avg' => $qualityAvg,
                            'leads_count' => $utmContentLeadsCount,
                            'total_sales_count' => $utmContentTotalSalesCount,
                            'acquisition_channel_id' => $acquisitionChannelId,
                            'acquisition_channel_name' => $acquisitionChannelName,
                            'total_proposals_count' => $utmContentTotalProposalsCount,
                            'leads_with_sales_count' => $utmContentLeadsWithSalesCount,
                            'close_rate_avg' => ($closeRateAvg <= 100) ? $closeRateAvg : 100,
                            'leads_with_proposals_count' => $utmContentLeadsWithSentProposalsCount,
                        ];
                        $report->push($reportRow);
                    }
                }
            }
        }
        return $report;
    }

}
