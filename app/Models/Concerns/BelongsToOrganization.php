<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Show;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait BelongsToOrganization
{
    use ValidatesEventRelations;

    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            $context = app(OrganizationContext::class);
            $model = $query->getModel();
            $path = $model->organizationPath();
            // Deleted organizations are unavailable both in the panel and in public sales.
            $query->whereHas($path === null ? 'organization' : $path.'.organization');
            if (! $context->id()) {
                return;
            }
            if ($path === null) {
                $query->where($model->qualifyColumn('organization_id'), $context->id());
                if ($model instanceof Show && $context->showIds() !== null) {
                    $query->whereIn($model->qualifyColumn('id'), $context->showIds());
                }
            } else {
                $query->whereHas($path, fn (Builder $parent) => $parent->where('organization_id', $context->id()));
            }
        });
        static::creating(function ($model) {
            if ($model->organizationPath() === null && app(OrganizationContext::class)->id()) {
                $model->organization_id = app(OrganizationContext::class)->id();
            }
        });
        static::updating(function ($model) {
            if ($model->organizationPath() === null && $model->isDirty('organization_id')) {
                throw ValidationException::withMessages(['organization_id' => 'No se puede trasladar este registro a otra organización.']);
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationPath(): ?string
    {
        return match (class_basename($this)) {
            'Show', 'Venue', 'Person', 'Buyer' => null,
            'Season', 'ShowCredit', 'ShowImage', 'ShowLink', 'ShowPerformanceHistory', 'Comment', 'CommentToken', 'Order', 'NewsletterSubscriber' => 'show',
            'Presentation', 'SeasonTicketType' => 'season.show',
            'Ticket', 'Payment', 'OrderItem' => 'order.show',
            'OrderItemPromotion' => 'orderItem.order.show',
        };
    }
}
