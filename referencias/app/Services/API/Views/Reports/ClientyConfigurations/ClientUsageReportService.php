<?php

namespace App\Services\API\Views\Reports\ClientyConfigurations;

use DateTime;
use Exception;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Models\MongoDB\EventLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\ClientService;
use App\Services\API\ClientUsageLogService;


class ClientUsageReportService
{

    public function __construct(
        protected readonly ClientUsageLogService $clientUsageLogService,
        protected readonly ClientService $clientService,
    ) {
    }


    public function userLevelReport(Client $client, array $options): Collection
    {
        $this->validateFilters($options);
        
        $dateEnd = $options['filters']['date_end'];
        $dateStart = $options['filters']['date_start'];
        $dateEndStr = $dateEnd->format('Y-m-d H:i:s');
        $dateStartStr = $dateStart->format('Y-m-d H:i:s');
        
        $onlyEnabledUsers = $options['onlyEnabledUsers'] ?? false;

        $users = $onlyEnabledUsers ? $client->enabledUsers : $client->users;
        $report = new Collection();
        foreach ($users as $user) {
            $leads = DB::table('Leads')
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('client_id', $client->id)
                ->whereRaw("lead_created_at <= '{$dateEndStr}'")
                ->whereRaw("lead_created_at >= '{$dateStartStr}'")
                ->select(['id', 'is_manually_created'])
                ->get(['id', 'is_manually_created'])
            ;
            $manualLeadsCount = $leads->where('is_manually_created', true)->count();
            $automaticLeadsCount = $leads->where('is_manually_created', false)->count();

            $wapSendings = DB::table('WhatsAppSendings')
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('client_id', $client->id)
                ->whereRaw("send_date <= '{$dateEndStr}'")
                ->whereRaw("send_date >= '{$dateStartStr}'")
                ->select(['id', 'is_massive', 'is_automation', 'is_proposal'])
                ->get(['id', 'is_massive', 'is_automation', 'is_proposal'])
            ;
            $proposalWapSendingIds = $wapSendings
                ->where('is_proposal', true)
                ->where('is_automation', false)
                ->pluck('id')
            ;
            $massiveWapSendingIds = $wapSendings
                ->where('is_massive', true)
                ->where('is_proposal', false)
                ->where('is_automation', false)
                ->pluck('id')
            ;
            $individualWapSendingIds = $wapSendings
                ->where('is_massive', false)
                ->where('is_proposal', false)
                ->where('is_automation', false)
                ->pluck('id')
            ;
            $automationWapSendingIds = $wapSendings
                ->where('is_automation', true)
                ->pluck('id')
            ;

            $manualProposalsCount = DB::table('ProposalsInfo')
                ->whereNull('email_ids')
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('client_id', $client->id)
                ->whereNull('whatsapp_sending_id')
                ->whereRaw("sent_date <= '{$dateEndStr}'")
                ->whereNull('whatsapp_sending_message_ids')
                ->whereRaw("sent_date >= '{$dateStartStr}'")
                ->count()
            ;

            $proposalWapSendingMsgsCount = 0;
            $automationWapSendingMsgsCount = 0;
            $individualWapSendingMsgsCount = 0;
            $massiveWapSendingMsgsCount = $massiveWapSendingIds->count();

            if ($individualWapSendingIds->isNotEmpty()) {
                $individualWapSendingMsgsCount = DB::table('WhatsAppSendingMessages')
                    ->whereNull('deleted_at')
                    ->where('client_id', $client->id)
                    ->whereIn('whatsapp_sending_id', $individualWapSendingIds)
                    ->count()
                ;
            }
            if ($proposalWapSendingIds->isNotEmpty()) {
                $proposalWapSendingMsgsCount = DB::table('WhatsAppSendingMessages')
                    ->whereNull('deleted_at')
                    ->where('client_id', $client->id)
                    ->whereIn('whatsapp_sending_id', $proposalWapSendingIds)
                    ->count()
                ;
            }
            if ($automationWapSendingIds->isNotEmpty()) {
                $automationWapSendingMsgsCount = DB::table('WhatsAppSendingMessages')
                    ->whereNull('deleted_at')
                    ->where('client_id', $client->id)
                    ->whereIn('whatsapp_sending_id', $automationWapSendingIds)
                    ->count()
                ;
            }

            $massiveEmailsCount = DB::table('Emails')
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('is_proposal', false)
                ->whereNull('automation_log_id')
                ->where('client_id', $client->id)
                ->whereNotNull('external_massive_id')
                ->whereRaw("sent_date <= '{$dateEndStr}'")
                ->whereRaw("sent_date >= '{$dateStartStr}'")
                ->selectRaw('COUNT(DISTINCT external_massive_id) as count')
                ->first()
                ->count
            ;
            $individualEmailsCount = DB::table('Emails')
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('is_proposal', false)
                ->whereNull('automation_log_id')
                ->where('client_id', $client->id)
                ->whereNull('external_massive_id')
                ->whereRaw("sent_date <= '{$dateEndStr}'")
                ->whereNotNull('individual_lead_send_hash')
                ->whereRaw("sent_date >= '{$dateStartStr}'")
                ->selectRaw('COUNT(DISTINCT individual_lead_send_hash) as count')
                ->first()
                ->count
            ;
            $proposalEmailsCount = DB::table('Emails')
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('is_proposal', true)
                ->whereNull('automation_log_id')
                ->where('client_id', $client->id)
                ->whereRaw("sent_date <= '{$dateEndStr}'")
                ->whereRaw("sent_date >= '{$dateStartStr}'")
                ->selectRaw('COUNT(DISTINCT individual_lead_send_hash) as count')
                ->first()
                ->count
            ;
            $automationEmailsCount = DB::table('Emails')
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->whereNotNull('automation_log_id')
                ->where('client_id', $client->id)
                ->whereRaw("sent_date <= '{$dateEndStr}'")
                ->whereRaw("sent_date >= '{$dateStartStr}'")
                ->selectRaw('COUNT(DISTINCT individual_lead_send_hash) as count')
                ->first()
                ->count
            ;

            $salesCount = DB::table('LeadsSales')
                ->select(['id'])
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('client_id', $client->id)
                ->whereRaw("created_at <= '{$dateEndStr}'")
                ->whereRaw("created_at >= '{$dateStartStr}'")
                ->count()
            ;
            $tasksCount = DB::table('Tasks')
                ->select(['id'])
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('client_id', $client->id)
                ->whereRaw("created_at <= '{$dateEndStr}'")
                ->whereRaw("created_at >= '{$dateStartStr}'")
                ->count()
            ;

            $visitedScreensUsageLogs = $this->clientUsageLogService->findVisitedScreenByUserBetweenDates(
                $user, $dateStart, $dateEnd
            );

            $statusChangeCount = EventLog::query()
                ->where('system', 'clienty_crm')
                ->where('event', 'lead_status_updated')
                ->where('log.user.id', $user->id)
                ->where('log.client_id', $user->client_id)
                ->where('createdAtTs', '<=', $dateEnd->getTimestamp())
                ->where('createdAtTs', '>=', $dateStart->getTimestamp())
                ->count()
            ;
            $standardWapHitsCount = EventLog::query()
                ->where('system', 'clienty_crm')
                ->where('event', 'whatsapp_message_sent')
                ->where('log.user.id', $user->id)
                ->where('log.client_id', $user->client_id)
                ->where('createdAtTs', '<=', $dateEnd->getTimestamp())
                ->where('createdAtTs', '>=', $dateStart->getTimestamp())
                ->count()
            ;

            $reportsScreensCount = $visitedScreensUsageLogs->where('data.category', 'reports')->count();
            // $templatesScreensCount = $visitedScreensUsageLogs->where('data.category', 'templates')->count();
            // $configusScreensCount = $visitedScreensUsageLogs->where('data.category', 'configurations')->count();

            $reportRow = [
                'user' => $user->only(['id', 'name', 'last_name', 'username']),
                'salesCount' => $salesCount,
                'tasksCount' => $tasksCount,
                'manualLeadsCount' => $manualLeadsCount,
                'statusChangeCount' => $statusChangeCount,
                'massiveEmailsCount' => $massiveEmailsCount,
                'proposalEmailsCount' => $proposalEmailsCount,
                'automaticLeadsCount' => $automaticLeadsCount,
                'standardWapHitsCount' => $standardWapHitsCount,
                'manualProposalsCount' => $manualProposalsCount,
                'individualEmailsCount' => $individualEmailsCount,
                'automationEmailsCount' => $automationEmailsCount,
                'visitedReportsScreensCount' => $reportsScreensCount,
                'massiveWapSendingMsgsCount' => $massiveWapSendingMsgsCount,
                'proposalWapSendingMsgsCount' => $proposalWapSendingMsgsCount,
                'proposalWapSendingMsgsCount' => $proposalWapSendingMsgsCount,
                'individualWapSendingMsgsCount' => $individualWapSendingMsgsCount,
                'automationWapSendingMsgsCount' => $automationWapSendingMsgsCount,
                // 'visitedTemplatesScreensCount' => $templatesScreensCount,
                // 'visitedConfigurationsScreensCount' => $configurationsScreensCount,
            ];
            $reportRow['totalHits'] = $this->calculateTotalHits($reportRow);
            $report->push($reportRow);
        }
        return $report;
    }



