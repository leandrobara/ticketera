<?php

namespace App\Services\API\Views\Reports;

use DateTime;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\TagCategory;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\LeadRepository;
use App\DTO\Reports\Dashboard\PeriodDTO;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\Leads\SortByCreated;
use App\Repositories\Criteria\Filter\Leads\TagORCriteria;
use App\Repositories\Criteria\Filter\Leads\TagANDCriteria;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Repositories\Criteria\Filter\Leads\LeadQualityCriteria;
use App\Repositories\Criteria\Filter\Leads\OnlyUTMLeadsCriteria;
use App\Repositories\Criteria\Sort\Leads\SortByLastStatusChanged;
use App\Repositories\Criteria\Filter\Leads\SpecialFilterCriteria;
use App\Repositories\Criteria\Filter\Leads\TagORExclusiveCriteria;
use App\Repositories\Criteria\Filter\Leads\CreatedDateEndCriteria;
use App\Repositories\Criteria\Filter\Leads\CreatedDateStartCriteria;
use App\Repositories\Criteria\Filter\Leads\ZapierTriggerTypeCriteria;
use App\Repositories\Criteria\Filter\Leads\AcquisitionChannelCriteria;


class DashboardService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function leadMetrics(PeriodDTO $periodDTO, array $opts = []): array
    {
        $client = $this->getClient();
        $currentDateEndStr = $periodDTO->currentDateEnd->format('Y-m-d H:i:s');
        $previousDateEndStr = $periodDTO->previousDateEnd->format('Y-m-d H:i:s');
        $currentDateStartStr = $periodDTO->currentDateStart->format('Y-m-d H:i:s');
        $previousDateStartStr = $periodDTO->previousDateStart->format('Y-m-d H:i:s');
        $queryBuilder = DB::table('Leads')->where('client_id', $client->id)->whereNull('deleted_at');
        
        $totalLeadsCount = (clone $queryBuilder)
            ->whereRaw("lead_created_at <= '{$currentDateEndStr}'")
            ->count()
        ;
        $previousTotalLeadsCount = (clone $queryBuilder)
            ->whereRaw("lead_created_at <= '{$previousDateEndStr}'")
            ->count()
        ;
        $percentageDiff = 0;
        if ($totalLeadsCount) {
            $percentageDiff = ($totalLeadsCount - $previousTotalLeadsCount) * 100 / $totalLeadsCount;
        }
        $totalLeadMetrics = [
            'count' => $totalLeadsCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousTotalLeadsCount,
        ];

        $newLeadsCount = (clone $queryBuilder)
            ->whereRaw("lead_created_at <= '{$currentDateEndStr}'")
            ->whereRaw("lead_created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousNewLeadsCount = (clone $queryBuilder)
            ->whereRaw("lead_created_at <= '{$previousDateEndStr}'")
            ->whereRaw("lead_created_at >= '{$previousDateStartStr}'")
            ->count()
        ;
        $percentageDiff = 0;
        if ($newLeadsCount) {
            $percentageDiff = ($newLeadsCount - $previousNewLeadsCount) * 100 / $newLeadsCount;
        }
        $newLeadMetrics = [
            'count' => $newLeadsCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousNewLeadsCount,
        ];

        $mainChannelResults = (clone $queryBuilder)->whereNotNull('acquisition_channel_id')
            ->whereRaw("lead_created_at <= '{$currentDateEndStr}'")
            ->whereRaw("lead_created_at >= '{$currentDateStartStr}'")
            ->selectRaw('acquisition_channel_id')
            ->selectRaw('count(*) as leads_count')
            ->groupBy('acquisition_channel_id')
            ->orderByRaw('count(*) desc')
            ->limit(1)
            ->get()
        ;
        $acquisitionChannelId = $mainChannelResults->first()?->acquisition_channel_id;
        $acquisitionChannelName = 'Sin canal de adquisición';
        if ($acquisitionChannelId) {
            $acquisitionChannel = AcquisitionChannel::find($acquisitionChannelId);
            $acquisitionChannelName = $acquisitionChannel->name;
        }
        $mainChannelMetrics = [
            'acquisition_channel_name' => $acquisitionChannelName,
            'leads_count' => $mainChannelResults->first()?->leads_count ?? 0,
        ];

        $channelsChartResults = (clone $queryBuilder)->whereNotNull('acquisition_channel_id')
            ->whereRaw("lead_created_at <= '{$currentDateEndStr}'")
            ->whereRaw("lead_created_at >= '{$currentDateStartStr}'")
            ->selectRaw('acquisition_channel_id')
            ->selectRaw('count(*) as leads_count')
            ->groupBy('acquisition_channel_id')
            ->orderByRaw('count(*) desc')
            ->get()
        ;

        $channels = AcquisitionChannel::where('client_id', $client->id)->get()->keyBy('id');
        $channelsChartMetrics = $channelsChartResults->map(function ($row) use ($channels) {
            $channelId = $row->acquisition_channel_id;
            $channelName = $channels->get($channelId)->name;
            $hash = hash('crc32', $channelName);
            $color = '#' . substr($hash, 0, 6);
            return [
                'background_color' => $color,
                'leads_count' => $row->leads_count,
                'acquisition_channel_id' => $channelId,
                'acquisition_channel_name' => $channelName,
            ];
        });

        $totalLeadsChartMetrics = [];
        $chartQuery = clone $queryBuilder;
        foreach ($periodDTO->previousPeriods as $i => $prevPeriod) {
            $dateStr = $prevPeriod['date_start']->format('Y-m-d');
            $rowLabel = 'count_' . $prevPeriod['date_start']->format('Y_m_d');
            $chartQuery->selectRaw(
                "SUM(IF(lead_created_at <= ?, 1, 0)) as {$rowLabel}", [$dateStr]
            );
            $totalLeadsChartMetrics[$i] = ['period' => $prevPeriod, 'data' => ['count' => 0]];
        }
        $totalLeadsChartResult = $chartQuery->first();
        foreach ($periodDTO->previousPeriods as $i => $prevPeriod) {
            $rowLabel = 'count_' . $prevPeriod['date_start']->format('Y_m_d');
            $totalLeadsChartMetrics[$i]['data']['count'] = (int) $totalLeadsChartResult->$rowLabel;
        }

        $metrics = [
            'new_leads' => $newLeadMetrics,
            'total_leads' => $totalLeadMetrics,
            'main_acquisition_channel' => $mainChannelMetrics,
            'acquisition_channels_chart_info' => $channelsChartMetrics,
            'total_leads_chart_info' => array_values($totalLeadsChartMetrics),
        ];
        $metrics = $this->formatPercentages($metrics);
        return $metrics;
    }


    public function leadSaleMetrics(PeriodDTO $periodDTO, array $opts = []): array
    {
        $client = $this->getClient();
        $currentDateEndStr = $periodDTO->currentDateEnd->format('Y-m-d H:i:s');
        $previousDateEndStr = $periodDTO->previousDateEnd->format('Y-m-d H:i:s');
        $currentDateStartStr = $periodDTO->currentDateStart->format('Y-m-d H:i:s');
        $previousDateStartStr = $periodDTO->previousDateStart->format('Y-m-d H:i:s');

        $queryBuilder = DB::table('LeadsSales')->where('client_id', $client->id)->whereNull('deleted_at');
        $totalLeadSalesAmount = (clone $queryBuilder)
            ->selectRaw('SUM(amount) as amount')
            ->whereRaw("sale_date <= '{$currentDateEndStr}'")
            ->get()->first()?->amount ?? 0
        ;
        $previousTotalLeadSalesAmount = (clone $queryBuilder)
            ->selectRaw('SUM(amount) as amount')
            ->whereRaw("sale_date <= '{$previousDateEndStr}'")
            ->get()->first()?->amount ?? 0
        ;

        $leadSalesCount = (clone $queryBuilder)
            ->whereRaw("sale_date <= '{$currentDateEndStr}'")
            ->whereRaw("sale_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousLeadSalesCount = (clone $queryBuilder)
            ->whereRaw("sale_date <= '{$previousDateEndStr}'")
            ->whereRaw("sale_date >= '{$previousDateStartStr}'")
            ->count()
        ;

        $leadSalesAmount = (clone $queryBuilder)
            ->selectRaw('SUM(amount) as amount')
            ->whereRaw("sale_date <= '{$currentDateEndStr}'")
            ->whereRaw("sale_date >= '{$currentDateStartStr}'")
            ->get()->first()?->amount ?? 0
        ;
        $previousLeadSalesAmount = (clone $queryBuilder)
            ->selectRaw('SUM(amount) as amount')
            ->whereRaw("sale_date <= '{$previousDateEndStr}'")
            ->whereRaw("sale_date >= '{$previousDateStartStr}'")
            ->get()->first()?->amount ?? 0
        ;

        $leadsQueryBuilder = DB::table('Leads')->where('client_id', $client->id)->whereNull('deleted_at');
        $leadsCount = (clone $leadsQueryBuilder)
            ->whereRaw("lead_created_at <= '{$currentDateEndStr}'")
            ->whereRaw("lead_created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousLeadsCount = (clone $leadsQueryBuilder)
            ->whereRaw("lead_created_at <= '{$previousDateEndStr}'")
            ->whereRaw("lead_created_at >= '{$previousDateStartStr}'")
            ->count()
        ;
        $leadsWithSaleCount = (clone $leadsQueryBuilder)
            ->whereExists(function ($query) {
                $query->select('id')->from('LeadsSales')
                    ->whereColumn('Leads.id', 'LeadsSales.lead_id')
                    ->whereNull('LeadsSales.deleted_at')
                ;
            })
            ->whereRaw("Leads.lead_created_at <= '{$currentDateEndStr}'")
            ->whereRaw("Leads.lead_created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousLeadsWithSaleCount = (clone $leadsQueryBuilder)
            ->whereExists(function ($query) {
                $query->select('id')->from('LeadsSales')
                    ->whereColumn('Leads.id', 'LeadsSales.lead_id')
                    ->whereNull('LeadsSales.deleted_at')
                ;
            })
            ->whereRaw("Leads.lead_created_at <= '{$previousDateEndStr}'")
            ->whereRaw("Leads.lead_created_at >= '{$previousDateStartStr}'")
            ->count()
        ;

        $percentageDiff = 0;
        if ($totalLeadSalesAmount) {
            $percentageDiff = ($totalLeadSalesAmount - $previousTotalLeadSalesAmount) * 100 / $totalLeadSalesAmount;
        }
        $totalLeadSalesMetrics = [
            'percentage_diff' => $percentageDiff,
            'amount' => (int) $totalLeadSalesAmount,
            'previous_amount' => $previousTotalLeadSalesAmount,
        ];

        $percentageDiff = 0;
        $leadSalesMetrics = [];
        if ($leadSalesAmount) {
            $percentageDiff = ($leadSalesAmount - $previousLeadSalesAmount) * 100 / $leadSalesAmount;
        }
        $leadSalesMetrics['amount'] = $leadSalesAmount;
        $leadSalesMetrics['amount_percentage_diff'] = $percentageDiff;
        $leadSalesMetrics['previous_amount'] = (int) $previousLeadSalesAmount;

        $percentageDiff = 0;
        if ($leadSalesCount) {
            $percentageDiff = ($leadSalesCount - $previousLeadSalesCount) * 100 / $leadSalesCount;
        }
        $leadSalesMetrics['count'] = $leadSalesCount;
        $leadSalesMetrics['count_percentage_diff'] = $percentageDiff;
        $leadSalesMetrics['previous_count'] = $previousLeadSalesCount;

        $leadSalesAverageTicket = 0;
        $averageTicketPercentageDiff = 0;
        $previousLeadSalesAverageTicket = 0;
        if ($leadSalesCount) {
            $leadSalesAverageTicket = $leadSalesAmount / $leadSalesCount;
        }
        if ($previousLeadSalesCount) {
            $previousLeadSalesAverageTicket = $previousLeadSalesAmount / $previousLeadSalesCount;
        }
        if ($leadSalesAverageTicket) {
            $diff = $leadSalesAverageTicket - $previousLeadSalesCount;
            $averageTicketPercentageDiff = $diff * 100 / $leadSalesAverageTicket;
        }
        $leadSalesMetrics['average_ticket'] = round($leadSalesAverageTicket, 1);
        $leadSalesMetrics['average_ticket_percentage_diff'] = $averageTicketPercentageDiff;
        $leadSalesMetrics['previous_average_ticket'] = round($previousLeadSalesAverageTicket, 1);


        $leadSalesCloseRate = 0;
        $closeRatePercentageDiff = 0;
        $previousLeadSalesCloseRate = 0;
        // if ($leadsCount) {
        //     $leadSalesCloseRate = $leadsWithSaleCount * 100 / $leadsCount;
        // }
        // if ($previousLeadSalesCount) {
        //     $diff = ($leadsWithSaleCount - $previousLeadSalesCount);
        //     $closeRatePercentageDiff = $diff * 100 / $previousLeadSalesCount;
        // }
        // if ($previousLeadsCount) {
        //     $previousLeadSalesCloseRate = $previousLeadsWithSaleCount * 100 / $previousLeadsCount;
        // }
        $leadsWithSaleMetrics = [
            'count' => $leadsWithSaleCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousLeadsWithSaleCount,
            
            'close_rate' => round($leadSalesCloseRate, 1),
            'close_rate_percentage_diff' => $closeRatePercentageDiff,
            'previous_close_rate' => round($previousLeadSalesCloseRate, 1),
        ];

        $metrics = [
            'lead_sales' => $leadSalesMetrics,
            'leads_with_sale' => $leadsWithSaleMetrics,
            'total_lead_sales' => $totalLeadSalesMetrics,
        ];
        $metrics = $this->formatPercentages($metrics);
        return $metrics;
    }


    public function proposalInfoMetrics(PeriodDTO $periodDTO, array $opts = []): array
    {
        $client = $this->getClient();
        
        $currentDateEndStr = $periodDTO->currentDateEnd->format('Y-m-d H:i:s');
        $previousDateEndStr = $periodDTO->previousDateEnd->format('Y-m-d H:i:s');
        $currentDateStartStr = $periodDTO->currentDateStart->format('Y-m-d H:i:s');
        $previousDateStartStr = $periodDTO->previousDateStart->format('Y-m-d H:i:s');
        
        $queryBuilder = DB::table('ProposalsInfo')->where('client_id', $client->id)->whereNull('deleted_at');
        
        $totalProposalsInfoCount = (clone $queryBuilder)
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->count()
        ;
        $previousTotalProposalsInfoCount = (clone $queryBuilder)
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->count()
        ;

        $proposalsInfoCount = (clone $queryBuilder)
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousProposalsInfoCount = (clone $queryBuilder)
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->count()
        ;

        $proposalsInfoAmount = (clone $queryBuilder)
            ->selectRaw('SUM(amount) as amount')
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->get()->first()?->amount ?? 0
        ;
        $previousProposalsInfoAmount = (clone $queryBuilder)
            ->selectRaw('SUM(amount) as amount')
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->get()->first()?->amount ?? 0
        ;

        $proposalsInfoSentByEmailCount = (clone $queryBuilder)
            ->whereNotNull('email_ids')
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousProposalsInfoSentByEmailCount = (clone $queryBuilder)
            ->whereNotNull('email_ids')
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->count()
        ;
        
        $proposalsInfoSentByWhatsAppCount = (clone $queryBuilder)
            ->whereNotNull('whatsapp_sending_id')
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousProposalsInfoSentByWhatsAppCount = (clone $queryBuilder)
            ->whereNotNull('whatsapp_sending_id')
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->count()
        ;

        $percentageDiff = 0;
        if ($totalProposalsInfoCount) {
            $diff = $totalProposalsInfoCount - $previousTotalProposalsInfoCount;
            $percentageDiff = $diff * 100 / $totalProposalsInfoCount;
        }
        $totalProposalsMetrics = [
            'count' => $totalProposalsInfoCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousTotalProposalsInfoCount,
        ];
        

        $percentageDiff = 0;
        if ($proposalsInfoCount) {
            $percentageDiff = ($proposalsInfoCount - $previousProposalsInfoCount) * 100 / $proposalsInfoCount;
        }
        $proposalsInfoMetrics = [];
        $proposalsInfoMetrics['count'] = $proposalsInfoCount;
        $proposalsInfoMetrics['percentage_diff'] = $percentageDiff;
        $proposalsInfoMetrics['previous_count'] = $previousProposalsInfoCount;

        $percentageDiff = 0;
        if ($proposalsInfoAmount) {
            $percentageDiff = ($proposalsInfoAmount - $previousProposalsInfoAmount) * 100 / $proposalsInfoAmount;
        }
        $proposalsInfoMetrics['amount'] = (int) $proposalsInfoAmount;
        $proposalsInfoMetrics['amount_percentage_diff'] = $percentageDiff;
        $proposalsInfoMetrics['previous_amount'] = $previousProposalsInfoAmount;

        $percentageDiff = 0;
        if ($proposalsInfoSentByEmailCount) {
            $diff = $proposalsInfoSentByEmailCount - $previousProposalsInfoSentByEmailCount;
            $percentageDiff = ($diff * 100) / $proposalsInfoSentByEmailCount;
        }
        $proposalsInfoMetrics['sent_by_email_count'] = $proposalsInfoSentByEmailCount;
        $proposalsInfoMetrics['sent_by_email_count_percentage_diff'] = $percentageDiff;
        $proposalsInfoMetrics['previous_sent_by_email_count'] = $previousProposalsInfoSentByEmailCount;

        $percentageDiff = 0;
        if ($proposalsInfoSentByWhatsAppCount) {
            $diff = $proposalsInfoSentByWhatsAppCount - $previousProposalsInfoSentByWhatsAppCount;
            $percentageDiff = ($diff * 100) / $proposalsInfoSentByWhatsAppCount;
        }
        $proposalsInfoMetrics['sent_by_whatsapp_count_percentage_diff'] = $percentageDiff;
        $proposalsInfoMetrics['sent_by_whatsapp_count'] = $proposalsInfoSentByWhatsAppCount;
        $proposalsInfoMetrics['previous_sent_by_whatsapp_count'] = $previousProposalsInfoSentByWhatsAppCount;

        $metrics = [
            'proposals_info' => $proposalsInfoMetrics,
            'total_proposals_info' => $totalProposalsMetrics,
        ];
        $metrics = $this->formatPercentages($metrics);
        return $metrics;
    }


    public function sendingMetrics(PeriodDTO $periodDTO, array $opts = []): array
    {
        $client = $this->getClient();
        
        $currentDateEndStr = $periodDTO->currentDateEnd->format('Y-m-d H:i:s');
        $previousDateEndStr = $periodDTO->previousDateEnd->format('Y-m-d H:i:s');
        $currentDateStartStr = $periodDTO->currentDateStart->format('Y-m-d H:i:s');
        $previousDateStartStr = $periodDTO->previousDateStart->format('Y-m-d H:i:s');
        
        $emailsQuery = DB::table('Emails')->where('client_id', $client->id)
            ->whereNotNull('sent_date')
            ->whereNull('deleted_at')
        ;
        
        $totalSentEmailsCount = (clone $emailsQuery)
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->count()
        ;
        $previousTotalSentEmailsCount = (clone $emailsQuery)
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->count()
        ;

        $manualSentEmailsCount = (clone $emailsQuery)->whereNull('automation_log_id')
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousManualSentEmailsCount = (clone $emailsQuery)->whereNull('automation_log_id')
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->count()
        ;

        $automationSentEmailsCount = (clone $emailsQuery)->whereNotNull('automation_log_id')
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAutomationSentEmailsCount = (clone $emailsQuery)->whereNotNull('automation_log_id')
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->count()
        ;

        $openedEmailsCount = (clone $emailsQuery)->whereNotNull('opened_date')
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousOpenedEmailsCount = (clone $emailsQuery)->whereNotNull('opened_date')
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->count()
        ;

        $wapQuery = DB::table('WhatsAppSendingMessages')->where('client_id', $client->id)
            ->whereNotNull('sent_date')
            ->whereNull('deleted_at')
        ;

        $totalSentWhatsAppCount = (clone $wapQuery)
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->count()
        ;
        $previousTotalSentWhatsAppCount = (clone $wapQuery)
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->count()
        ;

        $manualSentWhatsAppCount = (clone $wapQuery)->whereNull('wautomation_log_id')
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousManualSentWhatsAppCount = (clone $wapQuery)->whereNull('wautomation_log_id')
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->count()
        ;

        $automationSentWhatsAppCount = (clone $wapQuery)->whereNotNull('wautomation_log_id')
            ->whereRaw("sent_date <= '{$currentDateEndStr}'")
            ->whereRaw("sent_date >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAutomationSentWhatsAppCount = (clone $wapQuery)->whereNotNull('wautomation_log_id')
            ->whereRaw("sent_date <= '{$previousDateEndStr}'")
            ->whereRaw("sent_date >= '{$previousDateStartStr}'")
            ->count()
        ;

        $percentageDiff = 0;
        if ($totalSentEmailsCount) {
            $percentageDiff = ($totalSentEmailsCount - $previousTotalSentEmailsCount) * 100 / $totalSentEmailsCount;
        }
        $totalSentEmailsMetrics = [
            'count' => $totalSentEmailsCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousTotalSentEmailsCount,
        ];

        $percentageDiff = 0;
        if ($manualSentEmailsCount) {
            $percentageDiff = ($manualSentEmailsCount - $previousManualSentEmailsCount) * 100 / $manualSentEmailsCount;
        }
        $manualSentEmailMetrics = [
            'count' => $manualSentEmailsCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousManualSentEmailsCount,
        ];

        $percentageDiff = 0;
        if ($automationSentEmailsCount) {
            $diff = $automationSentEmailsCount - $previousAutomationSentEmailsCount;
            $percentageDiff = ($diff * 100) / $automationSentEmailsCount;
        }
        $automationSentEmailMetrics = [
            'percentage_diff' => $percentageDiff,
            'count' => $automationSentEmailsCount,
            'previous_count' => $previousAutomationSentEmailsCount,
        ];

        $percentageDiff = 0;
        $openedEmailsPercentage = 0;
        $totalSentEmails = $manualSentEmailsCount + $automationSentEmailsCount;
        if ($openedEmailsCount) {
            $percentageDiff = ($openedEmailsCount - $previousOpenedEmailsCount) * 100 / $openedEmailsCount;
        }
        if ($totalSentEmails) {
            $openedEmailsPercentage = ($openedEmailsCount * 100) / $totalSentEmails;
        }
        $openedEmailMetrics = [
            'count' => $openedEmailsCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousOpenedEmailsCount,
            'percentage' => round($openedEmailsPercentage, 1),
        ];

        $percentageDiff = 0;
        if ($totalSentWhatsAppCount) {
            $diff = $totalSentWhatsAppCount - $previousTotalSentWhatsAppCount;
            $percentageDiff = ($diff * 100) / $totalSentWhatsAppCount;
        }
        $totalSentWhatsAppMetrics = [
            'count' => $totalSentWhatsAppCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousTotalSentWhatsAppCount,
        ];

        
        $percentageDiff = 0;
        if ($manualSentWhatsAppCount) {
            $diff = $manualSentWhatsAppCount - $previousManualSentWhatsAppCount;
            $percentageDiff = ($diff * 100) / $manualSentWhatsAppCount;
        }
        $manualSentWhatsAppMetrics = [
            'count' => $manualSentWhatsAppCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousManualSentWhatsAppCount,
        ];

        $percentageDiff = 0;
        if ($automationSentWhatsAppCount) {
            $diff = $automationSentWhatsAppCount - $previousAutomationSentWhatsAppCount;
            $percentageDiff = ($diff * 100) / $automationSentWhatsAppCount;
        }
        $automationSentWhatsAppMetrics = [
            'percentage_diff' => $percentageDiff,
            'count' => $automationSentWhatsAppCount,
            'previous_count' => $previousAutomationSentWhatsAppCount,
        ];
        
        $metrics = [
            'opened_emails' => $openedEmailMetrics,
            'total_sent_emails' => $totalSentEmailsMetrics,
            'manual_sent_emails' => $manualSentEmailMetrics,
            'total_sent_whatsapp' => $totalSentWhatsAppMetrics,
            'manual_sent_whatsapp' => $manualSentWhatsAppMetrics,
            'automation_sent_emails' => $automationSentEmailMetrics,
            'automation_sent_whatsapp' => $automationSentWhatsAppMetrics,
        ];
        $metrics = $this->formatPercentages($metrics);
        return $metrics;
    }


    public function automationMetrics(PeriodDTO $periodDTO, array $opts = []): array
    {
        $minutesPerAutomation = 10;
        $client = $this->getClient();
        $currentDateEndStr = $periodDTO->currentDateEnd->format('Y-m-d H:i:s');
        $previousDateEndStr = $periodDTO->previousDateEnd->format('Y-m-d H:i:s');
        $currentDateStartStr = $periodDTO->currentDateStart->format('Y-m-d H:i:s');
        $previousDateStartStr = $periodDTO->previousDateStart->format('Y-m-d H:i:s');

        $autQueryBuilder = DB::table('AutomationsLogs')
            ->where('client_id', $client->id)
            ->where('is_fully_applied', true)
            ->whereNull('deleted_at')
        ;
        
        $totalAppliedAutomationsCount = (clone $autQueryBuilder)
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->count()
        ;
        $previousTotalAppliedAutomationsCount = (clone $autQueryBuilder)
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->count()
        ;

        $appliedAutomationsCount = (clone $autQueryBuilder)
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->whereRaw("created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAppliedAutomationsCount = (clone $autQueryBuilder)
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->whereRaw("created_at >= '{$previousDateStartStr}'")
            ->count()
        ;

        $appliedAutomationsNewLeadCount = (clone $autQueryBuilder)
            ->whereNotNull('automation_new_lead_id')
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->whereRaw("created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAppliedAutomationsNewLeadCount = (clone $autQueryBuilder)
            ->whereNotNull('automation_new_lead_id')
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->whereRaw("created_at >= '{$previousDateStartStr}'")
            ->count()
        ;

        $appliedAutomationsSequenceCount = (clone $autQueryBuilder)
            ->whereNotNull('automation_email_send_id')
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->whereRaw("created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAppliedAutomationsSequenceCount = (clone $autQueryBuilder)
            ->whereNotNull('automation_email_send_id')
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->whereRaw("created_at >= '{$previousDateStartStr}'")
            ->count()
        ;

        $appliedAutomationsTaskCount = (clone $autQueryBuilder)
            ->whereNotNull('automation_task_id')
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->whereRaw("created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAppliedAutomationsTaskCount = (clone $autQueryBuilder)
            ->whereNotNull('automation_task_id')
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->whereRaw("created_at >= '{$previousDateStartStr}'")
            ->count()
        ;

        $appliedAutomationsProposalCount = (clone $autQueryBuilder)
            ->where(function ($q) {
                $q->whereNotNull('automation_proposal_id')
                    ->orWhereNotNull('automation_proposal_resend_rule_id')
                    ->orWhereNotNull('automation_proposal_interaction_rule_id')
                ;
            })
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->whereRaw("created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAppliedAutomationsProposalCount = (clone $autQueryBuilder)
            ->where(function ($q) {
                $q->whereNotNull('automation_proposal_id')
                    ->orWhereNotNull('automation_proposal_resend_rule_id')
                    ->orWhereNotNull('automation_proposal_interaction_rule_id')
                ;
            })
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->whereRaw("created_at >= '{$previousDateStartStr}'")
            ->count()
        ;

        
        $wAutQueryBuilder = DB::table('WAutomationsLogs')
            ->where('client_id', $client->id)
            ->where('is_fully_applied', true)
            ->whereNull('deleted_at')
        ;

        $totalAppliedWAutomationsCount = (clone $wAutQueryBuilder)
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->count()
        ;
        $previousTotalAppliedWAutomationsCount = (clone $wAutQueryBuilder)
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->count()
        ;

        $appliedWAutomationsCount = (clone $wAutQueryBuilder)
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->whereRaw("created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAppliedWAutomationsCount = (clone $wAutQueryBuilder)
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->whereRaw("created_at >= '{$previousDateStartStr}'")
            ->count()
        ;

        $appliedWAutomationsSequenceCount = (clone $wAutQueryBuilder)
            ->whereNotNull('wautomation_sequence_id')
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->whereRaw("created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAppliedWAutomationsSequenceCount = (clone $wAutQueryBuilder)
            ->whereNotNull('wautomation_sequence_id')
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->whereRaw("created_at >= '{$previousDateStartStr}'")
            ->count()
        ;

        $appliedWAutomationsProposalCount = (clone $wAutQueryBuilder)
            ->where(function ($q) {
                $q->whereNotNull('wautomation_proposal_id')->orWhereNotNull('wautomation_proposal_resend_rule_id');
            })
            ->whereRaw("created_at <= '{$currentDateEndStr}'")
            ->whereRaw("created_at >= '{$currentDateStartStr}'")
            ->count()
        ;
        $previousAppliedWAutomationsProposalCount = (clone $wAutQueryBuilder)
            ->where(function ($q) {
                $q->whereNotNull('wautomation_proposal_id')->orWhereNotNull('wautomation_proposal_resend_rule_id');
            })
            ->whereRaw("created_at <= '{$previousDateEndStr}'")
            ->whereRaw("created_at >= '{$previousDateStartStr}'")
            ->count()
        ;
        
        $percentageDiff = 0;
        $totalAppliedAutCount = $totalAppliedAutomationsCount + $totalAppliedWAutomationsCount;
        $previousTotalAppliedAutCount = $previousTotalAppliedAutomationsCount + $previousTotalAppliedWAutomationsCount;
        if ($totalAppliedAutCount) {
            $diffCount = $totalAppliedAutCount - $previousTotalAppliedAutCount;
            $percentageDiff = ($diffCount) * 100 / $totalAppliedAutCount;
        }
        $totalSavedHoursCount = intval(($minutesPerAutomation * $totalAppliedAutCount) / 60);
        $previousTotalSavedHoursCount = intval(($minutesPerAutomation * $previousTotalAppliedAutCount) / 60);
        $totalSavedHoursMetrics = [
            'count' => $totalSavedHoursCount,
            'percentage_diff' => (int) $percentageDiff,
            'previous_count' => $previousTotalSavedHoursCount,
            'applied_automations_count' => $totalAppliedAutCount,
        ];

        $percentageDiff = 0;
        $appliedAutCount = $appliedAutomationsCount + $appliedWAutomationsCount;
        $previousAppliedAutCount = $previousAppliedAutomationsCount + $previousAppliedWAutomationsCount;
        if ($appliedAutCount) {
            $diffCount = $appliedAutCount - $previousAppliedAutCount;
            $percentageDiff = ($diffCount) * 100 / $appliedAutCount;
        }
        $savedHoursCount = intval(($minutesPerAutomation * $appliedAutCount) / 60);
        $previousSavedHoursCount = intval(($minutesPerAutomation * $previousAppliedAutCount) / 60);
        $savedHoursMetrics = [
            'count' => $savedHoursCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousSavedHoursCount,
            'applied_automations_count' => $appliedAutCount,
        ];
        $appliedAutomationsMetrics = [
            'count' => $appliedAutCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $previousAppliedAutCount,
        ];

        $percentageDiff = 0;
        if ($appliedAutomationsNewLeadCount) {
            $diffCount = $appliedAutomationsNewLeadCount - $previousAppliedAutomationsNewLeadCount;
            $percentageDiff = ($diffCount) * 100 / $appliedAutomationsNewLeadCount;
        }
        $appliedAutomationsNewLeadMetrics = [
            'percentage_diff' => $percentageDiff,
            'count' => $appliedAutomationsNewLeadCount,
            'previous_count' => $previousAppliedAutomationsNewLeadCount,
        ];

        $percentageDiff = 0;
        $autProposalCount = $appliedAutomationsProposalCount + $appliedWAutomationsProposalCount;
        $prevAutProposalCount = $previousAppliedAutomationsProposalCount + $previousAppliedWAutomationsProposalCount;
        if ($autProposalCount) {
            $percentageDiff = ($autProposalCount - $prevAutProposalCount) * 100 / $autProposalCount;
        }
        $appliedAutomationsProposalMetrics = [
            'count' => $autProposalCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $prevAutProposalCount,
        ];

        $percentageDiff = 0;
        $autSequenceCount = $appliedAutomationsSequenceCount + $appliedWAutomationsSequenceCount;
        $prevAutSequenceCount = $previousAppliedAutomationsSequenceCount + $previousAppliedWAutomationsSequenceCount;
        if ($autSequenceCount) {
            $percentageDiff = ($autSequenceCount - $prevAutSequenceCount) * 100 / $autSequenceCount;
        }
        $appliedAutomationSequenceMetrics = [
            'count' => $autSequenceCount,
            'percentage_diff' => $percentageDiff,
            'previous_count' => $prevAutSequenceCount,
        ];

        $percentageDiff = 0;
        if ($appliedAutomationsTaskCount) {
            $diffCount = $appliedAutomationsTaskCount - $previousAppliedAutomationsTaskCount;
            $percentageDiff = ($diffCount * 100) / $appliedAutomationsTaskCount;
        }
        $appliedAutomationTaskMetrics = [
            'percentage_diff' => $percentageDiff,
            'count' => $appliedAutomationsTaskCount,
            'previous_count' => $previousAppliedAutomationsTaskCount,
        ];

        $totalAppliedAutomationsChartInfo = [];
        $autChartQuery = clone $autQueryBuilder;
        $wAutChartQuery = clone $wAutQueryBuilder;
        foreach ($periodDTO->previousPeriods as $i => $prevPeriod) {
            $dateStr = $prevPeriod['date_start']->format('Y-m-d');
            $rowLabel = 'count_' . $prevPeriod['date_start']->format('Y_m_d');
            $autChartQuery->selectRaw("SUM(IF(created_at <= ?, 1, 0)) as {$rowLabel}", [$dateStr]);
            $wAutChartQuery->selectRaw("SUM(IF(created_at <= ?, 1, 0)) as {$rowLabel}", [$dateStr]);
            $totalAppliedAutomationsChartInfo[$i] = ['period' => $prevPeriod, 'data' => ['count' => 0]];
        }
        $autQueryResult = $autChartQuery->first();
        $wAutQueryResult = $wAutChartQuery->first();
        foreach ($periodDTO->previousPeriods as $i => $prevPeriod) {
            $rowLabel = 'count_' . $prevPeriod['date_start']->format('Y_m_d');
            $autCount = (int) $autQueryResult->$rowLabel;
            $wAutCount = (int) $wAutQueryResult->$rowLabel;
            $totalAppliedAutomationsChartInfo[$i]['data']['count'] = $autCount + $wAutCount;
        }

        $appliedAutomationsChartInfo = [];
        $autChartQuery = clone $autQueryBuilder;
        $wAutChartQuery = clone $wAutQueryBuilder;
        foreach ($periodDTO->previousPeriods as $i => $prevPeriod) {
            $dateEndStr = $prevPeriod['date_end']->format('Y-m-d');
            $dateStartStr = $prevPeriod['date_start']->format('Y-m-d');
            $rowLabel = 'count_' . $prevPeriod['date_start']->format('Y_m_d');
            $autChartQuery->selectRaw(
                "SUM(IF(created_at BETWEEN ? AND ?, 1, 0)) as {$rowLabel}", [$dateStartStr, $dateEndStr]
            );
            $wAutChartQuery->selectRaw(
                "SUM(IF(created_at BETWEEN ? AND ?, 1, 0)) as {$rowLabel}", [$dateStartStr, $dateEndStr]
            );
            $appliedAutomationsChartInfo[$i] = ['period' => $prevPeriod, 'data' => ['count' => 0]];
        }
        $autQueryResult = $autChartQuery->first();
        $wAutQueryResult = $wAutChartQuery->first();
        foreach ($periodDTO->previousPeriods as $i => $prevPeriod) {
            $rowLabel = 'count_' . $prevPeriod['date_start']->format('Y_m_d');
            $autCount = (int) $autQueryResult->$rowLabel;
            $wAutCount = (int) $wAutQueryResult->$rowLabel;
            $appliedAutomationsChartInfo[$i]['data']['count'] = $autCount + $wAutCount;
        }

        $metrics = [
            'saved_hours' => $savedHoursMetrics,
            'applied' => $appliedAutomationsMetrics,
            'total_saved_hours' => $totalSavedHoursMetrics,
            'automation_task' => $appliedAutomationTaskMetrics,
            'automation_new_lead' => $appliedAutomationsNewLeadMetrics,
            'automation_sequence' => $appliedAutomationSequenceMetrics,
            'automation_proposal' => $appliedAutomationsProposalMetrics,
            'applied_automations_chart_info' => array_values($appliedAutomationsChartInfo),
            'total_applied_automations_chart_info' => array_values($totalAppliedAutomationsChartInfo),
        ];
        $metrics = $this->formatPercentages($metrics);
        return $metrics;
    }


    protected function formatPercentages(array $metrics): array
    {
        foreach ($metrics as $key => &$value) {
            if (is_array($value)) {
                $value = $this->formatPercentages($value);
            } elseif (strpos($key, 'percentage') !== false && is_numeric($value)) {
                if (abs($value) < 10) {
                    $value = round($value, 1);
                } else {
                    $value = (int) $value;
                }
            }
        }
        return $metrics;
    }

}
