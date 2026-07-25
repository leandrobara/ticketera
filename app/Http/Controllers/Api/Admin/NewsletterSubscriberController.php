<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\DeleteNewsletterSubscriberRequest;
use App\Http\Requests\Admin\ListNewsletterSubscriberRequest;
use App\Models\NewsletterSubscriber;
use App\Services\Api\Admin\NewsletterSubscriberService;

class NewsletterSubscriberController extends BaseAPIController
{
    public function list(ListNewsletterSubscriberRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(NewsletterSubscriberService::class)->list($request->validated())
        );
    }

    public function delete(NewsletterSubscriber $newsletterSubscriber, DeleteNewsletterSubscriberRequest $request): array
    {
        resolve(NewsletterSubscriberService::class)->delete($newsletterSubscriber);
        return $this->getSuccessResponse($newsletterSubscriber);
    }
}
