<?php

namespace App\Services\Api\Admin;

use App\Models\Presentation;
use App\Repositories\PresentationRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PresentationService
{
    public function __construct(
        private readonly PresentationRepository $presentationRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->presentationRepository->listPaginated($filters);
    }

    public function getOne(Presentation $presentation): Presentation
    {
        return $this->presentationRepository->getOne($presentation);
    }

    public function create(array $data): Presentation
    {
        return $this->presentationRepository->store($data);
    }

    public function update(Presentation $presentation, array $data): Presentation
    {
        return DB::transaction(function () use ($presentation, $data) {
            $presentation = Presentation::lockForUpdate()->findOrFail($presentation->id);
            $protected = ! $presentation->starts_at->isFuture() || $presentation->orders()->withTrashed()->exists() || $presentation->tickets()->withTrashed()->exists();
            foreach (['season_id', 'starts_at', 'ends_at'] as $field) {
                if ($protected && array_key_exists($field, $data)) {
                    $different = $field === 'season_id' ? (int) $data[$field] !== (int) $presentation->$field : ! Carbon::parse($data[$field])->eq($presentation->$field);
                    if ($different) {
                        throw ValidationException::withMessages([$field => 'Una función pasada o con órdenes conserva sus fechas y temporada.']);
                    }
                }
            }
            if (isset($data['capacity']) && $data['capacity'] < $presentation->tickets()->whereIn('status', ['VALID', 'USED'])->count()) {
                throw ValidationException::withMessages(['capacity' => 'La capacidad no puede ser menor a las entradas emitidas.']);
            }

            return $this->presentationRepository->update($presentation, $data);
        });
    }

    public function delete(Presentation $presentation): void
    {
        if ($presentation->orders()->withTrashed()->exists() || $presentation->tickets()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['presentation' => 'Esta función tiene órdenes. Podés cancelarla, pero no eliminarla.']);
        }
        $this->presentationRepository->delete($presentation);
    }
}
