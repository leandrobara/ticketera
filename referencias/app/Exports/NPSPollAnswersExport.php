<?php

namespace App\Exports;

use DateTime;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Client;
use App\Models\NPSPoll;
use Illuminate\Support\Str;
use App\Models\NPSPollAnswer;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use Illuminate\Contracts\View\View;
use App\Models\WhatsAppSendingMessage;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class NPSPollAnswersExport implements FromView, WithProperties, WithStyles
{

    use Exportable;

    protected Collection $NPSPollAnswers;


    public function __construct(private readonly NPSPoll $NPSPoll)
    {
        $this->NPSPollAnswers = $NPSPoll->NPSPollAnswers->sortBy(function ($NPSPollAnswer) {
            return $NPSPollAnswer->client->name . '-' . $NPSPollAnswer->user->name;
        });
    }


    public function properties(): array
    {
        return [
            'creator' => 'Clienty',
            'company' => 'Clienty',
            'category' => 'Reporte',
            'lastModifiedBy' => 'Clienty',
            'title' => 'Reporte de encuestas de Clienty',
            'subject' => 'Reporte de encuestas de Clienty',
            'keywords' => 'clienty,reporte,consultas,encuestas',
            'description' => 'Reporte de encuestas de Clienty',
        ];
    }


    public function view(): View
    {
        return view('api.exports.nps_poll_export', [
            'NPSPollData' => $this->buildNPSPollDataReport(),
            'NPSPollAnswerData' => $this->buildNPSPollAnswerDataReport(),
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A:F')->getAlignment()->applyFromArray([
            'wrapText' => true,
            'textRotation' => 0,
            'vertical' => Alignment::VERTICAL_CENTER,
        ]);
        //columns A:F
        for ($i = 65; $i <= 71; $i++) {
            $column = chr($i);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }


    private function buildNPSPollDataReport(): array
    {
        $report = [
            'score_title' => $this->NPSPoll->score_title,
            'comments_title' => $this->NPSPoll->comments_title,
            'total_answers_count' => $this->getTotalAnswersCount(),
            'applied_clients_legend' => $this->getAppliedClientsLegend(),
            'total_answers_completed_count' => $this->getTotalAnswersCompletedCount(),
        ];

        return $report;
    }


    private function buildNPSPollAnswerDataReport(): array
    {
        $report = [];
        
        foreach ($this->NPSPollAnswers as $NPSPollAnswer) {
            $closedDate = $this->isAnswered($NPSPollAnswer) ?
                $this->getFormattedDate($NPSPollAnswer->closed_date) :
                null
            ;
            $row = [
                'closed_date' => $closedDate,
                'score' => $NPSPollAnswer->score,
                'client' => $NPSPollAnswer->client,
                'comments' => $NPSPollAnswer->comments,
                'user_email' => $NPSPollAnswer->user->email,
                'user_phone' => $NPSPollAnswer->user->phone,
                'user_full_name' => $NPSPollAnswer->user->fullName,
            ];
            foreach ($row as $key => $val) {
                if (!is_string($val)) {
                    continue;
                }
                // Para evitar error al querer hacer cálculo de fórmula por empezar con =
                if (Str::startsWith($val, '=')) {
                    $row[$key] = Str::replaceFirst('=', '', $row[$key]);
                }
                // Para quitar caracteres especiales que rompen el XML
                if (is_string($val)) {
                    $row[$key] = preg_replace(
                        '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF].|\xEF\xBF[\xBE\xBF]/',
                        "\xEF\xBF\xBD",
                        $row[$key]
                    );
                }
            }
            $report[] = $row;
        }
        return $report;
    }


    private function getFormattedDate(DateTime $date): DateTime
    {
        $clientyClient = Client::find(2); // Chanchada
        $clientTz = new DateTimeZone($clientyClient->timezone);
        $date = $date ? (new DateTime($date))->setTimezone($clientTz) : null;
        return $date;
    }


    private function getAppliedClientsLegend(): string
    {
        $clients = resolve(ClientService::class)->list([]);
        $systemClientCount = $clients->count();

        $answersClientCount = $this->NPSPollAnswers->pluck('client_id')->unique()->count();

        if ($answersClientCount == $systemClientCount) {
            return "Todos los clientes";
        }
        if ($answersClientCount == 1 ) {
            return "1 cliente";
        }
        return "{$answersClientCount} clientes";
    }


    private function getTotalAnswersCount(): int
    {
        return $this->NPSPollAnswers->count();
    }


    private function getTotalAnswersCompletedCount(): int
    {
        return $this->NPSPollAnswers->filter(function ($item) {
            return $item->close_reason == 'user_scored' || $item->close_reason == 'user_close_comments';
        })->count();
    }


    private function isAnswered(NPSPollAnswer $NPSPollAnswer): bool
    {
        return $NPSPollAnswer->score ? true : false;
    }

}