    public function allClientsReport(array $options): Collection
    {
        $report = new Collection();
        $clients = $this->clientService->findAllEnabled();
        foreach ($clients as $client) {
            $clientReportRow = $this->clientLevelReport($client, $options);
            $report->push($clientReportRow);
        }
        return $report;
    }


    public function clientLevelReport(Client $client, array $options): array
    {
        $this->validateFilters($options);
        
        $dateEnd = $options['filters']['date_end'];
        $dateStart = $options['filters']['date_start'];
        $dateEndStr = $dateEnd->format('Y-m-d H:i:s');
        $dateStartStr = $dateStart->format('Y-m-d H:i:s');

        $leads = DB::table('Leads')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->whereRaw("lead_created_at <= '{$dateEndStr}'")
            ->whereRaw("lead_created_at >= '{$dateStartStr}'")
            ->select(['id', 'is_manually_created'])
            ->get(['id', 'is_manually_created'])
        ;
        $manualLeadsCount = $leads->where('is_manually_created', true)->count();
        $automaticLeadsCount = $leads->where('is_manually_created', false)->count();

        $wapSendings = DB::table('WhatsAppSendings')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->whereRaw("send_date <= '{$dateEndStr}'")
            ->whereRaw("send_date >= '{$dateStartStr}'")
            ->select(['id', 'is_massive', 'is_automation', 'is_proposal'])
            ->get(['id', 'is_massive', 'is_automation', 'is_proposal'])
        ;
        $proposalWapSendingIds = $wapSendings
            ->where('is_proposal', true)
            ->where('is_automation', false)
            ->pluck('id')
        ;
        $massiveWapSendingIds = $wapSendings
            ->where('is_massive', true)
            ->where('is_proposal', false)
            ->where('is_automation', false)
            ->pluck('id')
        ;
        $individualWapSendingIds = $wapSendings
            ->where('is_massive', false)
            ->where('is_proposal', false)
            ->where('is_automation', false)
            ->pluck('id')
        ;
        $automationWapSendingIds = $wapSendings
            ->where('is_automation', true)
            ->pluck('id')
        ;

        $manualProposalsCount = DB::table('ProposalsInfo')
            ->whereNull('email_ids')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->whereNull('whatsapp_sending_id')
            ->whereRaw("sent_date <= '{$dateEndStr}'")
            ->whereNull('whatsapp_sending_message_ids')
            ->whereRaw("sent_date >= '{$dateStartStr}'")
            ->count()
        ;

        $proposalWapSendingMsgsCount = 0;
        $automationWapSendingMsgsCount = 0;
        $individualWapSendingMsgsCount = 0;
        $massiveWapSendingMsgsCount = $massiveWapSendingIds->count();

        if ($individualWapSendingIds->isNotEmpty()) {
            $individualWapSendingMsgsCount = DB::table('WhatsAppSendingMessages')
                ->whereNull('deleted_at')
                ->where('client_id', $client->id)
                ->whereIn('whatsapp_sending_id', $individualWapSendingIds)
                ->count()
            ;
        }
        if ($proposalWapSendingIds->isNotEmpty()) {
            $proposalWapSendingMsgsCount = DB::table('WhatsAppSendingMessages')
                ->whereNull('deleted_at')
                ->where('client_id', $client->id)
                ->whereIn('whatsapp_sending_id', $proposalWapSendingIds)
                ->count()
            ;
        }
        if ($automationWapSendingIds->isNotEmpty()) {
            $automationWapSendingMsgsCount = DB::table('WhatsAppSendingMessages')
                ->whereNull('deleted_at')
                ->where('client_id', $client->id)
                ->whereIn('whatsapp_sending_id', $automationWapSendingIds)
                ->count()
            ;
        }

        $massiveEmailsCount = DB::table('Emails')
            ->whereNull('deleted_at')
            ->where('is_proposal', false)
            ->whereNull('automation_log_id')
            ->where('client_id', $client->id)
            ->whereNotNull('external_massive_id')
            ->whereRaw("sent_date <= '{$dateEndStr}'")
            ->whereRaw("sent_date >= '{$dateStartStr}'")
            ->selectRaw('COUNT(DISTINCT external_massive_id) as count')
            ->first()
            ->count
        ;
        $individualEmailsCount = DB::table('Emails')
            ->whereNull('deleted_at')
            ->where('is_proposal', false)
            ->whereNull('automation_log_id')
            ->where('client_id', $client->id)
            ->whereNull('external_massive_id')
            ->whereRaw("sent_date <= '{$dateEndStr}'")
            ->whereNotNull('individual_lead_send_hash')
            ->whereRaw("sent_date >= '{$dateStartStr}'")
            ->selectRaw('COUNT(DISTINCT individual_lead_send_hash) as count')
            ->first()
            ->count
        ;
        $proposalEmailsCount = DB::table('Emails')
            ->whereNull('deleted_at')
            ->where('is_proposal', true)
            ->whereNull('automation_log_id')
            ->where('client_id', $client->id)
            ->whereRaw("sent_date <= '{$dateEndStr}'")
            ->whereRaw("sent_date >= '{$dateStartStr}'")
            ->selectRaw('COUNT(DISTINCT individual_lead_send_hash) as count')
            ->first()
            ->count
        ;
        $automationEmailsCount = DB::table('Emails')
            ->whereNull('deleted_at')
            ->whereNotNull('automation_log_id')
            ->where('client_id', $client->id)
            ->whereRaw("sent_date <= '{$dateEndStr}'")
            ->whereRaw("sent_date >= '{$dateStartStr}'")
            ->selectRaw('COUNT(DISTINCT individual_lead_send_hash) as count')
            ->first()
            ->count
        ;

        $salesCount = DB::table('LeadsSales')
            ->select(['id'])
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->whereRaw("created_at <= '{$dateEndStr}'")
            ->whereRaw("created_at >= '{$dateStartStr}'")
            ->count()
        ;
        $tasksCount = DB::table('Tasks')
            ->select(['id'])
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->whereRaw("created_at <= '{$dateEndStr}'")
            ->whereRaw("created_at >= '{$dateStartStr}'")
            ->count()
        ;

        $visitedScreensUsageLogs = $this->clientUsageLogService->findVisitedScreenByClientBetweenDates(
            $client, $dateStart, $dateEnd
        );
        
        $statusChangeCount = EventLog::query()
            ->where('system', 'clienty_crm')
            ->where('event', 'lead_status_updated')
            ->where('log.client_id', $client->id)
            ->where('createdAtTs', '<=', $dateEnd->getTimestamp())
            ->where('createdAtTs', '>=', $dateStart->getTimestamp())
            ->count()
        ;

        $standardWapHitsCount = EventLog::query()
            ->where('system', 'clienty_crm')
            ->where('log.client_id', $client->id)
            ->where('event', 'whatsapp_message_sent')
            ->where('createdAtTs', '<=', $dateEnd->getTimestamp())
            ->where('createdAtTs', '>=', $dateStart->getTimestamp())
            ->count()
        ;
        $reportsScreensCount = $visitedScreensUsageLogs->where('data.category', 'reports')->count();
        // $templatesScreensCount = $visitedScreensUsageLogs->where('data.category', 'templates')->count();
        // $configurationsScreensCount = $visitedScreensUsageLogs->where('data.category', 'configurations')->count();

        $reportRow = [
            'client' => $client,
            'salesCount' => $salesCount,
            'tasksCount' => $tasksCount,
            'manualLeadsCount' => $manualLeadsCount,
            'statusChangeCount' => $statusChangeCount,
            'massiveEmailsCount' => $massiveEmailsCount,
            'proposalEmailsCount' => $proposalEmailsCount,
            'automaticLeadsCount' => $automaticLeadsCount,
            'standardWapHitsCount' => $standardWapHitsCount,
            'manualProposalsCount' => $manualProposalsCount,
            'individualEmailsCount' => $individualEmailsCount,
            'automationEmailsCount' => $automationEmailsCount,
            'visitedReportsScreensCount' => $reportsScreensCount,
            'massiveWapSendingMsgsCount' => $massiveWapSendingMsgsCount,
            'proposalWapSendingMsgsCount' => $proposalWapSendingMsgsCount,
            'proposalWapSendingMsgsCount' => $proposalWapSendingMsgsCount,
            'individualWapSendingMsgsCount' => $individualWapSendingMsgsCount,
            'automationWapSendingMsgsCount' => $automationWapSendingMsgsCount,
            // 'visitedTemplatesScreensCount' => $templatesScreensCount,
            // 'visitedConfigurationsScreensCount' => $configurationsScreensCount,
        ];
        $reportRow['totalHits'] = $this->calculateTotalHits($reportRow);
        return $reportRow;
    }


    protected function calculateTotalHits(array $reportRow): int
    {
        $totalHits = 0;
        foreach ($reportRow as $indexName => $value) {
            if (!Str::contains($indexName, 'Count')) {
                continue;
            }
            $totalHits += (int) $value;
        }
        return $totalHits;
    }


    protected function validateFilters(array $options): void
    {
        $filters = $options['filters'] ?? [];
        if (!$filters) {
            throw new Exception('filters_are_missing');
        }
        // $clientId = $filters['client_id'] ?? null;
        // if (!$clientId) {
        //     throw new Exception('client_id_filter_is_missing');
        // }
        $dateEnd = $filters['date_end'] ?? null;
        if (!$dateEnd) {
            throw new Exception('date_end_filter_is_missing');
        }
        $dateStart = $filters['date_start'] ?? null;
        if (!$dateStart) {
            throw new Exception('date_start_filter_is_missing');
        }
    }

}
