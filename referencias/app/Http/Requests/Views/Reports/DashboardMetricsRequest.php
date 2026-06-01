<?php

namespace App\Http\Requests\Views\Reports;

use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;
use App\DTO\Reports\Dashboard\PeriodDTO;


class DashboardMetricsRequest extends APIBaseRequest
{

    public function rules()
    {
        $allowedPeriodsArr = [
            'last_week',
            'last_year',
            'last_month',
            'last_7_days',
            'current_week',
            'last_30_days',
            'current_year',
            'current_month',
        ];
        return [
            'user_id' => ['sometimes', 'nullable', 'integer'],
            'period' => ['required', 'string', Rule::in($allowedPeriodsArr)],
        ];
    }


    public function getPeriodDTO(): PeriodDTO
    {
        $val = parent::validated();
        $client = request()->client;
        $periodDTO = PeriodDTO::buildFromName($client, $val['period']);
        return $periodDTO;
    }

}
