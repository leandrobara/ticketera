<?php

namespace App\Exports;

use DateTime;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\Models\WhatsAppSendingMessage;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class WhatsAppSendingExport implements FromView, WithProperties, WithStyles
{

    use Exportable;

    private $whatsAppSendingMessages;


    public function __construct(Collection $whatsAppSendingMessages)
    {
        $this->whatsAppSendingMessages = $whatsAppSendingMessages;
    }


    public function properties(): array
    {
        return [
            'creator' => 'Clienty',
            'company' => 'Clienty',
            'category' => 'Reporte',
            'lastModifiedBy' => 'Clienty',
            'title' => 'Reporte whatsapp enviados de Clienty',
            'keywords' => 'clienty,reporte,consultas,whatsapp',
            'subject' => 'Reporte whatsapp enviados de Clienty',
            'description' => 'Reporte whatsapp enviados de Clienty',
        ];
    }


    public function view(): View
    {
        return view('api.exports.whatsapp_sending_export', [
            'report' => $this->buildReport(),
            'userName' => $this->getUserName(),
            'textMessage' => $this->getTextMessage(),
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A:M')->getAlignment()->applyFromArray([
            'wrapText' => true,
            'textRotation' => 0,
            'vertical' => Alignment::VERTICAL_CENTER,
        ]);
        //columns A:H
        for ($i = 65; $i <= 73; $i++) {
            $column = chr($i);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }


    public function buildReport(): array
    {
        $report = [];
        foreach ($this->whatsAppSendingMessages as $whatsAppSendingMessage) {
            if (!$whatsAppSendingMessage->lead) {
                continue;
            }
            $lead = $whatsAppSendingMessage->lead;
            $report[] = [
                'lead_id' => $lead->id,
                'lead_company' => $lead->company,
                'id' => $whatsAppSendingMessage->id,
                'type' => $this->getSendingType($whatsAppSendingMessage),
                'date' => $this->getFormattedDate($whatsAppSendingMessage),
                'hex_color' => $this->getHexColor($whatsAppSendingMessage),
                'lead_contact_name' => $lead->mainLeadContact->name ?? null,
                'lead_contact_phone' => $whatsAppSendingMessage->phone_number,
                'message_status' => $this->getStatus($whatsAppSendingMessage),
                'lead_contact_last_name' => $lead->mainLeadContact->last_name ?? null,
            ];
        }
        return $report;
    }


    private function getFormattedDate(WhatsAppSendingMessage $whatsAppSendingMessage): DateTime
    {
        if ($this->isCancelled($whatsAppSendingMessage)) {
            $date = $whatsAppSendingMessage->cancelled_date;
        } else if ($this->isPaused($whatsAppSendingMessage)) {
            $date = $whatsAppSendingMessage->paused_date;
        } else if ($this->isPending($whatsAppSendingMessage)) {
            $date = $whatsAppSendingMessage->send_date;
        } else {
            $date = $whatsAppSendingMessage->send_date;
        }
        $clientTz = new DateTimeZone($whatsAppSendingMessage->client->timezone);
        $date = $date->setTimezone($clientTz);
        return $date;
    }


    private function getStatus(WhatsAppSendingMessage $whatsAppSendingMessage): string
    {
        $status = 'Estado desconocido';

        if ($this->isCancelled($whatsAppSendingMessage)) {
            $status = "Cancelado";
        } else if ($this->isPaused($whatsAppSendingMessage)) {
            $status = "Pausado";
        } else if ($this->hasError($whatsAppSendingMessage)) {
            $status = "Error. No enviado";
        } else if ($this->isSuccess($whatsAppSendingMessage)) {
            $status = "Enviado correctamente";
        } else if ($this->isPending($whatsAppSendingMessage)) {
            $status = 'Pendiente de envío';
        } else if ($this->isNotSuccess($whatsAppSendingMessage)) {
            $status = "Error. No enviado";
        }
        return $status;
    }


    private function getHexColor(WhatsAppSendingMessage $whatsAppSendingMessage): string
    {
        $classStatus = "#575757";

        if ($this->isCancelled($whatsAppSendingMessage)) {
            $classStatus = "#ca4f3e";
        } else if ($this->isPaused($whatsAppSendingMessage)) {
            $classStatus = "#f0a73c";
        } else if ($this->hasError($whatsAppSendingMessage)) {
            $classStatus = "#ca4f3e";
        } else if ($this->isSuccess($whatsAppSendingMessage)) {
            $classStatus = "#38ab69";
        } else if ($this->isPending($whatsAppSendingMessage)) {
            $classStatus = '#5c80d1';
        } else if ($this->isNotSuccess($whatsAppSendingMessage)) {
            $classStatus = "#ca4f3e";
        }
        return $classStatus;
    }
 

    private function getSendingType(WhatsAppSendingMessage $whatsAppSendingMessage): string
    {
        $type = "WhatsApp Web";
        if ($whatsAppSendingMessage->type == "wap_sender") {
            $type = "Clienty WAP Sender";
        }
        if ($whatsAppSendingMessage->type == "wapi") {
            $type = "Clienty WAPI";
        }
        return $type;
    }


    private function getTextMessage(): ?string
    {
        if ($this->whatsAppSendingMessages->first() && $this->whatsAppSendingMessages->first()->whatsAppSending) {
            return $this->whatsAppSendingMessages->first()->whatsAppSending->whatsAppSendingMessageText->message;
        }
        return null;
    }


    private function getUserName(): ?string
    {
        if ($this->whatsAppSendingMessages->first() && $this->whatsAppSendingMessages->first()->whatsAppSending) {
            return $this->whatsAppSendingMessages->first()->whatsAppSending->user->fullName;
        }
        return null;
    }


    private function isCancelled(WhatsAppSendingMessage $whatsAppSendingMessages): bool
    {
        return $whatsAppSendingMessages->cancelled_date ? true : false;
    }


    private function isPaused(WhatsAppSendingMessage $whatsAppSendingMessages): bool
    {
        return $whatsAppSendingMessages->paused_date ? true : false;
    }


    private function hasError(WhatsAppSendingMessage $whatsAppSendingMessages): bool
    {
        return ($whatsAppSendingMessages->sent_date && !$whatsAppSendingMessages->success) ? true : false;
    }


    private function isSuccess(WhatsAppSendingMessage $whatsAppSendingMessages): bool
    {
        return ($whatsAppSendingMessages->sent_date && $whatsAppSendingMessages->success) ? true : false;
    }


    private function isPending(WhatsAppSendingMessage $whatsAppSendingMessages): bool
    {
        return !$whatsAppSendingMessages->sent_date ? true : false;
    }


    private function isNotSuccess(WhatsAppSendingMessage $whatsAppSendingMessages): bool
    {
        return !$whatsAppSendingMessages->success ? true : false;
    }

}
