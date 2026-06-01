<?php

namespace App\DTO\Reports\Dashboard;

use DateTime;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Client;
use Illuminate\Support\Collection;


class PeriodDTO
{

    private function __construct(
        public Client $client,
        public string $periodName,
        public DateTime $currentDateEnd,
        public DateTime $previousDateEnd,
        public DateTime $currentDateStart,
        public DateTime $previousDateStart,
        public Collection $previousPeriods,
    ) {
    }


    public static function buildFromName(Client $client, string $periodName): PeriodDTO
    {
        if ($periodName == 'last_week') {
            $currentDateStart = Carbon::now()->subWeek(1)->startOfWeek();
            $currentDateEnd = Carbon::now()->subWeek(1)->endOfWeek();
            $previousDateEnd = Carbon::now()->subWeek(2)->endOfWeek();
            $previousDateStart = Carbon::now()->subWeek(2)->startOfWeek();
        }
        if ($periodName == 'current_week') {
            $currentDateStart = Carbon::now()->startOfWeek();
            $currentDateEnd = Carbon::now()->endOfWeek();
            $previousDateStart = Carbon::now()->subWeek(1)->startOfWeek();
            $previousDateEnd = Carbon::now()->subWeek(1)->endOfWeek();
        }

        if ($periodName == 'last_month') {
            $currentDateStart = Carbon::now()->subMonth(1)->startOfMonth();
            $currentDateEnd = Carbon::now()->subMonth(1)->endOfMonth();
            $previousDateStart = Carbon::now()->subMonth(2)->startOfMonth();
            $previousDateEnd = Carbon::now()->subMonth(2)->endOfMonth();
        }
        if ($periodName == 'current_month') {
            $currentDateStart = Carbon::now()->startOfMonth();
            $currentDateEnd = Carbon::now();
            $previousDateStart = Carbon::now()->subMonth(1)->startOfMonth();
            $previousDateEnd = Carbon::now()->subMonth(1)->setDay($currentDateEnd->day);
        }

        if ($periodName == 'last_year') {
            $currentDateStart = Carbon::now()->subYear(1)->startOfYear();
            $currentDateEnd = Carbon::now()->subYear(1)->endOfYear();
            $previousDateEnd = Carbon::now()->subYear(2)->endOfYear();
            $previousDateStart = Carbon::now()->subYear(2)->startOfYear();
        }
        if ($periodName == 'current_year') {
            $currentDateStart = Carbon::now()->startOfYear();
            $currentDateEnd = Carbon::now();
            $previousDateStart = Carbon::now()->subYear(1)->startOfYear();
            $previousDateEnd = Carbon::now()->subYear(1)->endOfYear();
        }

        if ($periodName == 'last_30_days') {
            $currentDateStart = Carbon::now()->subDays(29);
            $currentDateEnd = Carbon::now()->today();
            $previousDateStart = Carbon::now()->subDays(59);
            $previousDateEnd = Carbon::now()->subDays(30);
        }
        if ($periodName == 'last_7_days') {
            $currentDateStart = Carbon::now()->subDays(6);
            $currentDateEnd = Carbon::now()->today();
            $previousDateStart = Carbon::now()->subDays(13);
            $previousDateEnd = Carbon::now()->subDays(7);
        }

        $currentDateEnd = $currentDateEnd->toDateTime();
        $previousDateEnd = $previousDateEnd->toDateTime();
        $currentDateStart = $currentDateStart->toDateTime();
        $previousDateStart = $previousDateStart->toDateTime();

        $previousPeriods = self::buildPreviousPeriods($periodName);

        $utcTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        
        $currentDateStart->setTime(12, 0, 0)->setTimezone($clientTz)->setTime(0, 0, 0);
        $currentDateEnd->setTime(12, 0, 0)->setTimezone($clientTz)->setTime(23, 59, 59);
        
        $previousDateStart->setTime(12, 0, 0)->setTimezone($clientTz)->setTime(0, 0, 0);
        $previousDateEnd->setTime(12, 0, 0)->setTimezone($clientTz)->setTime(23, 59, 59);


        $currentDateEnd->setTimezone($utcTz);
        $currentDateStart->setTimezone($utcTz);
        $previousDateStart->setTimezone($utcTz);
        $previousDateEnd->setTimezone($utcTz);
        
    
        $dto = new self(
            client: $client,
            periodName: $periodName,
            currentDateEnd: $currentDateEnd,
            previousDateEnd: $previousDateEnd,
            previousPeriods: $previousPeriods,
            currentDateStart: $currentDateStart,
            previousDateStart: $previousDateStart,
        );
        return $dto;
    }


