<?php

namespace App\Services\Api\Site;

use App\Models\NewsletterSubscriber;
use App\Repositories\NewsletterSubscriberRepository;

class NewsletterSubscriptionService
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $newsletterSubscriberRepository,
    ) {
        //
    }

    public function create(array $data): NewsletterSubscriber
    {
        $data = $this->normalize($data);

        $subscriber = $this->newsletterSubscriberRepository->findByEmail($data['email']);

        if ($subscriber) {
            return $subscriber;
        }

        return $this->newsletterSubscriberRepository->store($data);
    }

    private function normalize(array $data): array
    {
        $data['email'] = mb_strtolower(trim($data['email']));
        $data['name'] = filled($data['name'] ?? null) ? trim($data['name']) : null;
        $data['show_id'] = filled($data['show_id'] ?? null) ? (int) $data['show_id'] : null;

        return $data;
    }
}
