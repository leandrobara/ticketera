<?php

namespace App\Services\Api\Admin;

use App\Helpers\RedisHelper;
use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Repositories\ShowCreditRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PersonService
{
    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly ShowCreditRepository $showCreditRepository,
        private readonly RedisHelper $redisHelper,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->personRepository->listPaginated($filters, $filters['per_page'] ?? 20);
    }

    public function getOne(Person $person): Person
    {
        return $this->personRepository->getOne($person);
    }

    public function findCandidates(array $data): Collection
    {
        $filters = $this->candidateFilters($data);

        if (! $filters) {
            return new Collection;
        }

        return $this->personRepository->findCandidates($filters);
    }

    public function create(array $data): Person
    {
        $allowDuplicateName = (bool) ($data['allow_duplicate_name'] ?? false);
        unset($data['allow_duplicate_name']);

        $data = $this->normalizeData($data);
        $this->validateStrongIdentifiers($data);
        $this->validatePossibleNameDuplicate($data, null, $allowDuplicateName);

        return $this->personRepository->store($data);
    }

    public function update(Person $person, array $data): Person
    {
        $allowDuplicateName = (bool) ($data['allow_duplicate_name'] ?? false);
        unset($data['allow_duplicate_name']);

        $data = $this->normalizeData($data, $person);
        $this->validateStrongIdentifiers($data, $person->id);
        $this->validatePossibleNameDuplicate($data, $person->id, $allowDuplicateName);

        $person = $this->personRepository->update($person, $data);

        foreach ($this->showCreditRepository->getShowIdsByPersonId($person->id) as $showId) {
            $this->redisHelper->deleteByPartialKey('site:show:'.$showId.':getPublicShow');
        }

        return $person;
    }

    public function delete(Person $person): void
    {
        $showIds = $this->showCreditRepository->getShowIdsByPersonId($person->id);
        $this->personRepository->delete($person);

        foreach ($showIds as $showId) {
            $this->redisHelper->deleteByPartialKey('site:show:'.$showId.':getPublicShow');
        }
    }

    private function normalizeData(array $data, ?Person $person = null): array
    {
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        if (array_key_exists('document_type', $data) && $data['document_type'] !== null) {
            $data['document_type'] = mb_strtoupper(trim($data['document_type']));
        }

        if (array_key_exists('document_number', $data) && $data['document_number'] !== null) {
            $data['document_number'] = preg_replace('/\s+/', '', trim($data['document_number']));
        }

        $displayName = $data['display_name'] ?? $person?->display_name;

        if ($displayName !== null) {
            $data['display_name'] = trim($displayName);
            if ($data['display_name'] === '') {
                throw ValidationException::withMessages([
                    'display_name' => ['The display name field is required.'],
                ]);
            }
            $data['normalized_name'] = $this->normalizeName($displayName);
        }

        if ($person && (array_key_exists('document_type', $data) || array_key_exists('document_number', $data))) {
            $documentType = array_key_exists('document_type', $data) ? $data['document_type'] : $person->document_type;
            $documentNumber = array_key_exists('document_number', $data) ? $data['document_number'] : $person->document_number;

            if ($documentType === null || $documentNumber === null) {
                $data['document_type'] = null;
                $data['document_number'] = null;
            } else {
                $data['document_type'] = mb_strtoupper(trim($documentType));
                $data['document_number'] = preg_replace('/\s+/', '', trim($documentNumber));
            }
        }

        return $data;
    }

    private function candidateFilters(array $data): array
    {
        $filters = [];

        if ($data['email'] ?? null) {
            $filters['email'] = mb_strtolower(trim($data['email']));
        }

        if (($data['document_type'] ?? null) && ($data['document_number'] ?? null)) {
            $filters['document_type'] = mb_strtoupper(trim($data['document_type']));
            $filters['document_number'] = preg_replace('/\s+/', '', trim($data['document_number']));
        }

        if ($data['display_name'] ?? null) {
            $filters['normalized_name'] = $this->normalizeName($data['display_name']);
        }

        return $filters;
    }

    private function validateStrongIdentifiers(array $data, ?int $ignorePersonId = null): void
    {
        if (($data['email'] ?? null) && $this->personRepository->findByEmail($data['email'], $ignorePersonId)) {
            throw ValidationException::withMessages([
                'email' => ['person_email_already_exists'],
            ]);
        }

        if (
            ($data['document_type'] ?? null)
            && ($data['document_number'] ?? null)
            && $this->personRepository->findByDocument($data['document_type'], $data['document_number'], $ignorePersonId)
        ) {
            throw ValidationException::withMessages([
                'document_number' => ['person_document_already_exists'],
            ]);
        }
    }

    private function validatePossibleNameDuplicate(array $data, ?int $ignorePersonId, bool $allowDuplicateName): void
    {
        if ($allowDuplicateName || ! ($data['normalized_name'] ?? null)) {
            return;
        }

        $candidates = $this->personRepository->findCandidates([
            'normalized_name' => $data['normalized_name'],
        ], $ignorePersonId);

        if ($candidates->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'display_name' => [
                'possible_duplicate_person',
                'candidate_ids:'.$candidates->pluck('id')->implode(','),
            ],
        ]);
    }

    private function normalizeName(string $name): string
    {
        $name = Str::ascii($name);
        $name = mb_strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/i', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }
}
