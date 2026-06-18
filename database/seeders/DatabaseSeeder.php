<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Presentation;
use App\Models\PresentationTicketType;
use App\Models\Promotion;
use App\Models\Show;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate([
            'email' => 'admin@ticketera.test',
        ], [
            'name' => 'Ticketera Admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->seedBuyers();
        $this->seedVenues();
        $this->seedShows();
        $this->seedPresentations();
        $this->seedPresentationTicketTypes();
        $this->seedPromotions();
    }

    private function seedBuyers(): void
    {
        $buyers = [
            [
                'name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'juan.perez@example.com',
                'phone' => '5491133334444',
                'dni' => '31232345',
            ],
            [
                'name' => 'Lucia',
                'last_name' => 'Gomez',
                'email' => 'lucia.gomez@example.com',
                'phone' => '5491155556666',
                'dni' => '28444555',
            ],
            [
                'name' => 'Martin',
                'last_name' => 'Rodriguez',
                'email' => 'martin.rodriguez@example.com',
                'phone' => '5491177778888',
                'dni' => '35666777',
            ],
        ];

        foreach ($buyers as $buyerData) {
            $buyer = Buyer::withTrashed()
                ->where('email', $buyerData['email'])
                ->first();

            if ($buyer) {
                $buyer->restore();
                $buyer->update($buyerData);
                continue;
            }

            Buyer::create($buyerData);
        }
    }

    private function seedVenues(): void
    {
        $venues = [
            [
                'name' => 'Teatro El Umbral',
                'capacity' => 80,
                'description' => 'Sala independiente de formato intimista.',
                'address' => 'Av. Corrientes 1543',
                'neighborhood' => 'San Nicolas',
                'city' => 'Buenos Aires',
                'google_maps_url' => 'https://maps.google.com',
                'has_bar' => true,
                'is_accessible' => false,
                'has_parking' => false,
            ],
            [
                'name' => 'Espacio La Trama',
                'capacity' => 120,
                'description' => 'Espacio cultural para teatro y musica.',
                'address' => 'Mario Bravo 875',
                'neighborhood' => 'Almagro',
                'city' => 'Buenos Aires',
                'google_maps_url' => 'https://maps.google.com',
                'has_bar' => true,
                'is_accessible' => true,
                'has_parking' => false,
            ],
            [
                'name' => 'Sala Patio Sur',
                'capacity' => 60,
                'description' => 'Sala chica para obras autogestionadas.',
                'address' => 'Defensa 1020',
                'neighborhood' => 'San Telmo',
                'city' => 'Buenos Aires',
                'google_maps_url' => 'https://maps.google.com',
                'has_bar' => false,
                'is_accessible' => false,
                'has_parking' => false,
            ],
        ];

        foreach ($venues as $venueData) {
            Venue::updateOrCreate(
                ['name' => $venueData['name']],
                $venueData,
            );
        }
    }

    private function seedShows(): void
    {
        $shows = [
            [
                'title' => 'La noche antes',
                'slug' => 'la-noche-antes',
                'description' => 'Una obra sobre decisiones pequenas que cambian una vida.',
                'duration_minutes' => 75,
                'genre' => 'Drama',
                'format' => 'Theater',
                'age_rating' => '+13',
                'main_image_path' => null,
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'Manual para desaparecer',
                'slug' => 'manual-para-desaparecer',
                'description' => 'Comedia amarga sobre identidad, trabajo y deseo.',
                'duration_minutes' => 65,
                'genre' => 'Comedy',
                'format' => 'Theater',
                'age_rating' => '+16',
                'main_image_path' => null,
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'Ensayo abierto',
                'slug' => 'ensayo-abierto',
                'description' => 'Proceso escenico abierto al publico.',
                'duration_minutes' => 50,
                'genre' => 'Experimental',
                'format' => 'Performance',
                'age_rating' => 'ATP',
                'main_image_path' => null,
                'status' => 'draft',
                'published_at' => null,
            ],
        ];

        foreach ($shows as $showData) {
            Show::updateOrCreate(
                ['title' => $showData['title']],
                $showData,
            );
        }
    }

    private function seedPresentations(): void
    {
        $presentations = [
            [
                'show_title' => 'La noche antes',
                'venue_name' => 'Teatro El Umbral',
                'starts_at' => '2026-07-10 21:00:00',
                'capacity' => 80,
                'status' => 'published',
                'notes' => 'Funcion estreno.',
            ],
            [
                'show_title' => 'La noche antes',
                'venue_name' => 'Teatro El Umbral',
                'starts_at' => '2026-07-17 21:00:00',
                'capacity' => 80,
                'status' => 'published',
                'notes' => null,
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'venue_name' => 'Espacio La Trama',
                'starts_at' => '2026-07-11 20:30:00',
                'capacity' => 120,
                'status' => 'published',
                'notes' => 'Primera funcion de la temporada.',
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'venue_name' => 'Espacio La Trama',
                'starts_at' => '2026-07-18 20:30:00',
                'capacity' => 120,
                'status' => 'published',
                'notes' => null,
            ],
            [
                'show_title' => 'Ensayo abierto',
                'venue_name' => 'Sala Patio Sur',
                'starts_at' => '2026-07-25 19:00:00',
                'capacity' => 60,
                'status' => 'draft',
                'notes' => 'Funcion de prueba no publicada.',
            ],
        ];

        foreach ($presentations as $presentationData) {
            $show = Show::where('title', $presentationData['show_title'])->firstOrFail();
            $venue = Venue::where('name', $presentationData['venue_name'])->firstOrFail();
            $startsAt = Carbon::parse($presentationData['starts_at']);

            $presentation = Presentation::withTrashed()
                ->where('show_id', $show->id)
                ->where('venue_id', $venue->id)
                ->where('starts_at', $startsAt)
                ->first();

            $attributes = [
                'show_id' => $show->id,
                'venue_id' => $venue->id,
                'starts_at' => $startsAt,
                'capacity' => $presentationData['capacity'],
                'status' => $presentationData['status'],
                'notes' => $presentationData['notes'],
            ];

            if ($presentation) {
                $presentation->restore();
                $presentation->update($attributes);
                continue;
            }

            Presentation::create($attributes);
        }
    }

    private function seedPresentationTicketTypes(): void
    {
        $ticketTypes = [
            [
                'show_title' => 'La noche antes',
                'starts_at' => '2026-07-10 21:00:00',
                'name' => 'General',
                'price' => '20000.000000',
                'stock' => 60,
                'sort_order' => 1,
            ],
            [
                'show_title' => 'La noche antes',
                'starts_at' => '2026-07-10 21:00:00',
                'name' => 'Estudiantes',
                'price' => '15000.000000',
                'stock' => 20,
                'sort_order' => 2,
            ],
            [
                'show_title' => 'La noche antes',
                'starts_at' => '2026-07-17 21:00:00',
                'name' => 'General',
                'price' => '20000.000000',
                'stock' => 80,
                'sort_order' => 1,
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'starts_at' => '2026-07-11 20:30:00',
                'name' => 'General',
                'price' => '18000.000000',
                'stock' => 100,
                'sort_order' => 1,
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'starts_at' => '2026-07-11 20:30:00',
                'name' => 'Promocional',
                'price' => '14000.000000',
                'stock' => 20,
                'sort_order' => 2,
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'starts_at' => '2026-07-18 20:30:00',
                'name' => 'General',
                'price' => '18000.000000',
                'stock' => 120,
                'sort_order' => 1,
            ],
            [
                'show_title' => 'Ensayo abierto',
                'starts_at' => '2026-07-25 19:00:00',
                'name' => 'Entrada libre',
                'price' => '0.000000',
                'stock' => 60,
                'sort_order' => 1,
            ],
        ];

        foreach ($ticketTypes as $ticketTypeData) {
            $show = Show::where('title', $ticketTypeData['show_title'])->firstOrFail();
            $presentation = Presentation::where('show_id', $show->id)
                ->where('starts_at', Carbon::parse($ticketTypeData['starts_at']))
                ->firstOrFail();

            $ticketType = PresentationTicketType::withTrashed()
                ->where('presentation_id', $presentation->id)
                ->where('name', $ticketTypeData['name'])
                ->first();

            $attributes = [
                'show_id' => $show->id,
                'presentation_id' => $presentation->id,
                'name' => $ticketTypeData['name'],
                'price' => $ticketTypeData['price'],
                'stock' => $ticketTypeData['stock'],
                'is_active' => true,
                'sort_order' => $ticketTypeData['sort_order'],
            ];

            if ($ticketType) {
                $ticketType->restore();
                $ticketType->update($attributes);
                continue;
            }

            PresentationTicketType::create($attributes);
        }
    }

    private function seedPromotions(): void
    {
        $promotions = [
            [
                'name' => '2x1 General',
                'type' => 'buy_x_get_y',
                'value' => null,
                'bundle_quantity' => 2,
                'pay_quantity' => 1,
                'access_code' => null,
                'ticket_type' => ['show_title' => 'La noche antes', 'starts_at' => '2026-07-10 21:00:00', 'name' => 'General'],
            ],
            [
                'name' => 'Descuento estudiantes',
                'type' => 'percent_discount',
                'value' => '50.000000',
                'bundle_quantity' => null,
                'pay_quantity' => null,
                'access_code' => null,
                'ticket_type' => ['show_title' => 'La noche antes', 'starts_at' => '2026-07-10 21:00:00', 'name' => 'Estudiantes'],
            ],
            [
                'name' => 'Promocional lanzamiento',
                'type' => 'fixed_discount',
                'value' => '2000.000000',
                'bundle_quantity' => null,
                'pay_quantity' => null,
                'access_code' => null,
                'ticket_type' => ['show_title' => 'Manual para desaparecer', 'starts_at' => '2026-07-11 20:30:00', 'name' => 'Promocional'],
            ],
            [
                'name' => '30% lanzamiento',
                'type' => 'percent_discount',
                'value' => '30.000000',
                'bundle_quantity' => null,
                'pay_quantity' => null,
                'access_code' => null,
                'ticket_type' => ['show_title' => 'Manual para desaparecer', 'starts_at' => '2026-07-18 20:30:00', 'name' => 'General'],
            ],
        ];

        foreach ($promotions as $promotionData) {
            $ticketTypeData = $promotionData['ticket_type'];
            unset($promotionData['ticket_type']);

            $show = Show::where('title', $ticketTypeData['show_title'])->firstOrFail();
            $presentation = Presentation::where('show_id', $show->id)
                ->where('starts_at', Carbon::parse($ticketTypeData['starts_at']))
                ->firstOrFail();
            $ticketType = PresentationTicketType::where('presentation_id', $presentation->id)
                ->where('name', $ticketTypeData['name'])
                ->firstOrFail();

            $promotionData = [
                ...$promotionData,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
                'presentation_ticket_type_id' => $ticketType->id,
            ];

            $promotion = Promotion::withTrashed()
                ->where('name', $promotionData['name'])
                ->first();

            if ($promotion) {
                $promotion->restore();
                $promotion->update($promotionData);
            } else {
                Promotion::create($promotionData);
            }
        }
    }
}
