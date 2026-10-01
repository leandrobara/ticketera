<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateShowRequest;
use App\Models\Person;
use App\Models\Season;
use App\Models\Show;
use App\Models\Venue;
use App\Services\Api\Admin\SeasonScheduleService;
use App\Services\Api\Admin\SeasonService;
use App\Services\Api\Admin\SeasonTicketTypeService;
use App\Services\Api\Admin\ShowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EventWizardController extends BaseAPIController
{
    public function get(Season $season): array
    {
        return $this->getSuccessResponse($this->snapshot($season));
    }

    public function preview(Request $request, SeasonScheduleService $schedules): array
    {
        $data = $request->validate(['configuration' => 'present|array']);

        return $this->getSuccessResponse($schedules->generate($data['configuration']));
    }

    public function save(Request $request, ShowService $shows, SeasonService $seasons, SeasonTicketTypeService $types, SeasonScheduleService $schedules): array
    {
        $data = $request->validate([
            'show_id' => 'nullable|integer', 'season_id' => 'nullable|integer',
            'show' => 'required|array', 'venue_id' => 'nullable|integer', 'venue' => 'nullable|array',
            'capacity' => 'required|integer|min:0', 'season_name' => 'nullable|string|max:160',
            'configuration' => 'present|array', 'ticket_types' => 'required|array|min:1|max:100',
            'ticket_types.*.id' => 'nullable|integer',
            'ticket_types.*.name' => 'required|string|max:160', 'ticket_types.*.price' => 'required|numeric',
            'ticket_types.*.stock' => 'nullable|integer', 'ticket_types.*.is_active' => 'sometimes|boolean', 'ticket_types.*.sort_order' => 'sometimes|integer',
            'ticket_types.*.promotion_name' => 'nullable|string', 'ticket_types.*.promotion_type' => 'nullable|string', 'ticket_types.*.promotion_value' => 'nullable|numeric',
            'ticket_types.*.promotion_bundle_quantity' => 'nullable|integer', 'ticket_types.*.promotion_pay_quantity' => 'nullable|integer',
            'ticket_types.*.promotion_access_code' => 'nullable|string', 'ticket_types.*.promotion_is_active' => 'sometimes|boolean',
            'credits' => 'present|array|max:200', 'credits.*.id' => 'nullable|integer',
            'credits.*.person_id' => 'required|integer', 'credits.*.section' => 'required|in:cast,technical',
            'credits.*.role_label' => 'required|string|max:160', 'credits.*.character_name' => 'nullable|string|max:160',
            'credits.*.sort_order' => 'required|integer|min:1',
            'links' => 'present|array|max:100', 'links.*.id' => 'nullable|integer',
            'links.*.text' => 'required|string|max:255', 'links.*.url' => 'required|url|max:2048', 'links.*.sort_order' => 'required|integer|min:1',
            'action' => 'required|in:save,draft,publish',
        ], ['ticket_types.required' => 'Creá al menos una entrada para guardar el evento.',
            'ticket_types.min' => 'Creá al menos una entrada para guardar el evento.']);
        $showData = Validator::make($data['show'], (new CreateShowRequest)->rules())->validate();
        $schedules->generate($data['configuration']);
        $result = DB::transaction(function () use ($data, $showData, $shows, $seasons, $types, $schedules) {
            $season = ! empty($data['season_id']) ? Season::lockForUpdate()->findOrFail($data['season_id']) : null;
            if ($season?->closed_at) {
                throw ValidationException::withMessages(['season_id' => 'Esta temporada está cerrada. Creá una nueva para repetir el show.']);
            }
            $showId = $data['show_id'] ?? $season?->show_id;
            if ($season && (int) $showId !== (int) $season->show_id) {
                throw ValidationException::withMessages(['show_id' => 'La temporada pertenece a otro show.']);
            }
            $show = $showId ? $shows->update(Show::findOrFail($showId), $showData) : $shows->create($showData);
            if (! empty($data['venue_id'])) {
                $venue = Venue::findOrFail($data['venue_id']);
            } else {
                $venueData = Validator::make($data['venue'] ?? [], ['name' => 'required|string|max:160', 'address' => 'nullable|string|max:255', 'capacity' => 'nullable|integer|min:0'])->validate();
                $venue = Venue::create($venueData);
            }
            if ($season && (int) $season->venue_id !== (int) $venue->id) {
                throw ValidationException::withMessages(['venue_id' => 'Para cambiar de lugar, creá una nueva temporada del mismo show.']);
            }
            if (! $season) {
                $season = $seasons->create(['show_id' => $show->id, 'venue_id' => $venue->id, 'name' => $data['season_name'] ?? null, 'status' => 'draft']);
            } else {
                $season->update(['name' => $data['season_name'] ?? $season->name]);
            }
            $this->syncChildren($show, 'credits', $data['credits']);
            $this->syncChildren($show, 'links', $data['links']);
            $kept = [];
            foreach ($data['ticket_types'] as $typeData) {
                $id = $typeData['id'] ?? null;
                unset($typeData['id']);
                $typeData['season_id'] = $season->id;
                $type = $id ? $types->update($season->ticketTypes()->findOrFail($id), $typeData) : $types->create($typeData);
                $kept[] = $type->id;
            }
            $season->ticketTypes()->whereNotIn('id', $kept)->get()->each(fn ($type) => $types->delete($type));
            $preserved = $schedules->sync($season, $data['configuration'], $data['capacity'], $data['action'] === 'publish');
            if ($data['action'] === 'publish') {
                if (! $season->presentations()->where('starts_at', '>', now())->where('status', 'published')->exists() || ! $season->ticketTypes()->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['action' => 'Para publicar necesitás al menos una función futura y un tipo de entrada activo.']);
                }
                $season = $seasons->update($season, ['status' => 'published']);
            } elseif ($data['action'] === 'draft' && $season->status !== 'draft') {
                throw ValidationException::withMessages(['action' => 'Esta temporada ya está publicada. Usá Guardar cambios para editarla.']);
            }

            return $this->snapshot($season->fresh()) + ['preserved_presentation_ids' => $preserved];
        });

        return $this->getSuccessResponse($result);
    }

    private function syncChildren(Show $show, string $relation, array $rows): void
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            unset($row['id']);
            if ($relation === 'credits') {
                Person::findOrFail($row['person_id']);
            }
            $child = $id ? $show->$relation()->findOrFail($id) : $show->$relation()->make();
            $child->fill($row)->save();
            $ids[] = $child->id;
        }
        $show->$relation()->whereNotIn('id', $ids)->get()->each->delete();
    }

    private function snapshot(Season $season): array
    {
        $season->load(['show.mainImage', 'show.credits.person', 'show.links', 'venue', 'ticketTypes', 'schedule', 'presentations' => fn ($query) => $query->withCount(['orders' => fn ($q) => $q->withTrashed(), 'tickets' => fn ($q) => $q->withTrashed()])->orderBy('starts_at')]);
        foreach ($season->presentations as $presentation) {
            $presentation->setAttribute('is_protected', ! $presentation->starts_at->isFuture() || $presentation->orders_count > 0 || $presentation->tickets_count > 0);
            $presentation->setAttribute('local_starts_at', $presentation->starts_at->format('Y-m-d H:i:s'));
            $presentation->setAttribute('local_ends_at', $presentation->ends_at->format('Y-m-d H:i:s'));
        }
        $configuration = $season->schedule?->configuration;
        if (! $configuration) {
            $configuration = ['groups' => [], 'individual_dates' => $season->presentations->map(fn ($p) => ['date' => $p->starts_at->format('Y-m-d'), 'start_time' => $p->starts_at->format('H:i'), 'end_time' => $p->ends_at->format('H:i'), 'ends_on' => $p->ends_at->format('Y-m-d')])->all()];
        }

        return ['season_id' => $season->id, 'show_id' => $season->show_id, 'show' => $season->show, 'venue_id' => $season->venue_id,
            'venue' => $season->venue, 'season_name' => $season->name, 'status' => $season->status,
            'capacity' => $season->presentations->first()?->capacity ?? $season->venue->capacity ?? 0,
            'configuration' => $configuration, 'presentations' => $season->presentations,
            'ticket_types' => $season->ticketTypes, 'credits' => $season->show->credits, 'links' => $season->show->links];
    }
}
