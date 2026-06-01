<?php

namespace App\Services\API;

use Exception;
use App\Models\Lead;
use Illuminate\Support\Str;
use App\Services\API\LeadService;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use Illuminate\Support\Facades\Http;
use App\Services\API\LeadContactEmailService;
use App\Services\API\CalendlyScheduledEventService;


class ClientyConfigCustomerTrackingJourneyService
{

    public function __construct(
        private readonly LeadService $leadService,
        private readonly ClientService $clientService,
        private readonly LeadContactEmailService $leadContactEmailService,
        private readonly CalendlyScheduledEventService $calendlyScheduledEventService
    ) {
    }


    /**
     * Arma la vista del “customer tracking journey” de un prospecto
     * Buscando por ID o email de prospecto
     *
     * Busca en Calendly las preguntas y respuestas del prospecto
     * Busca en las notas, en los custom fields y en la hoja de Google Sheets links de Fathom
     *
     * @return Collection
     */
    public function getJourneyData(string $searchTerm): Collection
    {
        $leadIdOrEmail = trim($searchTerm);
        if (!$leadIdOrEmail) {
            return new Collection();
        }

        $journeyDataArr = [];
        $leadId = (int) $leadIdOrEmail;
        $leadEmail = $leadId ? null : strtolower(trim($leadIdOrEmail));
        $clientyClientModel = $this->clientService->findOneById((int) config('app.clienty.client_id'));

        if ($leadId) {
            $lead = $this->leadService->find($leadId);
            $leads = new Collection([$lead]);
        } else {
            $leadIds = $this->leadContactEmailService
                ->findByClientAndEmail($clientyClientModel, $leadEmail)
                ->pluck('lead_id')
            ;
            $leads = $this->leadService->findByClientAndIds($clientyClientModel, $leadIds);
        }

        foreach ($leads as $lead) {
            // cargo info del lead para el front
            $lead->user;
            $lead->mainEmail;

            $calendlyInfo = $this->getCalendlyDataByIdOrEmail($leadIdOrEmail);
            $closerFathomLinksInfo = $this->getCloserSalesMeetingsFathomInfo($lead);
            $callConfirmerFathomLinksInfo = $this->getCallConfirmerSalesMeetingsFathomInfo($lead);
            $customerSuccessMeetings = $this->getCustomerSuccessOnboardingMeetingsFathomInfo($lead);

            $journeyDataArr[] = [
                'lead' => $lead,
                'calendlyInfo' => $calendlyInfo,
                'fathomLinksInfo' => [
                    'closerSales' => $closerFathomLinksInfo,
                    'customerSuccess' => $customerSuccessMeetings,
                    'callConfirmerSales' => $callConfirmerFathomLinksInfo,
                ],
            ];
        }
        return new Collection($journeyDataArr);
    }


    // Busca links de Fathom en las notas del lead (que es donde están)
    private function getCallConfirmerSalesMeetingsFathomInfo(Lead $lead): Collection
    {
        $fathomLinksInfo = new Collection();
        foreach ($lead->notes as $note) {
            $links = $this->extractFathomLinksFromText($note->text ?? '');
            foreach ($links as $link) {
                $fathomLinksInfo->push(['url' => $link, 'createdAt' => $note->created_at]);
            }
        }
        return $fathomLinksInfo->values();
    }

    
    // Busca links de Fathom en los custom fields del lead
    private function getCloserSalesMeetingsFathomInfo(Lead $lead): Collection
    {
        $fathomLinksInfo = new Collection();
    
        foreach ($lead->leadCustomFieldsValues as $customFieldValue) {
            $fathomLinks = $this->extractFathomLinksFromText($customFieldValue->value ?? '');
            foreach ($fathomLinks as $fathomLink) {
                $fathomLinksInfo->push(['url' => $fathomLink, 'createdAt' => $customFieldValue->created_at]);
            }
        }
        return $fathomLinksInfo->values();
    }