    protected static function buildPreviousPeriods(string $periodName): Collection
    {
        $previousPeriods = [];
        $today = Carbon::now()->locale('es');

        if ($periodName == 'last_7_days' || $periodName == 'current_week' || $periodName == 'last_30_days') {
            $currentWeekStart = $today->startOfWeek();
            for ($i = 0; $i < 5; $i++) {
                $weekStart = $currentWeekStart->copy()->subWeeks($i);
                $weekEnd = $weekStart->copy()->endOfWeek();
                $label = 'Sem. ' . $weekStart->translatedFormat('d') . ' ' . $weekStart->translatedFormat('F');
                $previousPeriods[] = [
                    'label' => $label, 'date_end' => $weekEnd->toDateTime(), 'date_start' => $weekStart->toDateTime()
                ];
            }
        }

        if ($periodName == 'last_week') {
            $lastWeekStart = $today->startOfWeek()->subWeek();
            for ($i = 0; $i < 5; $i++) {
                $weekStart = $lastWeekStart->copy()->subWeeks($i);
                $weekEnd = $weekStart->copy()->endOfWeek();
                $label = 'Sem. ' . $weekStart->translatedFormat('d') . ' ' . $weekStart->translatedFormat('F');
                $previousPeriods[] = [
                    'label' => $label, 'date_end' => $weekEnd->toDateTime(), 'date_start' => $weekStart->toDateTime()
                ];
            }
        }

        if ($periodName == 'last_month') {
            $lastMonthStart = $today->startOfMonth()->subMonth();
            for ($i = 0; $i < 5; $i++) {
                $monthStart = $lastMonthStart->copy()->subMonths($i);
                $monthEnd = $monthStart->copy()->endOfMonth();
                $label = $monthStart->translatedFormat('F');
                $previousPeriods[] = [
                    'label' => $label, 'date_end' => $monthEnd->toDateTime(), 'date_start' => $monthStart->toDateTime()
                ];
            }
        }

        if ($periodName == 'current_month') {
            $currentMonthStart = $today->startOfMonth();
            for ($i = 0; $i < 5; $i++) {
                $monthStart = $currentMonthStart->copy()->subMonths($i);
                $monthEnd = $monthStart->copy()->endOfMonth();
                $label = $monthStart->translatedFormat('F');
                $previousPeriods[] = [
                    'label' => $label, 'date_end' => $monthEnd->toDateTime(), 'date_start' => $monthStart->toDateTime()
                ];
            }
        }

        if ($periodName == 'current_year') {
            $currentYearStart = $today->startOfYear();
            for ($i = 0; $i < 6; $i++) {
                $monthStart = $currentYearStart->copy()->subMonths($i);
                $monthEnd = $monthStart->copy()->endOfMonth();
                $label = $monthStart->translatedFormat('F');
                $previousPeriods[] = [
                    'label' => $label, 'date_end' => $monthEnd->toDateTime(), 'date_start' => $monthStart->toDateTime()
                ];
            }
        }

        if ($periodName == 'last_year') {
            $lastYearStart = $today->copy()->startOfYear()->subYear();
            $quarters = [
                ['start' => 9, 'end' => 11, 'label' => 'oct-dic'],
                ['start' => 6, 'end' => 8, 'label' => 'jul-sep'],
                ['start' => 3, 'end' => 5, 'label' => 'abr-jun'],
                ['start' => 0, 'end' => 2, 'label' => 'ene-mar'],
            ];
            foreach ($quarters as $quarter) {
                $label = $quarter['label'] . ' ' . $lastYearStart->year;
                $monthStart = $lastYearStart->copy()->addMonths($quarter['start']);
                $monthEnd = $lastYearStart->copy()->addMonths($quarter['end'])->endOfMonth();
                $previousPeriods[] = [
                    'label' => $label, 'date_end' => $monthEnd->toDateTime(), 'date_start' => $monthStart->toDateTime()
                ];
            }
        }

        return new Collection(array_reverse($previousPeriods));
    }



    public function toArray(): array
    {
        $arr = [
            'name' => $this->periodName,
            'current' => [
                'date_end' => $this->currentDateEnd->format('Y-m-d\TH:i:sP'),
                'date_start' => $this->currentDateStart->format('Y-m-d\TH:i:sP'),
            ],
            'previous' => [
                'date_end' => $this->previousDateEnd->format('Y-m-d\TH:i:sP'),
                'date_start' => $this->previousDateStart->format('Y-m-d\TH:i:sP'),
            ],
            // 'previous_periods' => $this->previousPeriods,
        ];
        return $arr;
    }
   
}
