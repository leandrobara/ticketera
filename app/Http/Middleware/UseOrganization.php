<?php

namespace App\Http\Middleware;

use App\Models;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UseOrganization
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(OrganizationContext::class);
        $membership = $request->user()->memberships()->with('organization')
            ->where('organization_id', $request->header('X-Organization-Id'))
            ->where('is_active', true)->whereHas('organization')->first();
        abort_unless($membership, 403, 'Seleccioná una organización de la que seas miembro.');
        $context->membership = $membership;
        try {
            $section = $request->segment(3);
            if (! $membership->managesOrganization()) {
                $collaborator = in_array('collaborator', $membership->roles ?? [], true);
                $door = in_array('door', $membership->roles ?? [], true);
                $allowed = $collaborator && ! in_array($section, ['team', 'invitations', 'users', 'newsletter-subscribers'], true);
                $allowed = $allowed || ($door && (($request->isMethod('GET') && in_array($section, ['shows', 'seasons', 'presentations', 'tickets'], true)) || ($request->isMethod('POST') && $request->is('api/admin/tickets/*/mark-used'))));
                abort_unless($allowed, 403, 'Tu rol no permite esta acción.');
                if ($membership->show_ids !== null && $request->isMethod('POST') && (($section === 'shows') || ($section === 'event-wizard' && ! $request->input('show_id') && ! $request->input('season_id') && ! $request->is('api/admin/event-wizard/preview-dates')))) {
                    abort(403, 'Tu acceso está limitado a los eventos asignados.');
                }
            }
            // Recheck any models that were bound before this middleware established the scope.
            foreach ($request->route()->parameters() as $model) {
                if ($model instanceof Model && method_exists($model, 'organizationPath')) {
                    abort_unless($model->newQuery()->whereKey($model->getKey())->exists(), 404);
                }
            }
            $map = ['show_id' => Models\Show::class, 'season_id' => Models\Season::class, 'venue_id' => Models\Venue::class,
                'person_id' => Models\Person::class, 'presentation_id' => Models\Presentation::class,
                'season_ticket_type_id' => Models\SeasonTicketType::class, 'buyer_id' => Models\Buyer::class,
                'order_id' => Models\Order::class, 'order_item_id' => Models\OrderItem::class];
            $check = function (array $values) use (&$check, $map) {
                foreach ($values as $key => $value) {
                    if (is_array($value)) {
                        $check($value);

                        continue;
                    }
                    if (isset($map[$key]) && filled($value) && ! $map[$key]::query()->whereKey($value)->exists()) {
                        throw ValidationException::withMessages([$key => 'El registro no pertenece a la organización o no está disponible.']);
                    }
                }
            };
            $check($request->all());

            return $next($request);
        } finally {
            $context->membership = null;
        }
    }
}
