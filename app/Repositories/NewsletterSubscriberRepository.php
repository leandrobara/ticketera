<?php

namespace App\Repositories;

use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NewsletterSubscriberRepository
{
    public function findByEmail(string $email): ?NewsletterSubscriber
    {
        return NewsletterSubscriber::query()
            ->where('email', $email)
            ->first();
    }

    public function listPaginated(?string $search, int $limit = 20): LengthAwarePaginator
    {
        return NewsletterSubscriber::query()
            ->with('show:id,title,slug')
            ->when($search, function ($query) use ($search) {
                $normalizedSearch = mb_strtolower(trim($search));

                $query->where(function ($query) use ($normalizedSearch) {
                    $query
                        ->where('name', 'like', "%{$normalizedSearch}%")
                        ->orWhere('email', 'like', "%{$normalizedSearch}%")
                        ->orWhereHas('show', function ($query) use ($normalizedSearch) {
                            $query->where('title', 'like', "%{$normalizedSearch}%");
                        });
                });
            })
            ->latest()
            ->paginate($limit);
    }

    public function store(array $attrs): NewsletterSubscriber
    {
        return NewsletterSubscriber::create($attrs);
    }

    public function update(NewsletterSubscriber $subscriber, array $attrs): NewsletterSubscriber
    {
        $subscriber->update($attrs);
        return $subscriber->fresh();
    }

    public function restore(NewsletterSubscriber $subscriber): NewsletterSubscriber
    {
        $subscriber->restore();
        return $subscriber->fresh();
    }

    public function delete(NewsletterSubscriber $subscriber): void
    {
        $subscriber->delete();
    }
}
