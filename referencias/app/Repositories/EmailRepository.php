<?php

namespace App\Repositories;

use DateTime;
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Lead;
use App\Models\Email;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Models\LeadContactEmail;
use Illuminate\Support\Collection;
use App\DTO\MailerSendResponseDTO;
use Illuminate\Support\Facades\DB;
use App\DTO\EmailSendParametersDTO;
use App\Exceptions\DatabaseException;
use App\DTO\MailerScheduleResponseDTO;
use App\DTO\EmailScheduleParametersDTO;
use Illuminate\Database\Eloquent\Builder;
use App\DTO\MailerMassiveScheduleResponseDTO;
use App\DTO\EmailMassiveScheduleParametersDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;
use App\DTO\ClientyConfigurations\ClientEmailSendingMetricsDTO;


class EmailRepository
{

    public function storeSentEmail(
        User $user,
        EmailSendParametersDTO $sendParametersDTO,
        LeadContactEmail $leadContactEmail,
        MailerSendResponseDTO $mailerSendResponseDTO
    ): Email {
        $ccEmails = null;
        if ($sendParametersDTO->cc) {
            $ccEmails = implode(',', $sendParametersDTO->cc);
        }
        $data = [
            'cc' => $ccEmails,
            'user_id' => $user->id,
            'send_date' => Carbon::now(),
            'sent_date' => Carbon::now(),
            'external_custom_massive_id' => null,
            'lead_id' => $leadContactEmail->lead->id,
            'external_id' => $mailerSendResponseDTO->id,
            'client_id' => $leadContactEmail->client->id,
            'is_proposal' => $sendParametersDTO->isProposal,
            'lead_contact_email_id' => $leadContactEmail->id,
            'external_custom_id' => $mailerSendResponseDTO->appCustomId,
            'external_massive_id' => $mailerSendResponseDTO->massiveSendingId,
            'individual_lead_send_hash' => $sendParametersDTO->individualLeadSendHash,
        ];
        $email = new Email($data);
        $email->saveOrFail();
        return $email->fresh();
    }


    public function storeScheduledEmail(
        User $user,
        EmailScheduleParametersDTO $scheduleParametersDTO,
        LeadContactEmail $leadContactEmail,
        MailerScheduleResponseDTO $mailerScheduleResponseDTO
    ): Email {
        $ccEmails = null;
        if ($scheduleParametersDTO->cc) {
            $ccEmails = implode(',', $scheduleParametersDTO->cc);
        }
        $automationLog = $scheduleParametersDTO->automationLog;
        $automationLogId = $automationLog ? $automationLog->id : null;
        $data = [
            'cc' => $ccEmails,
            'sent_date' => null,
            'user_id' => $user->id,
            'external_custom_massive_id' => null,
            'automation_log_id' => $automationLogId,
            'lead_id' => $leadContactEmail->lead->id,
            'client_id' => $leadContactEmail->client->id,
            'external_id' => $mailerScheduleResponseDTO->id,
            'lead_contact_email_id' => $leadContactEmail->id,
            'send_date' => $mailerScheduleResponseDTO->sendAt,
            'is_proposal' => $scheduleParametersDTO->isProposal,
            'external_custom_id' => $mailerScheduleResponseDTO->appCustomId,
            'external_massive_id' => $mailerScheduleResponseDTO->massiveSendingId,
            'individual_lead_send_hash' => $scheduleParametersDTO->individualLeadSendHash,
        ];
        $email = new Email($data);
        $email->saveOrFail();
        return $email->fresh();
    }