    private function getCalendlyDataByIdOrEmail(string $leadIdOrEmail): Collection
    {
        if (!$leadIdOrEmail) {
            return new Collection();
        }

        $calendlyData = [];
        $calendlyScheduledEvents = $this->calendlyScheduledEventService->findByLeadIdOrEmail($leadIdOrEmail);
        foreach ($calendlyScheduledEvents as $calendlyScheduledEvent) {
            foreach (($calendlyScheduledEvent->invitees ?? []) as $invitee) {
                $calendlyData[] = [
                    'name' => $invitee['name'] ?? null,
                    'id' => $calendlyScheduledEvent->id,
                    'email' => $invitee['email'] ?? null,
                    'createdAt' => $invitee['created_at'] ?? null,
                    'eventName' => $calendlyScheduledEvent['name'] ?? null,
                    'utmSource' => $invitee['tracking']['utm_source'] ?? null,
                    'questionsAndAnswers' => $invitee['questions_and_answers'] ?? [],
                    'clientyLeadIds' => $calendlyScheduledEvent->clientyLeadIds ?? [],
                ];
            }
        }
        return new Collection($calendlyData);
    }


    /**
     * Busca links de Fathom en una hoja de Google Sheets
     * Se asume que la hoja de Google Sheets es publica
     */
    private function getCustomerSuccessOnboardingMeetingsFathomInfo(Lead $lead): Collection
    {
        // la hoja de Google Sheets tiene que ser publica
        $leadIdColumn = 'B';
        $sheetName = 'Meetings CS';
        $sheetId = '1plExNDZd2dlqKOZDtCl6ceUg9AtLEONU1wlf5OJwTr0';
        // $sheetId = '1mcRtDUIKbsafNW0LK6d8al1uSeF7kVG03qXN2UP6uU0'; // copia
        $query = "select * where {$leadIdColumn} = {$lead->id}";

        $url = 'https://docs.google.com/spreadsheets/d/{sheetId}/gviz/tq?tqx=out:csv&sheet={sheetName}&tq={query}';
        $url = Str::replaceFirst('{sheetId}', rawurlencode($sheetId), $url);
        $url = Str::replaceFirst('{sheetName}', rawurlencode($sheetName), $url);
        $url = Str::replaceFirst('{query}', rawurlencode($query), $url);

        $response = Http::timeout(30)->get($url);
        if (!$response->ok()) {
            return new Collection();
        }

        return $this->csvToCollection($response->body())
            ->map(fn (array $row) => [
                'url' => $row['Link'] ?? null,
                'stage' => $row['Etapa'] ?? null,
                'createdAt' => $row['Fecha'] ?? null,
                'userName' => $row['Persona'] ?? null,
                'clientName' => $row['Cliente'] ?? null,
                'leadId' => $row['Prospecto ID'] ?? null,
                'comments' => $row['Comentarios'] ?? null,
                'meetingId' => $row['ID reunión'] ?? null,
                'guestEmails' => $row['Invitados'] ?? null,
            ])
        ;
    }


    // Devuelve las URLs únicas de Fathom encontradas en $text.
    private function extractFathomLinksFromText(string $text): array
    {
        $pattern = '~(?:https?://)?fathom\.video/share/[A-Za-z0-9_-]+~i';
        preg_match_all($pattern, $text, $matches);
        $links = [];
        foreach ($matches[0] as $link) {
            if (!preg_match('~^https?://~i', $link)) {
                $link = 'https://' . $link;
            }
            $links[] = $link;
        }
        return array_values(array_unique($links));
    }


    private function csvToCollection(string $csv): Collection
    {
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $csv);
        rewind($fp);
        $headers = fgetcsv($fp);
        $rows = [];
        while (($row = fgetcsv($fp)) !== false) {
            $rows[] = array_combine($headers, $row);
        }
        fclose($fp);
        return collect($rows);
    }

}
