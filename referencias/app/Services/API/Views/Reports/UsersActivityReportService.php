<?php

namespace App\Services\API\Views\Reports;

use DateTime;
use App\Models\User;
use App\Models\Client;
use App\Models\MongoDB\EventLog;
use App\Services\API\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class UsersActivityReportService
{

    public function __construct(protected readonly UserService $userService)
    {
    }


    public function list(Client $client, array $options): Collection
    {
        $this->validateFilters($options);
        
        $dateEnd = $options['filters']['date_end'];
        $dateEndStr = $dateEnd->format('Y-m-d H:i:s');
        $dateStart = $options['filters']['date_start'];
        $userIds = $options['filters']['user_id'] ?? null;
        $dateStartStr = $dateStart->format('Y-m-d H:i:s');
        $userType = $options['filters']['user_type'] ?? 'all';

        $users = $client->users->filter(function (User $user) use ($userIds, $userType) {
            if ($userIds && (!in_array($user->id, $userIds))) {
                return false;
            }
            if ($userType == 'enabled' && !$user->enabled) {
                return false;
            }
            if ($userType == 'disabled' && $user->enabled) {
                return false;
            }
            return true;
        });
        $report = new Collection();
        foreach ($users as $user) {
            $sentWapSendingMsgs = DB::table('WhatsAppSendingMessages')
                ->where('success', true)
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('client_id', $client->id)
                ->whereRaw("send_date <= '{$dateEndStr}'")
                ->whereRaw("send_date >= '{$dateStartStr}'")
                ->select(['id', 'is_massive', 'is_proposal', 'wautomation_log_id'])
                ->get(['id', 'is_massive', 'is_proposal', 'wautomation_log_id'])
            ;
            $totalSentWapSendingMsgsCount = $sentWapSendingMsgs->whereNull('wautomation_log_id')->count();
            $sentProposalWapSendingMsgsCount = $sentWapSendingMsgs->whereNull('wautomation_log_id')
                ->where('is_proposal', true)
                ->count()
            ;

            $sentEmails = DB::table('Emails')
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('client_id', $client->id)
                ->whereRaw("send_date <= '{$dateEndStr}'")
                ->whereRaw("send_date >= '{$dateStartStr}'")
                ->select(['id', 'is_proposal', 'automation_log_id'])
                ->get(['id', 'is_proposal', 'automation_log_id'])
            ;
            $totalSentEmailsCount = $sentEmails->whereNull('automation_log_id')->count();
            $sentProposalEmailsCount = $sentEmails->whereNull('automation_log_id')
                ->where('is_proposal', true)
                ->count()
            ;

            $automationWapSendingMsgsCount = $sentEmails->whereNotNull('automation_log_id')->count();
            $sentAutomationWapSendingMsgsCount = $sentWapSendingMsgs->whereNotNull('wautomation_log_id')->count();
            $totalSentAutomationsCount = $automationWapSendingMsgsCount + $sentAutomationWapSendingMsgsCount;

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
            $notesCount = DB::table('Notes')
                ->select(['id'])
                ->whereNull('deleted_at')
                ->where('user_id', $user->id)
                ->where('client_id', $client->id)
                ->whereRaw("created_at <= '{$dateEndStr}'")
                ->whereRaw("created_at >= '{$dateStartStr}'")
                ->count()
            ;

            $statusChangeCount = EventLog::query()
                ->where('system', 'clienty_crm')
                ->where('event', 'lead_status_updated')
                ->where('log.user.id', $user->id)
                ->where('log.client_id', $user->client_id)
                ->where('createdAtTs', '<=', $dateEnd->getTimestamp())
                ->where('createdAtTs', '>=', $dateStart->getTimestamp())
                ->count()
            ;

            $reportRow = [
                'user' => $user->only(['id', 'name', 'last_name', 'username']),
                'salesCount' => $salesCount,
                'tasksCount' => $tasksCount,
                'notesCount' => $notesCount,
                'statusChangeCount' => $statusChangeCount,
                'totalSentEmailsCount' => $totalSentEmailsCount,
                'sentProposalEmailsCount' => $sentProposalEmailsCount,
                'totalSentAutomationsCount' => $totalSentAutomationsCount,
                'totalSentWapSendingMsgsCount' => $totalSentWapSendingMsgsCount,
                'sentProposalWapSendingMsgsCount' => $sentProposalWapSendingMsgsCount,

            ];
            $report->push($reportRow);
        }
        return $report;
    }


    protected function validateFilters(array $options): void
    {
        $filters = $options['filters'] ?? [];
        if (!$filters) {
            throw new Exception('filters_are_missing');
        }
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
