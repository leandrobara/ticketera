<?php

namespace App\Services\Api\Admin;

use App\Models\NewsletterSubscriber;
use App\Repositories\NewsletterSubscriberRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NewsletterSubscriberService
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $newsletterSubscriberRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->newsletterSubscriberRepository->listPaginated($filters['search'] ?? null);
    }

    public function delete(NewsletterSubscriber $newsletterSubscriber): void
    {
        $this->newsletterSubscriberRepository->delete($newsletterSubscriber);
    }
}
