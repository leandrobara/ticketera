<?php

namespace App\Http\Controllers\API\External;

use DateTime;
use DateTimeZone;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\StringHelper;
use App\Helpers\SystemHelper;
use App\Helpers\MondayAPIHelper2;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use App\Services\API\EventsLogService;
use App\Services\API\UserNotificationService;
use App\Models\MongoDB\CalendlyScheduledEvent;
use App\Http\Controllers\API\BaseAPIController;
use App\DTO\Monday\MondayAPIClientsBoardItemDTO;
use App\Helpers\PowerBIReports\LeadCountryHelper;
use App\Services\API\CalendlyScheduledEventService;
use App\Services\API\MondayChurnBoardClientService;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ReportsController extends BaseAPIController
{

    public function downloadPowerBiReport(Request $req)
    {
        \Debugbar::disable();

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=powerbi_report.csv');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');

        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(300);
        SystemHelper::setMemoryLimitMB(900);

        $handle = fopen('php://output', 'w');
        $headers = [
            'ID',
            'Pais',
            'Canal',
            'Estado',
            'Categoría de Estado',
            
            'Estrellas amarillas (calidad)',
            'Estrellas verdes',
            'Fecha de 1ra reunion',
            'Campo <Dato interno hora reunion>',
            'Campo <Dato interno hora 1ra reunion efectiva>',
            
            'Campo <Dato interno hora 2da reunion efectiva>',
            'Campo <Dato interno hora 3ra reunion efectiva>',
            'Campo <Embudo>',
            'Fecha envío presupuesto',
            'Fecha de venta',
            
            'Monto de venta',
            'Fecha de baja',
            'Fecha de ingreso del lead al CRM',
            'Fecha de ingreso como cliente',
            'Fecha de pedido de baja desde CRM',
            
            'Motivo de limpieza SDR',
            'Motivo de no compra',
            'Rubro',
            'Vendedor',
            'Empresa',
            
            'Email',
            'Teléfono',
            'Onboarder',
            'Tipo de cliente',
            'Motivo de Baja (Monday)',
            
            'Motivo de Baja (CRM)',
            'UTM Source',
            'UTM Medium',
            'UTM Content',
            'UTM Campaign',
            
            'UTM Keywords',
        ];

        fputcsv($handle, $headers);

        // Proceso y agrego primero, los datos fijos históricos que me pasó finanzas en este archivo.
        $historicalFile = fopen(resource_path('docs/powerbi_leads_viejos.csv'), 'r');
        fgetcsv($historicalFile); // Salteo el header
        while (($row = fgetcsv($historicalFile)) !== false) {
            fputcsv($handle, $row);
        }
        fclose($historicalFile);

        $with = [
            'tags',
            'user',
            'status',
            'client',
            'landing',
            'leadSales',
            'leadContacts',
            'proposalsInfo',
            'tags.tagCategory',
            'acquisitionChannel',
            'status.statusCategory',
            'leadContacts.leadContactEmails',
            'leadContacts.leadContactPhones',
            'leadCustomFieldsValues.leadCustomField',
        ];

        $chunk = 300;
        $continue = true;
        $processedCount = 0;
        $offset = $req->input('offset') ?? 0;
        $limit = $req->input('limit') ?? 999999;
        $chunk = ($limit > $chunk) ? $chunk : $limit;

        $stringHelper = resolve(StringHelper::class);
        $clientService = resolve(ClientService::class);
        $leadCountryHelper = resolve(LeadCountryHelper::class);
        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
        $userNotificationService = resolve(UserNotificationService::class);
        // $calendlyEventsService = resolve(CalendlyScheduledEventService::class);
        // $mondayChurnBoardClientService = resolve(MondayChurnBoardClientService::class);

        $mondayClientsBoardItems = resolve(MondayAPIHelper2::class)
            ->findClientsBoardItemsKeyedByLeadId(['limit' => 99999])
            ->mapWithKeys(fn ($item, $leadId) => [$leadId => new MondayAPIClientsBoardItemDTO($item)])
        ;

        $leads = Lead::where('client_id', 2)
            ->with($with)
            ->limit($chunk)
            ->offset($offset)
            ->orderByRaw('lead_created_at ASC, id ASC')
            ->get()
        ;
        while ($continue) {
            foreach ($leads as $lead) {
                $processedCount++;

                $countryName = $leadCountryHelper->getCountryName($lead) ?? 'SIN INFO';

                $calendlyMeetingDateStr = '';
                $calendlyEvent = resolve(CalendlyScheduledEventService::class)->findLastByLeadId($lead->id);
                if ($calendlyEvent) {
                    $calendlyMeetingDate = (new DateTime($calendlyEvent->start_time))->setTimezone($tz);
                    $calendlyMeetingDateStr = $calendlyMeetingDate->format('d/m/Y H:i:s');
                }

                $meetingFieldName = 'Dato interno hora reunion';
                $firstMeetingFieldName = 'Dato interno hora 1ra reunion efectiva';
                $thirdMeetingFieldName = 'Dato interno hora 3ra reunion efectiva';
                $secondMeetingFieldName = 'Dato interno hora 2da reunion efectiva';
                
                $funnelFieldValue = $lead->getCustomFieldValueByName('Embudo') ?? '';
                $clientyMeetingDateStr = $lead->getCustomFieldValueByName($meetingFieldName) ?? '';
                $clientyFirstMeetingDateStr = $lead->getCustomFieldValueByName($firstMeetingFieldName) ?? '';
                $clientySecondMeetingDateStr = $lead->getCustomFieldValueByName($secondMeetingFieldName) ?? '';
                $clientyThirdMeetingDateStr = $lead->getCustomFieldValueByName($thirdMeetingFieldName) ?? '';

                $onboarder = '';
                $clientType = '';
                $clientyChurnDate = '';
                $mondayChurnReason = '';
                $mondayEntryDateStr = '';
                $mondayChurnDateStr = '';
                $mondayBusinessArea = '';
                $clientyChurnReason = '';
                
                $mondayClientItem = $mondayClientsBoardItems->get($lead->id);
                if ($mondayClientItem) {
                    if ($mondayClientItem->churnDate) {
                        $mondayChurnDate = (new DateTime($mondayClientItem->churnDate));
                        $mondayChurnDateStr = $mondayChurnDate->format('d/m/Y');
                    }
                    if ($mondayClientItem->entryDate) {
                        $mondayEntryDate = (new DateTime($mondayClientItem->entryDate));
                        $mondayEntryDateStr = $mondayEntryDate->format('d/m/Y');
                    }

                    $clientType = $mondayClientItem->clientType ?? '';
                    $mondayChurnReason = $mondayClientItem->churnReason ?? '';
                    $mondayBusinessArea = $mondayClientItem->businessArea ?? '';

                    $churnNotification = null;
                    if ($mondayClientItem->clientyClientSubdomain) {
                        $client = $clientService->findOneBySubdomain($mondayClientItem->clientyClientSubdomain);
                        $churnNotification = $client ?
                            $userNotificationService->findLastUnsubscribeByClientId($client->id)
                            : null
                        ;
                        if ($churnNotification) {
                            $clientyChurnDate = $churnNotification->created_at->format('d/m/Y');
                            $clientyChurnReason = $stringHelper->removeLineBreaks($churnNotification->comments);
                        }
                    }
                }


                // $mondayChurnClient = $mondayChurnBoardClientService->findOneByLeadId($lead->id);
                // if ($mondayChurnClient) {
                //     $modifiedDateStr = $mondayChurnClient->formattedValues['modifiedDate'];
                //     if ($modifiedDateStr) {
                //         $mondayChurnDate = (new DateTime($modifiedDateStr));
                //         $mondayChurnDateStr = $mondayChurnDate->format('d/m/Y');
                //     }

                //     $entryDateStr = $mondayChurnClient->formattedValues['entryDate'];
                //     if ($entryDateStr) {
                //         $mondayEntryDate = (new DateTime($entryDateStr));
                //         $mondayEntryDateStr = $mondayEntryDate->format('d/m/Y');
                //     }

                //     $onboarder = $mondayChurnClient->formattedValues['onboarder'] ?? '';
                //     $clientType = $mondayChurnClient->formattedValues['clientType'] ?? '';
                //     $mondayChurnReason = $mondayChurnClient->formattedValues['reason'] ?? '';
                //     $mondayChurnReason = $stringHelper->removeLineBreaks($mondayChurnReason);
                //     $clientBusinessArea = $mondayChurnClient->formattedValues['businessArea'] ?? '';

                //     $churnNotification = null;
                //     $clientyClientId = $mondayChurnClient?->clientyClient['id'] ?? null;
                //     if ($clientyClientId) {
                //         $client = Client::find($clientyClientId);
                //         $churnNotification = $userNotificationService->findLastUnsubscribeByClientId(
                //             $clientyClientId
                //         );
                //     }
                //     if ($churnNotification) {
                //         $clientyChurnDate = $churnNotification->created_at->format('d/m/Y');
                //         $clientyChurnReason = $stringHelper->removeLineBreaks($churnNotification->comments);
                //     }
                // }

                // $mondayClientsBoardClient = $mondayClientsBoardClients->get($lead->id);
                // if ($mondayClientsBoardClient) {
                //     $businessAreaColumn = collect($mondayClientsBoardClient['column_values'])
                //         ->first(function ($col) {
                //             return isset($col['column']['title']) && $col['column']['title'] === 'Rubro';
                //         }
                //     );
                //     $mondayBusinessArea = $businessAreaColumn['text'] ?? null;
                //     $clientBusinessArea = $mondayBusinessArea ? $mondayBusinessArea : $clientBusinessArea;
                // }
                
                $leadCreatedDate = $lead->lead_created_at;
                $leadCreatedDateStr = $leadCreatedDate->setTimezone($tz)->format('d/m/Y');

                $firstSentProposalInfo = $lead->proposalsInfo->sortBy('id')->first();
                $firstSentProposalDateStr = '';
                if ($firstSentProposalInfo) {
                    $firstSentProposalDateStr = $firstSentProposalInfo->sent_date->setTimezone($tz)->format('d/m/Y');
                }

                $firstSale = $lead->leadSales->first();
                $firstSaleAmount = $firstSale ? (int) $firstSale?->amount : '';
                $firstSaleDate = $firstSale ? $firstSale?->sale_date->setTimezone($tz)->format('d/m/Y') : '';

                $interestTagMap = ['1 (*)' => 1, '2 (**)' => 2, '3 (***)' => 3];
                $leadInterest = collect($lead->tags)->pluck('name')
                    ->intersect(array_keys($interestTagMap))
                    ->map(fn($name) => $interestTagMap[$name])
                    ->first() ?? ''
                ;

                $sdrCleanTagMap = [
                    'Restaurant B2C',
                    'Juguetería -B2C',
                    'Psicólogos -B2C',
                    'Farmacéutico B2C',
                    'Spa/Belleza -B2C',
                    'Indumentaria- B2C',
                    'Veterinarias -B2C',
                    'Venta catálogo-B2C',
                    'Venta de alimentos -B2C',
                    'Instituciones educativas -B2C',
                ];
                $sdrCleanReason = collect($lead->tags)
                    ->filter(function (Tag $tag) use ($sdrCleanTagMap) {
                        return $tag?->tagCategory?->name == 'Back Ventas- SDR' && in_array($tag->name, $sdrCleanTagMap);
                    })
                    ->pluck('name')
                    ->implode(' | ')
                ;

                $noBuyReasonMap = [
                    'Eccomerce',
                    'No tener Demo',
                    'Chats Omnicanal',
                    'Chatbot WhatsApp',
                    'Sistema Gestión ERP',
                    'Fuera de Presupuesto',
                    'WhatsApp Multiagente',
                    'Call center- Llamados',
                ];
                $noBuyReason = collect($lead->tags)
                    ->filter(function (Tag $tag) use ($noBuyReasonMap) {
                        return $tag->tagCategory?->name == 'Back Ventas- SDR' && in_array($tag->name, $noBuyReasonMap);
                    })
                    ->pluck('name')
                    ->implode(' | ')
                ;

                fputcsv($handle, [
                    $lead->id,
                    $countryName,
                    $lead->acquisitionChannel?->name ?? '',
                    $lead->status->name,
                    $lead->status->statusCategory?->name,
                    
                    $lead->quality,
                    $leadInterest,
                    $calendlyMeetingDateStr,
                    $clientyMeetingDateStr,
                    $clientyFirstMeetingDateStr,

                    $clientySecondMeetingDateStr,
                    $clientyThirdMeetingDateStr,
                    $funnelFieldValue,
                    $firstSentProposalDateStr,
                    $firstSaleDate,
                    
                    $firstSaleAmount,
                    $mondayChurnDateStr,
                    $leadCreatedDateStr,
                    $mondayEntryDateStr,
                    $clientyChurnDate,
                    
                    $sdrCleanReason,
                    $noBuyReason,
                    $mondayBusinessArea,
                    $lead->user->fullName,
                    $lead->company,
                    
                    $lead->mainEmail,
                    $lead->mainPhone,
                    $onboarder,
                    $clientType,
                    $mondayChurnReason,
                    
                    $clientyChurnReason,
                    $lead->utm_source ?? '',
                    $lead->utm_medium ?? '',
                    $lead->utm_content ?? '',
                    $lead->utm_campaign ?? '',
                    
                    $lead->utm_keywords ?? '',
                ]);
            }

            if ($processedCount >= $limit) {
                $continue = false;
                break;
            }

            unset($leads);
            gc_collect_cycles();

            $offset = $offset + $chunk;
            $leads = Lead::where('client_id', 2)
                ->with($with)
                ->limit($chunk)
                ->offset($offset)
                ->orderByRaw('lead_created_at ASC, id ASC')
                ->get()
            ;
            if ($leads->isEmpty()) {
                $continue = false;
                break;
            }
        }
        
        fclose($handle);
        SystemHelper::doFlush();

        die();
    }


    public function downloadFailedMeetingsReport(Request $req)
    {
        \Debugbar::disable();

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=failed_meetings_report.csv');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');

        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(300);
        SystemHelper::setMemoryLimitMB(900);

        $handle = fopen('php://output', 'w');
        $headers = [
            'Lead ID',
            'Fecha creación Calendly',
            'Fecha reunión Calendly',
            'Días diferencia',
            'Reunión realizada',
        ];

        fputcsv($handle, $headers);

        $chunk = 300;
        $continue = true;
        $processedCount = 0;
        $offset = $req->input('offset') ?? 0;
        $limit = $req->input('limit') ?? 999999;
        $chunk = ($limit > $chunk) ? $chunk : $limit;
        $leads = Lead::where('client_id', 2)
            ->limit($chunk)
            ->offset($offset)
            // ->with(['status'])
            ->orderByRaw('lead_created_at ASC, id ASC')
            ->get()
        ;

        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
        $failedMeetingStatusList = new Collection(['Re-agendar']);
        $confirmedMeetingStatusList = new Collection(['Reunión coordinada', 'Reunión confirmada']);

        while ($continue) {
            SystemHelper::doFlush();

            foreach ($leads as $lead) {
                $processedCount++;

                $calendlyMeetingDateStr = '';
                $calendlyEvent = resolve(CalendlyScheduledEventService::class)->findFirstByLeadId($lead->id);
                if (!$calendlyEvent) {
                    continue;
                }
                $statusEventLogs = resolve(EventsLogService::class)->findEventsFromOneLead(
                    $lead, ['lead_status_updated']
                );
                $historyStatusNames = $statusEventLogs->pluck('log.status.name');
                
                $meetingWasConfirmed = $historyStatusNames->intersect($confirmedMeetingStatusList)->isNotEmpty();
                if (!$meetingWasConfirmed) {
                    continue;
                }

                $meetingDate = (new DateTime($calendlyEvent->start_time))->setTimezone($tz);
                $meetingDateStr = $meetingDate->format('d/m/Y H:i:s');

                $calendlyCreatedDate = (new DateTime($calendlyEvent->created_at))->setTimezone($tz);
                $calendlyCreatedDateStr = $calendlyCreatedDate->format('d/m/Y H:i:s');

                $daysDiff = $calendlyCreatedDate->diff($meetingDate)->days;

                $wasFailedMeeting = $historyStatusNames->intersect($failedMeetingStatusList)->isNotEmpty();

                fputcsv($handle, [
                    $lead->id,
                    $calendlyCreatedDateStr,
                    $meetingDateStr,
                    $daysDiff,
                    $wasFailedMeeting ? 'No' : 'Si'
                ]);
            }

            if ($processedCount >= $limit) {
                $continue = false;
                break;
            }

            unset($leads);
            gc_collect_cycles();

            $offset = $offset + $chunk;
            $leads = Lead::where('client_id', 2)
                ->limit($chunk)
                ->offset($offset)
                // ->with(['status'])
                ->orderByRaw('lead_created_at ASC, id ASC')
                ->get()
            ;
            if ($leads->isEmpty()) {
                $continue = false;
                break;
            }
        }

        fclose($handle);
        SystemHelper::doFlush();

        die();
    }


    public function downloadCalendlyQuestionAndAnswers(Request $req)
    {
        \Debugbar::disable();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=calendly-questions-answers.csv');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');

        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(300);
        SystemHelper::setMemoryLimitMB(900);

        $handle = fopen('php://output', 'w');
        $headers = ['Lead ID', 'Pregunta', 'Respuesta', 'Fecha'];

        fputcsv($handle, $headers);

        $chunk = 300;
        $continue = true;
        $processedCount = 0;
        $offset = $req->input('offset') ?? 0;
        $limit = $req->input('limit') ?? 999999;
        $chunk = ($limit > $chunk) ? $chunk : $limit;
        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
        $scheduledEvents = CalendlyScheduledEvent::orderBy('created_at')->limit($chunk)->offset($offset)->get();

        while ($continue) {
            SystemHelper::doFlush();
            foreach ($scheduledEvents as $scheduledEvent) {
                $processedCount++;

                $leadIds = $scheduledEvent?->clientyLeadIds ?? [];
                $questionAndAnswers = $scheduledEvent?->invitees[0]['questions_and_answers'] ?? [];
                if (!$leadIds || !$questionAndAnswers) {
                    continue;
                }
                $createdAtStr = (new DateTime($scheduledEvent['created_at']))->format('Y-m-d');
                foreach ($scheduledEvent['clientyLeadIds'] as $leadId) {
                    foreach ($questionAndAnswers as $row) {
                        fputcsv($handle, [$leadId, $row['question'], $row['answer'], $createdAtStr]);
                    }
                }
            }
            if ($processedCount >= $limit) {
                $continue = false;
                break;
            }

            unset($leads);
            gc_collect_cycles();

            $offset = $offset + $chunk;
            $scheduledEvents = CalendlyScheduledEvent::orderBy('created_at')->limit($chunk)->offset($offset)->get();
            if ($scheduledEvents->isEmpty()) {
                $continue = false;
                break;
            }
        }
        fclose($handle);
        SystemHelper::doFlush();
        die();
    }


    public function downloadClientLeadsReport(Request $req, string $subdomain)
    {
        $client = Client::where('subdomain', strtolower($subdomain))->first();
        if (!$client) {
            die('Cliente no encontrado');
        }

        \Debugbar::disable();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=reporte-leads-godixital.csv');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');

        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(300);
        SystemHelper::setMemoryLimitMB(900);

        $handle = fopen('php://output', 'w');
        $headers = [
            'Lead id',
            'Estado',
            'Categoría de estado',
            'Landing',
            'Canal',
            'Estrellas',
            'Fecha de ingreso',
            'Fecha de reunión',
            'Fecha de PE',
            'Fecha de venta',
            'Monto de venta',
            'Mail',
            'Teléfono',
            'Empresa',
            'Vendedor',
            'País',
        ];
        fputcsv($handle, $headers);

        $chunk = 300;
        $continue = true;
        $processedCount = 0;
        $offset = $req->input('offset') ?? 0;
        $limit = $req->input('limit') ?? 999999;
        $chunk = ($limit > $chunk) ? $chunk : $limit;
        $builder = Lead::where('client_id', $client->id)
            ->with([
                'user',
                'tags',
                'status',
                'landing',
                'leadSales',
                'leadContacts',
                'proposalsInfo',
                'tags.tagCategory',
                'leadContactEmails',
                'leadContactPhones',
                'acquisitionChannel',
                'status.statusCategory',
                'leadCustomFieldsValues',
                'leadCustomFieldsValues.leadCustomField'
            ])
            ->orderBy('created_at', 'desc')
        ;

        $leads = $builder->limit($chunk)->offset($offset)->get();
        while ($continue) {
            SystemHelper::doFlush();

            $processedCount++;
            if ($processedCount >= $limit) {
                $continue = false;
                break;
            }

            foreach ($leads as $lead) {
                $countryTag = $lead->tags()->whereHas('tagCategory', function ($q) {
                    $q->where('name', 'País');
                })->first();

                $csvRow = [
                    $lead->id,
                    $lead->status->name,
                    $lead->status->statusCategory?->name,
                    $lead->landing?->url,
                    $lead->acquisitionChannel?->name,
                    $lead->quality,
                    $lead->lead_created_at ?? $lead->created_at,
                    Str::limit($lead->getCustomFieldValueByName('Día y hora de reunión'), 65, '...'),
                    $lead->proposalsInfo->first()?->sent_date?->format('d/m/Y'),
                    $lead->leadSales->first()?->sale_date?->format('d/m/Y'),
                    $lead->leadSales->first()?->amount,
                    $lead->leadContactEmails->first()?->email,
                    $lead->leadContactPhones->first()?->phone,
                    $lead->company,
                    $lead->user->fullName,
                    $countryTag?->name,
                ];
                fputcsv($handle, $csvRow);
            }

            unset($leads);
            gc_collect_cycles();

            $offset = $offset + $chunk;
            $leads = $builder->limit($chunk)->offset($offset)->get();
            if ($leads->isEmpty()) {
                $continue = false;
                break;
            }
        }
        fclose($handle);
        SystemHelper::doFlush();
        die();
    }

}