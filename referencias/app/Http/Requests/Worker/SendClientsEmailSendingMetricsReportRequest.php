<?php

namespace App\Http\Requests\Worker;

use App\Models\News;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class SendClientsEmailSendingMetricsReportRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'client_id' => ['sometimes', 'nullable', 'integer'],
            'send_email' => ['sometimes', 'nullable', 'boolean'],
            'dump_metrics' => ['sometimes', 'nullable', 'boolean'],
            'period' => ['sometimes', 'nullable', 'string', 'in:last_week,last_day,last_month'],
        ];
    }

}