    public function storeMassiveScheduledEmail(
        User $user,
        EmailMassiveScheduleParametersDTO $paramsDTO,
        MailerMassiveScheduleResponseDTO $mailerResponseDTO
    ): Collection {
        $emailsDataArr = [];
        $client = $user->client;
        $externalIds = collect([]);

        // Emails keyed by Mailer:Email:app_custom_id (equals to Clienty:Email:external_custom_id)
        $responseEmails = collect($mailerResponseDTO->emails)->keyBy('app_custom_id');

        $dateNow = new DateTime();
        $leadContactEmailsGroupedByLead = $paramsDTO->leadContactEmails->groupBy('lead_id');

        foreach ($leadContactEmailsGroupedByLead as $leadContactEmails) {
            $individualLeadSendHash = str_replace('-', '', Str::orderedUuid());

            foreach ($leadContactEmails as $leadContactEmail) {
                $externalCustomId = $leadContactEmail->buildExternalCustomId();
                $externalEmailData = $responseEmails->get($externalCustomId);
                $externalIds->push($externalEmailData['id']);

                $emailsDataArr[] = [
                    'sent_date' => null,
                    'user_id' => $user->id,
                    'created_at' => $dateNow,
                    'updated_at' => $dateNow,
                    'client_id' => $client->id,
                    'send_date' => $paramsDTO->sendDate,
                    'is_proposal' => $paramsDTO->isProposal,
                    'lead_id' => $leadContactEmail->lead->id,
                    'external_id' => $externalEmailData['id'],
                    'external_custom_id' => $externalCustomId,
                    'lead_contact_email_id' => $leadContactEmail->id,
                    'individual_lead_send_hash' => $individualLeadSendHash,
                    'external_massive_id' => $mailerResponseDTO->massiveSendingId,
                    'external_custom_massive_id' => $mailerResponseDTO->appCustomMassiveId,
                ];
            }
        }
        $chunks = array_chunk($emailsDataArr, 150);
        foreach ($chunks as $emailsDataArrChunk) {
            Email::insert($emailsDataArrChunk);
        }
        return $externalIds;
    }


    public function findLastOneSentByUser(User $user): ?Email
    {
        return Email::where('user_id', $user->id)
            ->whereNotNull('sent_date')
            ->whereNull('automation_log_id')
            ->orderBy('sent_date', 'desc')
            ->first()
        ;
    }


    public function findAlreadyManuallySentProposalsByLeadCollection(Collection $leads): Collection
    {
        return Email::select('lead_id')
            ->where(['is_proposal' => true, 'automation_log_id' => null])
            ->whereIn('lead_id', $leads->pluck('id'))
            ->groupBy('lead_id')
            ->havingRaw('COUNT(lead_id) > 1')
            ->get()
        ;
    }


    public function cancelMassiveEmailByExternalIds(array $cancelledExternalEmailIds): Collection
    {
        $data = ['cancelled_date' => Carbon::now()];
        $updated = Email::whereIn('external_id', $cancelledExternalEmailIds)->update($data);
        if (!$updated) {
            throw new \Exception('emails_not_cancelled');
        }
        return Email::whereIn('external_id', $cancelledExternalEmailIds)->get('id');
    }


    public function cancelEmails(Collection $emails): Collection
    {
        $cancelledEmails = collect([]);
        foreach ($emails as $email) {
            $email->cancelled_date = Carbon::now();
            $email->saveOrFail();
            $cancelledEmails->push($email->fresh());
        }
        return $cancelledEmails;
    }


    public function findPaginatedEmailsByClient(Client $client, array $options)
    {
        $limit = $options['limit'] ?? 20;
        $pageNumber = $options['page'] ?? 1;
        $filters = $options['filters'] ?? [];
        // DB::enableQueryLog();
        $queryBuilder = Email::where(['client_id' => $client->id, 'external_massive_id' => null])
            // ->groupBy('individual_lead_send_hash')
            // ->orderBy(DB::raw('IFNULL(sent_date, send_date)'), 'DESC')
            ->orderBy(DB::raw('IFNULL(sent_date, send_date)'), 'DESC')
        ;
        
        $relationshipsToEagerLoad = $options['with'] ?? [];
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }
        
        $queryBuilder = $this->applyFilters($queryBuilder, $filters);
        
