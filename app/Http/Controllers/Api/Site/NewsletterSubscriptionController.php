<?php

namespace App\Http\Controllers\Api\Site;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Site\CreateNewsletterSubscriptionRequest;
use App\Services\Api\Site\NewsletterSubscriptionService;

class NewsletterSubscriptionController extends BaseAPIController
{
    public function create(CreateNewsletterSubscriptionRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(NewsletterSubscriptionService::class)->create($request->validated())
        );
    }
}
