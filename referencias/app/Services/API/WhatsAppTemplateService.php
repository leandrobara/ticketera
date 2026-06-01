<?php

namespace App\Services\API;

use Throwable;
use Exception;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Models\WhatsAppTemplate;
use App\Repositories\Repository;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WhatsAppMetaAPIConnection;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\WhatsAppAttachmentService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPITemplateDTO;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;


class WhatsAppTemplateService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        protected Repository $whatsAppTemplateRepository,
        protected WhatsAppMetaAPIHelper $whatsAppMetaAPIHelper,
        protected WhatsAppAttachmentService $whatsAppAttachmentService,
        protected WhatsAppEventsDispatcherService $whatsAppEventsDispatcherService,
    ) {
    }


    public function findWhatsAppTemplateById(int $id): ?WhatsAppTemplate
    {
        return $this->whatsAppTemplateRepository->findById($id);
    }


    public function findAllByClient(): Collection
    {
        return $this->whatsAppTemplateRepository->findAllByClient($this->getClient());
    }


    public function findMetaTemplatesByClientAndWabaId(Client $client, string $wabaId): Collection
    {
        return $this->whatsAppTemplateRepository->findMetaTemplatesByClientAndWabaId($client, $wabaId);
    }


    public function findMatchingTemplateForWaba(WhatsAppTemplate $wapTpl, string $targetWabaId): ?WhatsAppTemplate
    {
        return $this->whatsAppTemplateRepository->findMatchingTemplateForWaba($wapTpl, $targetWabaId);
    }


    public function create(
        array $data,
        ?WhatsAppMetaAPIConnection $forcedWhatsAppMetaAPIConnection = null,
    ): WhatsAppTemplate {
        $whatsAppAttachment = null;
        $loginUser = $this->getRequestUserOrNull();
        $wapMetaAPIConnection = $forcedWhatsAppMetaAPIConnection ?? $loginUser?->whatsAppMetaAPIConnection;

        $data['client_id'] = $data['client_id'] ?? $this->getClient()->id;
        $wapAttachmentId = $data['whatsapp_attachment_id'] ?? null;
        $templateIsLinkedWithMeta = ($data['enable_meta_template'] ?? false);

        if ($templateIsLinkedWithMeta) {
            if (!$wapMetaAPIConnection) {
                throw new Exception('WhatsApp Meta API connection not found');
            }
            if ($wapAttachmentId && $wapMetaAPIConnection) {
                $whatsAppAttachment = $this->whatsAppAttachmentService->findOrFail($wapAttachmentId);
                if (!$whatsAppAttachment->meta_handle_id) {
                    $whatsAppAttachment = $this->whatsAppAttachmentService->uploadToWhatsAppMeta(
                        $wapMetaAPIConnection, $whatsAppAttachment
                    );
                }
            }
        }

        try {
            DB::beginTransaction();
            
            if ($templateIsLinkedWithMeta) {
                $wapMetaAPITemplateDTO = $this->createTemplateAtMetaAPIAndRetrieveDTO(
                    $data, $wapMetaAPIConnection, $whatsAppAttachment
                );
                $data['meta_id'] = $wapMetaAPITemplateDTO->id;
                $data['meta_name'] = $wapMetaAPITemplateDTO->name;
                $data['waba_id'] = $wapMetaAPIConnection->waba_id;
                $data = $this->prepareMetaVariablesForStorage($data);
            }
            $wapTemplate = $this->whatsAppTemplateRepository->create($data);
            
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Si pasé un $forcedWhatsAppMetaAPIConnection, es por que ya estoy en el job de sync creando desde ahí.
        if ($templateIsLinkedWithMeta && !$forcedWhatsAppMetaAPIConnection && $loginUser) {
            $this->whatsAppEventsDispatcherService->dispatchWhatsAppMetaAPISyncUsersTemplatesJob(
                triggerUser: $loginUser,
                whatsAppTemplate: $wapTemplate,
                triggerAction: 'templateCreate',
            );
        }

        return $wapTemplate;
    }


    /**
     * Por el momento está deshabilitada la edición de plantillas de Meta!
     */
    public function update(WhatsAppTemplate $whatsAppTemplate, $data): WhatsAppTemplate
    {
        $loginUser = $this->getRequestUserOrNull();
        $hasMetaId = !empty($whatsAppTemplate->meta_id);
        // $whatsAppAttachment = $whatsAppTemplate->whatsAppAttachment;

        $wapMetaAPIConnection = $loginUser?->whatsAppMetaAPIConnection;
        $templateIsLinkedWithMeta = ($data['enable_meta_template'] ?? false);
        $metaTemplateHasChanged = $this->metaTemplateHasChanged($whatsAppTemplate, $data);

        if ($templateIsLinkedWithMeta && !$wapMetaAPIConnection) {
            throw new Exception('WhatsApp Meta API connection not found');
        }

        // Si ya tiene una plantilla meta, la eliminamos antes de crear la nueva
        if ($templateIsLinkedWithMeta && $metaTemplateHasChanged) {
            if ($hasMetaId) {
                $this->deleteMetaTemplate($whatsAppTemplate, $wapMetaAPIConnection);
            }
            $wapMetaAPITemplateDTO = $this->createTemplateAtMetaAPIAndRetrieveDTO($data, $wapMetaAPIConnection);
            $data['meta_id'] = $wapMetaAPITemplateDTO->id;
            $data['waba_id'] = $wapMetaAPIConnection->waba_id;
            $data['meta_name'] = $wapMetaAPITemplateDTO->name;
            $data = $this->prepareMetaVariablesForStorage($data);
        }
        
        // Meta was enabled, but now it's being disabled.
        if (!$templateIsLinkedWithMeta && $hasMetaId) {
            $this->deleteMetaTemplate($whatsAppTemplate, $wapMetaAPIConnection);
            $data = $this->clearMetaData();
        }

        $wapTemplate = $this->whatsAppTemplateRepository->update($whatsAppTemplate, $data);

        if ($templateIsLinkedWithMeta) {
            // Por ahora el job no lo usa, y tampoco se puede editar un tpl de meta, lo lanzo igual.
            $this->whatsAppEventsDispatcherService->dispatchWhatsAppMetaAPISyncUsersTemplatesJob(
                triggerUser: $loginUser,
                triggerAction: 'templateEdit',
                whatsAppTemplate: $wapTemplate,
            );
        }

        return $wapTemplate;
    }


    public function delete(
        WhatsAppTemplate $whatsAppTemplate,
        ?WhatsAppMetaAPIConnection $forcedWhatsAppMetaAPIConnection = null,
    ): WhatsAppTemplate {
        $loginUser = $this->getRequestUserOrNull();
        $hasMetaId = !empty($whatsAppTemplate->meta_id);
        $wapMetaAPIConnection = $forcedWhatsAppMetaAPIConnection ?? $loginUser?->whatsAppMetaAPIConnection;
        
        if ($hasMetaId) {
            if (!$wapMetaAPIConnection) {
                throw new Exception('WhatsApp Meta API connection not found');
            }
            $this->deleteMetaTemplate($whatsAppTemplate, $wapMetaAPIConnection);
        }

        $wapTemplate = $this->whatsAppTemplateRepository->delete($whatsAppTemplate);

        // Si pasé un $forcedWhatsAppMetaAPIConnection, es por que ya estoy en el job de sync borrando desde ahí.
        if ($hasMetaId && !$forcedWhatsAppMetaAPIConnection && $loginUser) {
            $this->whatsAppEventsDispatcherService->dispatchWhatsAppMetaAPISyncUsersTemplatesJob(
                triggerUser: $loginUser,
                whatsAppTemplate: $wapTemplate,
                triggerAction: 'templateDelete',
            );
        }

        return $wapTemplate;
    }


    private function createTemplateAtMetaAPIAndRetrieveDTO(
        array $data,
        WhatsAppMetaAPIConnection $wapMetaAPIConnection,
        ?WhatsAppAttachment $whatsAppAttachment = null,
    ): WhatsAppMetaAPITemplateDTO {
        // Uso un nro random, para evitar errores de nombres al borrar y crear nuevamente.
        $templateMetaName = $data['meta_name'] ?? Str::slug($data['title'], '_') . '_' . mt_rand(0, 1000);

        $variables = $data['meta_variables'] ?? [];
        $headerVariables = collect($variables)->where('type', 'header')->values()->all();
        $bodyVariables = collect($variables)->where('type', 'body')->values()->all();
    
        $documentFilename = $whatsAppAttachment?->original_filename;
        $mediaHeaderHandleId = $whatsAppAttachment?->meta_handle_id;
        $headerFormat = ($data['meta_header_text'] ?? null) ? 'TEXT' : '';
        if ($whatsAppAttachment) {
            $headerFormat = $this->whatsAppAttachmentService->getWhatsAppMediaTypeByMime(
                $whatsAppAttachment->mime_type
            );
        }

        return $this->whatsAppMetaAPIHelper->createTemplate(
            language: 'es_ES',
            bodyText: $data['body'],
            name: $templateMetaName,
            bodyTextVariables: $bodyVariables,
            documentFilename: $documentFilename,
            headerTextVariables: $headerVariables,
            wabaId: $wapMetaAPIConnection->waba_id,
            headerFormat: strtolower($headerFormat),
            mediaHeaderHandleId: $mediaHeaderHandleId,
            headerText: $data['meta_header_text'] ?? '',
            footerText: $data['meta_footer_text'] ?? '',
            accessToken: $wapMetaAPIConnection->access_token,
            templateCategory: $data['meta_category'] ?? 'MARKETING',
        );
    }


    private function deleteMetaTemplate(
        WhatsAppTemplate $whatsAppTemplate,
        WhatsAppMetaAPIConnection $wapMetaAPIConnection
    ): bool {
        if (!$whatsAppTemplate->meta_id) {
            return false;
        }
        try {
            $ok = $this->whatsAppMetaAPIHelper->deleteTemplate(
                wabaId: $wapMetaAPIConnection->waba_id,
                templateName: $whatsAppTemplate->meta_name,
                accessToken: $wapMetaAPIConnection->access_token
            );
            return $ok;
        } catch (Exception $e) {
            $errObj = json_decode($e->getMessage(), true);
            $code = $errObj['error']['code'] ?? null;
            $subcode = $errObj['error']['error_subcode'] ?? null;
            // La plantilla no existe (o ya fue eliminada)
            if ($errObj && $code == 100 && $subcode == 2593002) {
                return false;
            }
            throw $e;
        }
    }


    private function metaTemplateHasChanged(WhatsAppTemplate $whatsAppTemplate, array $data): bool
    {
        if ($whatsAppTemplate->body != ($data['body'] ?? $whatsAppTemplate->body)) {
            return true;
        }
        if ($whatsAppTemplate->meta_header_text != ($data['meta_header_text'] ?? null)) {
            return true;
        }
        if ($whatsAppTemplate->meta_footer_text != ($data['meta_footer_text'] ?? null)) {
            return true;
        }
        $newWapAttachmentId = $data['whatsapp_attachment_id'] ?? null;
        if ($whatsAppTemplate->whatsAppAttachment?->id != $newWapAttachmentId) {
            return true;
        }
        return false;
    }


    private function prepareMetaVariablesForStorage(array $data): array
    {
        $variables = $data['meta_variables'] ?? [];
        $data['meta_header_variables_json'] = json_encode(
            collect($variables)->where('type', 'header')->values()->all()
        );
        $data['meta_body_variables_json'] = json_encode(
            collect($variables)->where('type', 'body')->values()->all()
        );
        unset($data['meta_variables']);
        unset($data['enable_meta_template']);
        return $data;
    }


    private function clearMetaData(): array
    {
        return [
            'meta_id' => null,
            'meta_category' => null,
            'meta_header_text' => null,
            'meta_footer_text' => null,
            'meta_header_variables_json' => null,
            'meta_body_variables_json' => null,
        ];
    }


    public function createMultipleFromClientyConfigWhatsAppTemplates(
        Collection $clientyConfigWhatsAppTemplates,
        User $user
    ): Collection {
        $data['user_id'] = $user->id;
        $data['client_id'] = $user->client_id;
        $whatsAppTemplates = new Collection();

        try {
            DB::beginTransaction();
            
            foreach ($clientyConfigWhatsAppTemplates as $clientyConfigTpl) {
                $data = [
                    'user_id' => $user->id,
                    'client_id' => $user->client_id,
                    'body' => $clientyConfigTpl->body,
                    'title' => $clientyConfigTpl->title,
                ];
                $whatsAppTemplate = $this->whatsAppTemplateRepository->create($data);
                $whatsAppTemplates->push($whatsAppTemplate);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $whatsAppTemplates;
    }


    public function createNewClientDefaultProposalResend(Client $client): WhatsAppTemplate
    {
        $attrs = ['client_id' => $client->id];
        $tpl = WhatsAppTemplate::factory()->newClientDefaultProposalResend()->create($attrs);
        return $tpl;
    }

}
