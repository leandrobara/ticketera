<?php

namespace App\Services\Api\Admin;

use App\Models\Season;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SeasonScheduleService
{
    public function generate(array $configuration): array
    {
        $data = Validator::make($configuration, [
            'groups' => 'sometimes|array|max:50',
            'groups.*.frequency' => 'required|in:daily,weekly',
            'groups.*.weekdays' => 'required_if:groups.*.frequency,weekly|array|max:7',
            'groups.*.weekdays.*' => 'integer|between:0,6|distinct',
            'groups.*.starts_on' => 'required|date_format:Y-m-d',
            'groups.*.ends_on' => 'required|date_format:Y-m-d',
            'groups.*.start_time' => 'required|date_format:H:i',
            'groups.*.end_time' => 'required|date_format:H:i',
            'individual_dates' => 'sometimes|array|max:1000',
            'individual_dates.*.date' => 'required|date_format:Y-m-d',
            'individual_dates.*.start_time' => 'required|date_format:H:i',
            'individual_dates.*.end_time' => 'required|date_format:H:i',
            'individual_dates.*.ends_on' => 'nullable|date_format:Y-m-d',
            'excluded_starts_at' => 'sometimes|array|max:1000',
            'excluded_starts_at.*' => 'required|date_format:Y-m-d H:i:s|distinct',
        ])->validate();
        $dates = [];
        foreach ($data['groups'] ?? [] as $index => $group) {
            $first = CarbonImmutable::parse($group['starts_on'], config('app.timezone'));
            $last = CarbonImmutable::parse($group['ends_on'], config('app.timezone'));
            if ($last->lt($first) || $first->diffInDays($last) > 730 || ($group['frequency'] === 'weekly' && empty($group['weekdays']))) {
                throw ValidationException::withMessages(["configuration.groups.$index" => 'Elegí un rango válido (hasta dos años) y al menos un día para repetir semanalmente.']);
            }
            for ($day = $first; $day->lte($last); $day = $day->addDay()) {
                if ($group['frequency'] === 'weekly' && ! in_array($day->dayOfWeek, $group['weekdays'])) {
                    continue;
                }
                $this->addDate($dates, $day->format('Y-m-d'), $group['start_time'], $group['end_time']);
            }
        }
        foreach ($data['individual_dates'] ?? [] as $date) {
            $this->addDate($dates, $date['date'], $date['start_time'], $date['end_time'], $date['ends_on'] ?? null);
        }
        foreach ($data['excluded_starts_at'] ?? [] as $excluded) {
            unset($dates[$excluded]);
        }
        if (count($dates) > 1000) {
            throw ValidationException::withMessages(['configuration' => 'Se pueden generar hasta 1000 funciones por temporada.']);
        }
        ksort($dates);

        return array_values($dates);
    }

    private function addDate(array &$dates, string $date, string $startTime, string $endTime, ?string $endsOn = null): void
    {
        $start = CarbonImmutable::parse($date.' '.$startTime, config('app.timezone'));
        $end = CarbonImmutable::parse(($endsOn ?: $date).' '.$endTime, config('app.timezone'));
        if (! $endsOn && $end->lt($start)) {
            $end = $end->addDay();
        }
        if (! $end->gt($start)) {
            throw ValidationException::withMessages(['configuration' => 'La finalización debe ser posterior al inicio. Para más de un día, indicá la fecha de finalización.']);
        }
        $key = $start->format('Y-m-d H:i:s');
        $row = ['starts_at' => $key, 'ends_at' => $end->format('Y-m-d H:i:s')];
        if (isset($dates[$key]) && $dates[$key] !== $row) {
            throw ValidationException::withMessages(['configuration' => 'Hay dos funciones con el mismo inicio y distinta finalización.']);
        }
        $dates[$key] = $row;
    }

    public function sync(Season $season, array $configuration, int $capacity, bool $publish = false): array
    {
        return DB::transaction(function () use ($season, $configuration, $capacity, $publish) {
            $season = Season::lockForUpdate()->findOrFail($season->id);
            $dates = collect($this->generate($configuration))->keyBy('starts_at');
            $existing = $season->presentations()->withTrashed()->withCount(['orders' => fn ($q) => $q->withTrashed(), 'tickets' => fn ($q) => $q->withTrashed()])->lockForUpdate()->get();
            $preserved = [];
            foreach ($existing as $presentation) {
                $key = $presentation->starts_at->format('Y-m-d H:i:s');
                $protected = ! $presentation->starts_at->isFuture() || $presentation->orders_count > 0 || $presentation->tickets_count > 0;
                if ($protected) {
                    if (! $presentation->trashed()) {
                        $preserved[] = $presentation->id;
                    }
                    $dates->forget($key);

                    continue;
                }
                $desired = $dates->get($key);
                if (! $desired) {
                    if (! $presentation->trashed()) {
                        $presentation->delete();
                    }

continue;
                }
                if ($presentation->trashed()) {
                    $presentation->restore();
                }
                $attrs = $desired + ['capacity' => $capacity];
                if ($publish && $presentation->status === 'draft') {
                    $attrs['status'] = 'published';
                }
                $presentation->update($attrs);
                $dates->forget($key);
            }
            foreach ($dates as $date) {
                $season->presentations()->create($date + ['capacity' => $capacity, 'status' => $publish || $season->status === 'published' ? 'published' : 'draft']);
            }
            $season->schedule()->updateOrCreate([], ['configuration' => $configuration]);

            return $preserved;
        });
    }
}
