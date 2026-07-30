<?php

namespace App\Services\Api\Site;

use App\Models\Show;
use App\Repositories\Site\SiteShowRepositoryInterface;

class ShowService
{
    public function __construct(
        private readonly SiteShowRepositoryInterface $showRepository,
    ) {
        //
    }

    public function getShowProfileData(Show $show): array
    {
        return $this->getPublicShow($this->showRepository->getPublicShow($show));
    }

    private function getPublicShow(Show $show): array
    {
        return [
            'id' => $show->id,
            'title' => $show->title,
            'subtitle' => $show->subtitle,
            'slug' => $show->slug,
            'genre' => $show->genre,
            'format' => $show->format,
            'synopsis' => $show->synopsis,
            'additional_information' => $show->additional_information,
            'age_rating' => $show->age_rating,
            'production_note' => $show->production_note,
            'social_links' => [
                'instagram' => $show->instagram_url,
                'facebook' => $show->facebook_url,
                'x' => $show->x_url,
                'tiktok' => $show->tiktok_url,
                'youtube' => $show->youtube_url,
                'pinterest' => $show->pinterest_url,
                'website' => $show->website_url,
            ],
            'faqs' => $this->showFaqs($show->faqs),
            'duration_minutes' => $show->duration_minutes,
            'service_fee_type' => $show->service_fee_type,
            'service_fee_fixed_amount' => $show->service_fee_fixed_amount,
            'service_fee_percentage' => $show->service_fee_percentage,
            'service_fee_minimum_unit_amount' => $show->service_fee_minimum_unit_amount,
            'main_image_url' => $this->imageUrl($show->mainImage),
            'venue' => null,
            'credits' => $show->credits
                ->sortBy([
                    ['section', 'asc'],
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->map(fn ($credit) => [
                    'id' => $credit->id,
                    'section' => $credit->section,
                    'name' => $credit->display_name_override
                        ?: $credit->person?->display_name
                        ?: trim(($credit->person?->first_name ?? '').' '.($credit->person?->last_name ?? '')),
                    'role' => $credit->role_label,
                    'character_name' => $credit->character_name,
                    'photo_url' => $credit->photo_url ?: $this->pathUrl($credit->person?->photo_path),
                ])
                ->values(),
            'performance_history' => $show->performanceHistories
                ->map(fn ($history) => [
                    'id' => $history->id,
                    'year' => $history->year,
                    'venue_name' => $history->venue_name,
                    'sort_order' => $history->sort_order,
                ])
                ->values(),
            'links' => $show->links
                ->map(fn ($link) => [
                    'id' => $link->id,
                    'text' => $link->text,
                    'url' => $link->url,
                    'sort_order' => $link->sort_order,
                ])
                ->values(),
        ];
    }

    private function showFaqs(?array $faqs): array
    {
        return collect($faqs ?? [])
            ->filter(fn ($faq) => is_array($faq) && filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))
            ->sortBy(fn ($faq) => $faq['sort_order'] ?? 1)
            ->values()
            ->map(fn ($faq) => [
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'sort_order' => $faq['sort_order'] ?? 1,
            ])
            ->all();
    }

    private function imageUrl($image): ?string
    {
        if (! $image || ! $image->path) {
            return null;
        }

        return $image->url ?: (str_starts_with($image->path, '/') ? $image->path : '/'.$image->path);
    }

    private function pathUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $cloudfrontDomain = config('filesystems.cloudfront_domain');

        if ($cloudfrontDomain) {
            return 'https://'.rtrim($cloudfrontDomain, '/').'/'.ltrim($path, '/');
        }

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }
}
