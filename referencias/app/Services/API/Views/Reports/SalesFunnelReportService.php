<?php

namespace App\Services\API\Views\Reports;

use App\Models\Lead;
use App\Models\Client;
use App\Models\Status;
use App\Models\LeadSale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\StatusService;
use Illuminate\Database\Eloquent\Builder;
use App\Services\API\StatusCategoryService;
use App\Services\Traits\GetClientFromRequest;


class SalesFunnelReportService
{

    use GetClientFromRequest;

    
    public function __construct(
        protected readonly StatusCategoryService $statusCategoryService,
    ) {
    }


    public function list(array $opts = []): Collection
    {
        $client = $this->getClient();
        $averageTicket = $opts['filters']['average_ticket'];

        $report = new Collection();
        $statusCategories = $this->statusCategoryService->findAllByClient($client)->sortBy('order');
        foreach ($statusCategories as $index => $statusCategory) {
            $saleProbabilityPercentage = $statusCategory->status->pluck('sale_probability')->avg();
            $leadsCount = $this->getLeadsCount($statusCategory->status, $opts['filters'] ?? []);
            $expectedTicketAmount = $averageTicket * ($saleProbabilityPercentage / 100) * $leadsCount;
            $reportRow = [
                'leadsCount' => $leadsCount,
                'statusCategory' => $statusCategory,
                'averageTicket' => (int) $averageTicket,
                'expectedTicketAmount' => (int) $expectedTicketAmount,
                'conversionRatePercentage' => 0, // se calcula al final,
                'saleProbabilityPercentage' => (int) $saleProbabilityPercentage,
                'conversionRateToTotalPercentage' => 0, // se calcula al final,
            ];
            $report->push($reportRow);
        }
        // Esto suma a cada categoría, secuencialmente los leads de las categorías siguientes
        // Para dar el aspecto "de embudo" y de acumulación secuencial de leads por categoría.
        $report = $this->sumHighOrderCategoriesLeadsCount($report);
        $report = $this->calculateReportConversionRatePercentage($report);
        return $report;
    }


    public function getAverageTicketAmount(array $opts = []): int
    {
        $client = $this->getClient();
        $dateEnd = $opts['filters']['date_end'] ?? null;
        $dateStart = $opts['filters']['date_start'] ?? null;

        $query = LeadSale::select(DB::raw('AVG(LeadsSales.amount) as average_ticket_amount'))
            ->leftJoin('Leads', 'Leads.id', '=', 'LeadsSales.lead_id')
            ->join('Status', 'Status.id', '=', 'Leads.status_id')
            ->join('StatusCategories', 'StatusCategories.id', '=', 'Status.status_category_id')
            ->where('StatusCategories.is_irrelevant', false)
            ->where('Leads.client_id', $client->id)
            ->where('LeadsSales.client_id', $client->id)
            ->whereNull('Leads.deleted_at')
            ->whereNull('LeadsSales.deleted_at')
        ;
        if ($dateStart) {
            $query->whereRaw(
                "DATE(Leads.lead_created_at) <= '{$dateStart->format('Y-m-d')}'"
            );
        }
        if ($dateEnd) {
            $query->whereRaw(
                "DATE(Leads.lead_created_at) <= '{$dateEnd->format('Y-m-d')}'"
            );
        }
        $result = $query->first();
        return (int) $result->average_ticket_amount;
    }


    private function sumHighOrderCategoriesLeadsCount(Collection $report): Collection
    {
        $newReport = new Collection();
        foreach ($report as $index => $currentReportRow) {
            $currentCategoryOrder = $currentReportRow['statusCategory']->order;
            $nextLeadsCountSum = $report
                ->where('statusCategory.is_irrelevant', false)
                ->where('statusCategory.order', '>', $currentCategoryOrder)
                ->sum('leadsCount')
            ;
            $currentReportRow['leadsCount'] = $currentReportRow['leadsCount'] + $nextLeadsCountSum;
            $newReport->push($currentReportRow);
        }
        return $newReport;
    }


