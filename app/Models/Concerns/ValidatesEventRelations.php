<?php

namespace App\Models\Concerns;

use App\Models\Buyer;
use App\Models\Comment;
use App\Models\CommentToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Person;
use App\Models\Presentation;
use App\Models\Season;
use App\Models\SeasonTicketType;
use App\Models\Show;
use App\Models\ShowCredit;
use App\Models\Ticket;
use App\Models\Venue;
use Illuminate\Validation\ValidationException;

trait ValidatesEventRelations
{
    protected static function bootValidatesEventRelations(): void
    {
        static::saving(function ($model) {
            $fail = fn ($key) => throw ValidationException::withMessages([$key => 'Las relaciones deben pertenecer al mismo evento y organización.']);
            if ($model instanceof Season) {
                $show = Show::find($model->show_id);
                $venue = Venue::find($model->venue_id);
                if (! $show || ! $venue || (int) $show->organization_id !== (int) $venue->organization_id) {
                    $fail('venue_id');
                }
                if ($model->exists && ($model->isDirty('show_id') || $model->isDirty('venue_id'))) {
                    $fail('season_id');
                }
            }
            if ($model instanceof Presentation) {
                if (! Season::find($model->season_id)) {
                    $fail('season_id');
                }
                if (! $model->starts_at || ! $model->ends_at || ! $model->ends_at->gt($model->starts_at)) {
                    throw ValidationException::withMessages(['ends_at' => 'La finalización debe ser posterior al inicio.']);
                }
            }
            if ($model instanceof SeasonTicketType && $model->exists && $model->isDirty('season_id')) {
                $fail('season_id');
            }
            if ($model instanceof Order) {
                $presentation = Presentation::find($model->presentation_id);
                $show = Show::find($model->show_id);
                $buyer = Buyer::withTrashed()->find($model->buyer_id);
                if (! $presentation || ! $show || ! $buyer || (int) $presentation->season->show_id !== (int) $show->id || (int) $show->organization_id !== (int) $buyer->organization_id) {
                    $fail('presentation_id');
                }
            }
            if ($model instanceof OrderItem) {
                $order = Order::withTrashed()->find($model->order_id);
                $type = $model->season_ticket_type_id ? SeasonTicketType::withTrashed()->find($model->season_ticket_type_id) : null;
                if (! $order || ($model->show_id && (int) $model->show_id !== (int) $order->show_id) || ($model->season_ticket_type_id && (! $type || (int) $type->season_id !== (int) $order->presentation?->season_id))) {
                    $fail('season_ticket_type_id');
                }
            }
            if ($model instanceof Ticket) {
                $order = Order::withTrashed()->find($model->order_id);
                $item = OrderItem::withTrashed()->find($model->order_item_id);
                if (! $order || ! $item || (int) $item->order_id !== (int) $order->id || (int) $model->presentation_id !== (int) $order->presentation_id || (int) $model->show_id !== (int) $order->show_id || (int) $model->season_ticket_type_id !== (int) $item->season_ticket_type_id) {
                    $fail('order_item_id');
                }
            }
            if ($model instanceof ShowCredit && $model->person_id) {
                $person = Person::find($model->person_id);
                $show = Show::find($model->show_id);
                if (! $person || ! $show || (int) $person->organization_id !== (int) $show->organization_id) {
                    $fail('person_id');
                }
            }
            if ($model instanceof Comment || $model instanceof CommentToken) {
                $show = Show::find($model->show_id);
                $buyer = Buyer::withTrashed()->find($model->buyer_id);
                if (! $show || ! $buyer || (int) $show->organization_id !== (int) $buyer->organization_id) {
                    $fail('buyer_id');
                }
                if ($model instanceof Comment && Order::withTrashed()->find($model->order_id)?->show_id !== $show->id) {
                    $fail('order_id');
                }
            }
        });
    }
}