        $paginated = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
        // dd(DB::getQueryLog());
        return $paginated;
    }
    

    public function findProposalsBetweenSentDatesByClient(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd
    ): Collection {
        $proposals = Email::where('sent_date', '>=', $dateStart)
            ->where('sent_date', '<=', $dateEnd)
            ->where('client_id', $client->id)
            ->where('is_proposal', true)
            ->whereNull('automation_log_id')
            ->get()
        ;
        return $proposals;
    }


    public function findFilteredEmailsByLead(Lead $lead, array $filters = []): Collection
    {
        return Email::where(['lead_id' => $lead->id])->where($filters)->get();
    }


    public function findPaginatedMassiveEmailsByClient(Client $client, array $options)
    {
        $sort = $options['sort'];
        $limit = $options['limit'] ?? 20;
        $pageNumber = $options['page'] ?? 1;
        $filters = $options['filters'] ?? [];

        $queryBuilder = Email::query()
            ->select(
                'external_massive_id',
                'user_id',
                DB::raw('count(external_massive_id) as total'),
                DB::raw('count(bounced_date) as bounced_count')
            )
            ->where('client_id', $client->id)
            ->where('external_massive_id', '!=', null)
            ->groupBy(['external_massive_id', 'user_id'])
        ;
        if ($sort) {
            $queryBuilder->orderBy('send_date', $sort);
        }

        $queryBuilder = $this->applyFilters($queryBuilder, $filters);
        $result = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
        return $result;
    }


    public function findByIdsAndClient(array $ids, Client $client): Collection
    {
        $emails = Email::where('client_id', $client->id)->whereIn('id', $ids)->get();
        return $emails;
    }


    public function findOneByExternalIdAndExternalCustomId(int $externalId, string $externalCustomId): ?Email
    {
        $email = Email::where('external_id', $externalId)->where('external_custom_id', $externalCustomId)->first();
        return $email;
    }


    public function findOneOrFailByExternalId(int $externalId): Email
    {
        $email = Email::where('external_id', $externalId)->firstOrFail();
        return $email;
    }


    public function getSentOrScheduledCountByRange(Client $client, DateTime $dateStart, DateTime $dateEnd): int
    {
        $marginDays = 20;
        $sentEnd = (clone $dateEnd)->modify("+{$marginDays} days");
        $sentStart = (clone $dateStart)->modify("-{$marginDays} days");

        // Usamos sent_date a propósito, para que use el index de la base de datos.
        // Le damos un margen de +- 20 días, con eso debería ser suficiente en casi todos los casos.
        $query = Email::where('client_id', $client->id)
            ->whereNull('cancelled_date')
            ->where('send_date', '>=', $dateStart)
            ->where('send_date', '<=', $dateEnd)
            ->where('sent_date', '>=', $sentStart)
            ->where('sent_date', '<=', $sentEnd)
        ;
        return $query->count();
    }


    public function getClientyConfigClientModalMetricsInfo(
        Client $client,
        array $opts = []
    ): ClientEmailSendingMetricsDTO {
        $period = $opts['period'] ?? null;

        $queryBuilder = DB::table('Emails')
            ->where('client_id', $client->id)
            ->whereNotNull('sent_date')
            ->selectRaw('COUNT(id) as sentCount')
            ->selectRaw('SUM(opened_date IS NOT NULL) as openedCount')
            ->selectRaw('SUM(bounced_date IS NOT NULL) as bouncedCount')
            ->selectRaw('SUM(complained_date IS NOT NULL) as complainedCount')
            ->selectRaw('SUM(unsubscribed_date IS NOT NULL) as unsubscribedCount')
            ->groupBy('client_id')
        ;
        if ($period) {
            $startDate = match ($period) {
                'last_day' => new DateTime('1 day ago'),
                'last_week' => new DateTime('1 week ago'),
                'last_month' => new DateTime('1 month ago'),
                default => null,
            };
            if ($startDate) {
                $queryBuilder->where('sent_date', '>=', $startDate);
            }
        }

        $result = $queryBuilder->first();
        $dto = ClientEmailSendingMetricsDTO::buildFromQueryResult($client, $result);
        return $dto;
    }


    public function findTotalLeadsForMassiveSend($externalMassiveId): int
    {
        return Email::where('external_massive_id', $externalMassiveId)
            ->groupBy(['external_massive_id', 'lead_id'])
            ->get()
            ->count()
        ;
    }


    public function listPaginated(Client $client, array $options = []): LengthAwarePaginator
    {
        $relationshipsToEagerLoad = $options['with'] ?? [];
        $queryBuilder = Email::query();
        $queryBuilder->where('client_id', $client->id);
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }
        $queryBuilder = $this->applyFilters($queryBuilder, $options['filters']);
        $paginated = $queryBuilder->paginate($options['limit'], ['*'], 'page', $options['page']);
        return $paginated;
    }


    protected function applyFilters(Builder $queryBuilder, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if (isset($filters[$key])) {
                if (is_array($value)) {
                    $queryBuilder->whereIn($key, $value);
                } elseif ($filters[$key] instanceof SQLFilterCriteria) {
                    $queryBuilder = $filters[$key]->filterSQLQuery($queryBuilder);
                } else {
                    $queryBuilder->where($key, $value);
                }
            }
        }
        return $queryBuilder;
    }

}