    private function calculateReportConversionRatePercentage(Collection $report): Collection
    {
        // Recordar: el método sumHighOrderCategoriesLeadsCount() suma secuencialmente los totales
        // dejando en la primer categoría la suma de todos los leads que ingresaron al sistema.
        $nonIrrelevantLeadsCount = $report->get(0)['leadsCount'];
        $irrelevantLeadsCount = $report->where('statusCategory.is_irrelevant', false)->pluck('leadsCount')->sum();
        $totalLeadsCount = $nonIrrelevantLeadsCount + $irrelevantLeadsCount;

        $newReport = new Collection();
        $totalLeadsCount = $report->get(0)['leadsCount'];
        foreach ($report as $index => $currentReportRow) {
            if ($index == 0) {
                $currentReportRow['conversionRatePercentage'] = 0;
                $newReport->push($currentReportRow);
                continue;
            }

            $previousIndex = $index - 1;
            $previousReportRow = $report->get($previousIndex);
            $currentLeadsCount = $currentReportRow['leadsCount'];
            $previousLeadsCount = $previousReportRow['leadsCount'];
            $isIrrelevant = $currentReportRow['statusCategory']->is_irrelevant;
            // El último pero SIN contar Irrelevante
            $isLastCategory = $index == ($report->pluck('statusCategories')->count() - 1 - 1);

            if ($isIrrelevant) {
                $conversionRatePercentage = $totalLeadsCount ? ($currentLeadsCount * 100 / $totalLeadsCount) : 0;
                $conversionRateToTotalPercentage = $conversionRatePercentage;
            } else {
                $conversionRatePercentage = $previousLeadsCount ? ($currentLeadsCount * 100 / $previousLeadsCount) : 0;
                $conversionRateToTotalPercentage = $nonIrrelevantLeadsCount
                    ? ($currentLeadsCount * 100 / $nonIrrelevantLeadsCount)
                    : 0
                ;
            }
            // else if ($isLastCategory) {
            //     $partialLeadsCount = $nonIrrelevantLeadsCount;
            //     $conversionRatePercentage = $partialLeadsCount ? ($currentLeadsCount * 100 / $partialLeadsCount) : 0;
            // }

            $currentReportRow['conversionRatePercentage'] = $conversionRatePercentage;
            $currentReportRow['conversionRateToTotalPercentage'] = $conversionRateToTotalPercentage;
            $newReport->push($currentReportRow);
        }
        return $newReport;
    }


    private function getLeadsCount(Collection $statusList, array $filters = []): int
    {
        $client = $this->getClient();
        if ($statusList->isEmpty()) {
            return 0;
        }

        $tagIds = $filters['tag_id'] ?? [];
        $userIds = $filters['user_id'] ?? [];
        $dateEnd = $filters['date_end'] ?? null;
        $dateStart = $filters['date_start'] ?? null;
        $channelIds = $filters['acquisition_channel_id'] ?? null;

        $leadsQuery = DB::table('Leads')
            ->whereNull('Leads.deleted_at')
            ->where('Leads.client_id', $client->id)
            ->whereIn('Leads.status_id', $statusList->pluck('id'))
        ;
        if ($userIds) {
            $leadsQuery->whereIn('Leads.user_id', $userIds);
        }
        if ($channelIds) {
            $leadsQuery->whereIn('Leads.acquisition_channel_id', $channelIds);
        }
        if ($tagIds) {
            $leadsQuery->join('Leads_Tags', 'Leads.id', '=', 'Leads_Tags.lead_id');
            $leadsQuery->whereIn('Leads_Tags.tag_id', $tagIds);
        }
        if ($dateStart) {
            $leadsQuery->whereRaw(
                "DATE(Leads.lead_created_at) >= '{$dateStart->format('Y-m-d')}'"
            );
        }
        if ($dateEnd) {
            $leadsQuery->whereRaw(
                "DATE(Leads.lead_created_at) <= '{$dateEnd->format('Y-m-d')}'"
            );
        }
        $leadsCount = $leadsQuery->count();
        return $leadsCount;
    }


    public function export(array $options): Collection
    {
        $options['limit'] = 9999999999999999;
        return $this->list($options);
    }

}
