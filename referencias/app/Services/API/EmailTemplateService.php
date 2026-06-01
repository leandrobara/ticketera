<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\User;
use App\Models\EmailTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\EmailTemplateRepository;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;


class EmailTemplateService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $browserEventsDispatcher;
    private $emailTemplateRepository;


    public function __construct(
        EmailTemplateRepository $emailTemplateRepository,
        BrowserEventsDispatcher $browserEventsDispatcher
    ) {
        $this->browserEventsDispatcher = $browserEventsDispatcher;
        $this->emailTemplateRepository = $emailTemplateRepository;
    }


    public function list(array $options)
    {
        $client = $this->getClient();
        $opts = [
            'filters' => $this->getFilterCriterias($options['filters'] ?? []),
        ];

        return $this->emailTemplateRepository->list($client, $opts);
    }


    public function create(array $data, array $opts = []): EmailTemplate
    {
        $data['user_id'] = $this->getUser()->id;
        $data['client_id'] = $this->getClient()->id;
        $attachments = isset($data['attachments']) ? collect($data['attachments']) : null;
        unset($data['attachments']);

        try {
            DB::beginTransaction();
            $emailTemplate = $this->emailTemplateRepository->create($data);
            if ($attachments) {
                $this->addEmailTemplateAttachments($emailTemplate, $attachments);
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $emailTemplate = $emailTemplate->fresh();
        $this->browserEventsDispatcher->notifyNewEmailTemplate($emailTemplate);
        return $emailTemplate;
    }


    public function update(EmailTemplate $emailTemplate, $data): EmailTemplate
    {
        $data['user_id'] = $this->getUser()->id;
        $attachments = isset($data['attachments']) ? collect($data['attachments']) : null;
        unset($data['attachments']);

        try {
            DB::beginTransaction();
            $emailTemplate = $this->emailTemplateRepository->update($emailTemplate, $data);
            if ($attachments) {
                $this->addEmailTemplateAttachments($emailTemplate, $attachments);
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        
        return $emailTemplate->fresh();
    }


    public function createMultipleFromClientyConfigEmailTemplates(
        Collection $clientyConfigEmailTemplates,
        User $user
    ): Collection {
        $data['user_id'] = $user->id;
        $emailTemplates = new Collection();
        $data['client_id'] = $user->client_id;

        try {
            DB::beginTransaction();
            
            foreach ($clientyConfigEmailTemplates as $clientyConfigTpl) {
                $data = [
                    'user_id' => $user->id,
                    'client_id' => $user->client_id,
                    'body' => $clientyConfigTpl->body,
                    'title' => $clientyConfigTpl->title,
                    'subject' => $clientyConfigTpl->subject,
                    'is_proposal' => $clientyConfigTpl->is_proposal,
                ];
                $emailTemplate = $this->emailTemplateRepository->create($data);
                $emailTemplates->push($emailTemplate);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $emailTemplates;
    }


    public function delete(EmailTemplate $emailTemplate)
    {
        return $this->emailTemplateRepository->delete($emailTemplate);
    }


    public function addEmailTemplateAttachments(EmailTemplate $emailTemplate, Collection $attachments): EmailTemplate
    {
        $clientId = $this->getClient()->id;
        $syncData = $attachments->pluck('id')->mapWithKeys(function ($attachmentId) use ($clientId) {
            return [$attachmentId => ['client_id' => $clientId]];
        });
        $emailTemplate->attachments()->sync($syncData);
        $emailTemplate->saveOrFail();
        return $emailTemplate;
    }


    public function createNewClientDefaultProposalResend(User $user): EmailTemplate
    {
        $attrs = ['user_id' => $user->id, 'client_id' => $user->client->id];
        $tpl = EmailTemplate::factory()->newClientDefaultProposalResend()->create($attrs);
        return $tpl;
    }


    protected function getFilterCriterias(array $filters)
    {
        $criterias = [];
        $nfilters = [];
        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias))) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] =  $value;
            }
        }
        return $nfilters;
    }

}
