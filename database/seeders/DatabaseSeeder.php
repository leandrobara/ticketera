<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Comment;
use App\Models\Order;
use App\Models\Person;
use App\Models\Presentation;
use App\Models\PresentationTicketType;
use App\Models\Season;
use App\Models\Show;
use App\Models\ShowCredit;
use App\Models\ShowImage;
use App\Models\ShowLink;
use App\Models\ShowPerformanceHistory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
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
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->seedBuyers();
        $this->seedVenues();
        $this->seedShows();
        $this->seedSeasons();
        $this->seedShowImages();
        $this->seedShowPerformanceHistories();
        $this->seedShowLinks();
        $this->seedPeople();
        $this->seedShowCredits();
        $this->seedPresentations();
        $this->seedPresentationTicketTypes();
        $this->seedComments();
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
            [
                'name' => 'Sofia',
                'last_name' => 'Fernandez',
                'email' => 'sofia.fernandez@example.com',
                'phone' => '5491199990000',
                'dni' => '33999888',
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

    private function seedPeople(): void
    {
        $people = [
            [
                'display_name' => 'Martín Seefeld',
                'normalized_name' => 'martin seefeld',
                'first_name' => 'Martín',
                'last_name' => 'Seefeld',
                'email' => 'martin.seefeld@example.com',
                'document_type' => 'DNI',
                'document_number' => '20111222',
                'phone' => '5491111112222',
                'photo_path' => null,
                'bio' => 'Actor argentino con trayectoria en teatro y television.',
                'instagram_url' => null,
                'website_url' => null,
            ],
            [
                'display_name' => 'Andrea Frigerio',
                'normalized_name' => 'andrea frigerio',
                'first_name' => 'Andrea',
                'last_name' => 'Frigerio',
                'email' => 'andrea.frigerio@example.com',
                'document_type' => 'DNI',
                'document_number' => '20222333',
                'phone' => '5491122223333',
                'photo_path' => null,
                'bio' => 'Actriz, conductora y modelo.',
                'instagram_url' => null,
                'website_url' => null,
            ],
            [
                'display_name' => 'Luis Brandoni',
                'normalized_name' => 'luis brandoni',
                'first_name' => 'Luis',
                'last_name' => 'Brandoni',
                'email' => 'luis.brandoni@example.com',
                'document_type' => 'DNI',
                'document_number' => '20333444',
                'phone' => null,
                'photo_path' => null,
                'bio' => 'Actor argentino de extensa trayectoria.',
                'instagram_url' => null,
                'website_url' => null,
            ],
            [
                'display_name' => 'Pablo Novak',
                'normalized_name' => 'pablo novak',
                'first_name' => 'Pablo',
                'last_name' => 'Novak',
                'email' => 'pablo.novak@example.com',
                'document_type' => 'DNI',
                'document_number' => '20444555',
                'phone' => null,
                'photo_path' => null,
                'bio' => 'Actor y director.',
                'instagram_url' => null,
                'website_url' => null,
            ],
            [
                'display_name' => 'Laura Oliva',
                'normalized_name' => 'laura oliva',
                'first_name' => 'Laura',
                'last_name' => 'Oliva',
                'email' => 'laura.oliva@example.com',
                'document_type' => 'DNI',
                'document_number' => '20555666',
                'phone' => null,
                'photo_path' => null,
                'bio' => 'Actriz y comediante.',
                'instagram_url' => null,
                'website_url' => null,
            ],
            [
                'display_name' => 'Daniel Di Pace',
                'normalized_name' => 'daniel di pace',
                'first_name' => 'Daniel',
                'last_name' => 'Di Pace',
                'email' => 'daniel.dipace@example.com',
                'document_type' => 'DNI',
                'document_number' => '20666777',
                'phone' => null,
                'photo_path' => null,
                'bio' => 'Compositor y musico.',
                'instagram_url' => null,
                'website_url' => null,
            ],
            [
                'display_name' => 'Rosario Suban',
                'normalized_name' => 'rosario suban',
                'first_name' => 'Rosario',
                'last_name' => 'Suban',
                'email' => 'rosario.suban@example.com',
                'document_type' => 'DNI',
                'document_number' => '20777888',
                'phone' => null,
                'photo_path' => null,
                'bio' => 'Asistente de direccion.',
                'instagram_url' => null,
                'website_url' => null,
            ],
            [
                'display_name' => 'Guillermo Francella',
                'normalized_name' => 'guillermo francella',
                'first_name' => 'Guillermo',
                'last_name' => 'Francella',
                'email' => 'guillermo.francella@example.com',
                'document_type' => 'DNI',
                'document_number' => '20888999',
                'phone' => null,
                'photo_path' => null,
                'bio' => 'Actor argentino.',
                'instagram_url' => null,
                'website_url' => null,
            ],
            [
                'display_name' => 'Guillermo Francella',
                'normalized_name' => 'guillermo francella',
                'first_name' => 'Guillermo',
                'last_name' => 'Francella',
                'email' => 'guillermo.francella.tecnico@example.com',
                'document_type' => 'DNI',
                'document_number' => '20999000',
                'phone' => null,
                'photo_path' => null,
                'bio' => 'Tecnico de sonido homonimo.',
                'instagram_url' => null,
                'website_url' => null,
            ],
        ];

        foreach ($people as $personData) {
            $person = Person::withTrashed()
                ->where('email', $personData['email'])
                ->first();

            if ($person) {
                $person->restore();
                $person->update($personData);
                continue;
            }

            Person::create($personData);
        }
    }

    private function seedShowCredits(): void
    {
        $credits = [
            [
                'show_title' => 'La noche antes',
                'person_email' => 'martin.seefeld@example.com',
                'role_label' => 'Actor',
                'section' => 'cast',
                'character_name' => 'Chance Gardiner',
                'sort_order' => 1,
            ],
            [
                'show_title' => 'La noche antes',
                'person_email' => 'andrea.frigerio@example.com',
                'role_label' => 'Actriz',
                'section' => 'cast',
                'character_name' => 'Eva',
                'sort_order' => 2,
            ],
            [
                'show_title' => 'La noche antes',
                'person_email' => 'luis.brandoni@example.com',
                'role_label' => 'Actor',
                'section' => 'cast',
                'character_name' => 'Ben',
                'sort_order' => 3,
            ],
            [
                'show_title' => 'La noche antes',
                'person_email' => 'pablo.novak@example.com',
                'role_label' => 'Actor',
                'section' => 'cast',
                'character_name' => 'Presidente',
                'sort_order' => 4,
            ],
            [
                'show_title' => 'La noche antes',
                'person_email' => 'daniel.dipace@example.com',
                'role_label' => 'Música original',
                'section' => 'technical',
                'character_name' => null,
                'sort_order' => 1,
            ],
            [
                'show_title' => 'La noche antes',
                'person_email' => 'rosario.suban@example.com',
                'role_label' => 'Asistencia de dirección',
                'section' => 'technical',
                'character_name' => null,
                'sort_order' => 2,
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'person_email' => 'laura.oliva@example.com',
                'role_label' => 'Actriz',
                'section' => 'cast',
                'character_name' => 'Julia',
                'sort_order' => 1,
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'person_email' => 'guillermo.francella@example.com',
                'role_label' => 'Actor',
                'section' => 'cast',
                'character_name' => 'Roberto',
                'sort_order' => 2,
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'person_email' => 'guillermo.francella.tecnico@example.com',
                'role_label' => 'Sonido',
                'section' => 'technical',
                'character_name' => null,
                'sort_order' => 1,
            ],
        ];

        foreach ($credits as $creditData) {
            $show = Show::where('title', $creditData['show_title'])->firstOrFail();
            $person = Person::where('email', $creditData['person_email'])->firstOrFail();

            $attributes = [
                'show_id' => $show->id,
                'person_id' => $person->id,
                'role_label' => $creditData['role_label'],
                'section' => $creditData['section'],
                'character_name' => $creditData['character_name'],
                'display_name_override' => null,
                'photo_path_override' => null,
                'sort_order' => $creditData['sort_order'],
                'notes' => null,
            ];

            $showCredit = ShowCredit::withTrashed()
                ->where('show_id', $show->id)
                ->where('person_id', $person->id)
                ->where('role_label', $creditData['role_label'])
                ->where('section', $creditData['section'])
                ->where('character_name', $creditData['character_name'])
                ->first();

            if ($showCredit) {
                $showCredit->restore();
                $showCredit->update($attributes);
                continue;
            }

            ShowCredit::create($attributes);
        }
    }

    private function seedVenues(): void
    {
        $venues = [
            [
                'name' => 'Teatro El Umbral',
                'capacity' => 80,
                'note' => 'Sala independiente de formato intimista. Ingreso por boleteria sobre Corrientes.',
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
                'note' => 'Espacio cultural para teatro y musica. Cuenta con foyer para espera previa.',
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
                'note' => 'Sala chica para obras autogestionadas.',
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
                'subtitle' => 'Una historia sobre decisiones pequenas que cambian una vida.',
                'synopsis' => 'Un profesor de literatura reúne a sus antiguos compañeros de escuela veinticinco años después de la graduación. Lo que comienza como una noche de recuerdos, bromas y cuentas pendientes cambia cuando una revelación inesperada obliga al grupo a revisar las decisiones que marcaron sus vidas y a preguntarse cuánto queda de quienes soñaban ser.',
                'production_note' => 'La produccion recomienda llegar con 20 minutos de anticipacion.',
                'instagram_url' => 'https://www.instagram.com/entradatix/',
                'facebook_url' => 'https://www.facebook.com/entradatix/',
                'x_url' => null,
                'tiktok_url' => 'https://www.tiktok.com/@entradatix',
                'youtube_url' => 'https://www.youtube.com/@entradatix',
                'pinterest_url' => null,
                'website_url' => null,
                'faqs' => [
                    [
                        'question' => '¿Cuánto dura la obra?',
                        'answer' => 'La obra dura aproximadamente 75 minutos.',
                        'sort_order' => 1,
                    ],
                    [
                        'question' => '¿Con cuánta anticipación conviene llegar?',
                        'answer' => 'Recomendamos llegar 20 minutos antes del inicio de la función.',
                        'sort_order' => 2,
                    ],
                ],
                'duration_minutes' => 75,
                'genre' => 'Drama',
                'format' => 'Theater',
                'age_rating' => '+13',
                'service_fee_type' => 'fixed_amount',
                'service_fee_fixed_amount' => '2000.000000',
                'service_fee_percentage' => null,
                'service_fee_minimum_unit_amount' => '2000.000000',
            ],
            [
                'title' => 'Manual para desaparecer',
                'slug' => 'manual-para-desaparecer',
                'subtitle' => 'Comedia amarga sobre identidad, trabajo y deseo.',
                'synopsis' => 'Comedia amarga sobre identidad, trabajo y deseo.',
                'production_note' => 'Funcion con conversatorio posterior algunos dias.',
                'faqs' => [
                    [
                        'question' => '¿Hay conversatorio después de la función?',
                        'answer' => 'Algunas funciones incluyen conversatorio posterior. La información se confirma en la ficha de cada fecha.',
                        'sort_order' => 1,
                    ],
                ],
                'duration_minutes' => 65,
                'genre' => 'Comedy',
                'format' => 'Theater',
                'age_rating' => '+16',
                'service_fee_type' => 'percentage',
                'service_fee_fixed_amount' => null,
                'service_fee_percentage' => '10.000000',
                'service_fee_minimum_unit_amount' => '2000.000000',
            ],
            [
                'title' => 'Ensayo abierto',
                'slug' => 'ensayo-abierto',
                'subtitle' => 'Proceso escenico abierto al publico.',
                'synopsis' => 'Proceso escenico abierto al publico.',
                'production_note' => null,
                'faqs' => [],
                'duration_minutes' => 50,
                'genre' => 'Experimental',
                'format' => 'Performance',
                'age_rating' => 'ATP',
                'service_fee_type' => 'fixed_amount',
                'service_fee_fixed_amount' => '0.000000',
                'service_fee_percentage' => null,
                'service_fee_minimum_unit_amount' => '2000.000000',
            ],
        ];

        foreach ($shows as $showData) {
            Show::updateOrCreate(
                ['title' => $showData['title']],
                $showData,
            );
        }
    }

    private function seedSeasons(): void
    {
        $seasons = [
            [
                'show_title' => 'La noche antes',
                'venue_name' => 'Teatro El Umbral',
                'name' => 'Temporada 2026',
                'status' => 'published',
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'venue_name' => 'Espacio La Trama',
                'name' => 'Temporada 2026',
                'status' => 'published',
            ],
            [
                'show_title' => 'Ensayo abierto',
                'venue_name' => 'Sala Patio Sur',
                'name' => 'Temporada de prueba',
                'status' => 'draft',
            ],
        ];

        foreach ($seasons as $seasonData) {
            $show = Show::where('title', $seasonData['show_title'])->firstOrFail();
            $venue = Venue::where('name', $seasonData['venue_name'])->firstOrFail();

            $season = Season::withTrashed()
                ->where('show_id', $show->id)
                ->where('venue_id', $venue->id)
                ->where('closed_season_id', 0)
                ->first();

            $attributes = [
                'show_id' => $show->id,
                'venue_id' => $venue->id,
                'name' => $seasonData['name'],
                'status' => $seasonData['status'],
                'closed_season_id' => 0,
                'published_at' => $seasonData['status'] === 'published' ? now() : null,
                'closed_at' => null,
            ];

            if ($season) {
                $season->restore();
                $season->update($attributes);
                continue;
            }

            Season::create($attributes);
        }
    }

    private function seedShowImages(): void
    {
        $showImages = [
            [
                'show_title' => 'La noche antes',
                'path' => 'shows/1/bb2d87eb-6f48-46ad-97cb-9c3fd8700d82.jpeg',
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'path' => 'shows/2/c6d7d0a4-9791-4980-a37b-e62ef9b657e2.jpg',
            ],
        ];

        foreach ($showImages as $showImageData) {
            $show = Show::where('title', $showImageData['show_title'])->firstOrFail();

            ShowImage::withTrashed()
                ->where('show_id', $show->id)
                ->update(['is_main' => false]);

            $image = ShowImage::withTrashed()->updateOrCreate(
                [
                    'show_id' => $show->id,
                    'path' => $showImageData['path'],
                ],
                [
                    'type' => 'gallery',
                    'alt_text' => $showImageData['show_title'],
                    'caption' => null,
                    'sort_order' => 1,
                    'is_main' => true,
                ],
            );

            if ($image->trashed()) {
                $image->restore();
            }
        }
    }

    private function seedShowPerformanceHistories(): void
    {
        $show = Show::where('title', 'La noche antes')->firstOrFail();
        $histories = [
            ['year' => '2025', 'venue_name' => 'Teatro Metropolitan Sura', 'sort_order' => 1],
            ['year' => '2024', 'venue_name' => 'Teatro Maipú', 'sort_order' => 2],
            ['year' => '2023', 'venue_name' => 'Centro Cultural de la Cooperación', 'sort_order' => 3],
            ['year' => '2022', 'venue_name' => 'Espacio La Trama', 'sort_order' => 4],
        ];

        foreach ($histories as $historyData) {
            $history = ShowPerformanceHistory::withTrashed()->updateOrCreate(
                [
                    'show_id' => $show->id,
                    'year' => $historyData['year'],
                    'venue_name' => $historyData['venue_name'],
                ],
                [
                    'sort_order' => $historyData['sort_order'],
                ],
            );

            if ($history->trashed()) {
                $history->restore();
            }
        }
    }

    private function seedShowLinks(): void
    {
        $show = Show::where('title', 'La noche antes')->firstOrFail();
        $links = [
            [
                'text' => 'La noche antes: una obra sobre los vínculos y el paso del tiempo',
                'url' => 'https://www.pagina12.com.ar/',
                'sort_order' => 1,
            ],
            [
                'text' => 'Entrevista con el elenco y el equipo creativo',
                'url' => 'https://www.youtube.com/',
                'sort_order' => 2,
            ],
        ];

        foreach ($links as $linkData) {
            $link = ShowLink::withTrashed()->updateOrCreate(
                [
                    'show_id' => $show->id,
                    'text' => $linkData['text'],
                    'url' => $linkData['url'],
                ],
                [
                    'sort_order' => $linkData['sort_order'],
                ],
            );

            if ($link->trashed()) {
                $link->restore();
            }
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
            $season = Season::where('show_id', $show->id)
                ->where('venue_id', $venue->id)
                ->where('closed_season_id', 0)
                ->firstOrFail();
            $startsAt = Carbon::parse($presentationData['starts_at']);

            $presentation = Presentation::withTrashed()
                ->where('season_id', $season->id)
                ->where('starts_at', $startsAt)
                ->first();

            $attributes = [
                'season_id' => $season->id,
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
                'promotion_name' => '2x1 General',
                'promotion_type' => 'buy_x_get_y',
                'promotion_value' => null,
                'promotion_bundle_quantity' => 2,
                'promotion_pay_quantity' => 1,
                'promotion_access_code' => null,
                'promotion_is_active' => true,
            ],
            [
                'show_title' => 'La noche antes',
                'starts_at' => '2026-07-10 21:00:00',
                'name' => 'Estudiantes',
                'price' => '15000.000000',
                'stock' => 20,
                'sort_order' => 2,
                'promotion_name' => 'Descuento estudiantes',
                'promotion_type' => 'percent_discount',
                'promotion_value' => '50.000000',
                'promotion_bundle_quantity' => null,
                'promotion_pay_quantity' => null,
                'promotion_access_code' => null,
                'promotion_is_active' => true,
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
                'promotion_name' => 'Promocional lanzamiento',
                'promotion_type' => 'fixed_discount',
                'promotion_value' => '2000.000000',
                'promotion_bundle_quantity' => null,
                'promotion_pay_quantity' => null,
                'promotion_access_code' => null,
                'promotion_is_active' => true,
            ],
            [
                'show_title' => 'Manual para desaparecer',
                'starts_at' => '2026-07-18 20:30:00',
                'name' => 'General',
                'price' => '18000.000000',
                'stock' => 120,
                'sort_order' => 1,
                'promotion_name' => '30% lanzamiento',
                'promotion_type' => 'percent_discount',
                'promotion_value' => '30.000000',
                'promotion_bundle_quantity' => null,
                'promotion_pay_quantity' => null,
                'promotion_access_code' => null,
                'promotion_is_active' => true,
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
            $presentation = Presentation::whereHas(
                'season',
                fn ($query) => $query->where('show_id', $show->id)
            )
                ->where('starts_at', Carbon::parse($ticketTypeData['starts_at']))
                ->firstOrFail();

            $ticketType = PresentationTicketType::withTrashed()
                ->where('presentation_id', $presentation->id)
                ->where('name', $ticketTypeData['name'])
                ->first();

            $attributes = [
                'presentation_id' => $presentation->id,
                'name' => $ticketTypeData['name'],
                'price' => $ticketTypeData['price'],
                'stock' => $ticketTypeData['stock'],
                'is_active' => true,
                'sort_order' => $ticketTypeData['sort_order'],
                'promotion_name' => $ticketTypeData['promotion_name'] ?? null,
                'promotion_type' => $ticketTypeData['promotion_type'] ?? null,
                'promotion_value' => $ticketTypeData['promotion_value'] ?? null,
                'promotion_bundle_quantity' => $ticketTypeData['promotion_bundle_quantity'] ?? null,
                'promotion_pay_quantity' => $ticketTypeData['promotion_pay_quantity'] ?? null,
                'promotion_access_code' => $ticketTypeData['promotion_access_code'] ?? null,
                'promotion_is_active' => $ticketTypeData['promotion_is_active'] ?? false,
            ];

            if ($ticketType) {
                $ticketType->restore();
                $ticketType->update($attributes);
                continue;
            }

            PresentationTicketType::create($attributes);
        }
    }

    private function seedComments(): void
    {
        $show = Show::where('title', 'La noche antes')->firstOrFail();
        $presentation = Presentation::whereHas(
            'season',
            fn ($query) => $query->where('show_id', $show->id)
        )
            ->orderBy('starts_at')
            ->firstOrFail();

        $comments = [
            [
                'buyer_email' => 'juan.perez@example.com',
                'name' => 'Juan',
                'rating' => 5,
                'comment' => 'Una obra emocionante y muy bien actuada. El final me sorprendió y nos dejó hablando durante toda la vuelta a casa.',
            ],
            [
                'buyer_email' => 'lucia.gomez@example.com',
                'name' => 'Lucía',
                'rating' => 4,
                'comment' => 'Me gustó mucho la historia y el clima íntimo de la sala. Hay momentos muy divertidos y otros realmente conmovedores.',
            ],
            [
                'buyer_email' => 'martin.rodriguez@example.com',
                'name' => 'Martín',
                'rating' => 5,
                'comment' => 'Excelente puesta y actuaciones muy parejas. Se nota el trabajo del elenco y la cercanía con el público suma muchísimo.',
            ],
            [
                'buyer_email' => 'sofia.fernandez@example.com',
                'name' => 'Sofía',
                'rating' => 5,
                'comment' => 'La recomiendo. Tiene un ritmo muy bueno y personajes en los que es fácil reconocerse.',
            ],
            [
                'buyer_email' => 'juan.perez@example.com',
                'name' => 'Juan P.',
                'rating' => 4,
                'comment' => 'Volví con amigos y la disfruté tanto como la primera vez. Muy buena energía en escena.',
            ],
            [
                'buyer_email' => 'lucia.gomez@example.com',
                'name' => 'Lucía Gómez',
                'rating' => 5,
                'comment' => 'Una propuesta sensible, cercana y con mucho humor. Salimos encantados.',
            ],
            [
                'buyer_email' => 'martin.rodriguez@example.com',
                'name' => 'Martín R.',
                'rating' => 4,
                'comment' => 'Muy buenas actuaciones y una historia que sostiene la atención hasta el final.',
            ],
            [
                'buyer_email' => 'sofia.fernandez@example.com',
                'name' => 'Sofía Fernández',
                'rating' => 5,
                'comment' => 'Nos reímos, nos emocionamos y nos quedamos con ganas de recomendarla. Gran noche de teatro.',
            ],
            [
                'buyer_email' => 'juan.perez@example.com',
                'name' => 'Juan Pérez',
                'rating' => 4,
                'comment' => 'La sala y la puesta ayudan a sentirse parte de la historia. Una experiencia muy cálida.',
            ],
            [
                'buyer_email' => 'lucia.gomez@example.com',
                'name' => 'Lucía G.',
                'rating' => 5,
                'comment' => 'El texto, la dirección y el elenco funcionan muy bien juntos. Volvería a verla.',
            ],
        ];

        foreach ($comments as $index => $commentData) {
            $buyer = Buyer::where('email', $commentData['buyer_email'])->firstOrFail();
            $orderCode = sprintf('ORD-SEED-COMMENT-%02d', $index + 1);

            $order = Order::withTrashed()->updateOrCreate([
                'code' => $orderCode,
            ], [
                'show_id' => $show->id,
                'presentation_id' => $presentation->id,
                'buyer_id' => $buyer->id,
                'created_by_user_id' => null,
                'source' => 'CHECKOUT',
                'status' => 'APPROVED',
                'payment_method' => 'MERCADO_PAGO',
                'total_quantity' => 1,
                'total_amount' => '20000.000000',
                'currency' => 'ARS',
                'approved_at' => now()->subDays(12 - $index),
                'expires_at' => null,
                'notes' => 'Orden de referencia para comentarios del seeder.',
                'deleted_at' => null,
            ]);

            if ($order->trashed()) {
                $order->restore();
            }

            $comment = Comment::withTrashed()->updateOrCreate([
                'order_id' => $order->id,
            ], [
                'buyer_id' => $buyer->id,
                'show_id' => $show->id,
                'name' => $commentData['name'],
                'rating' => $commentData['rating'],
                'comment' => $commentData['comment'],
                'status' => 'visible',
                'deleted_at' => null,
            ]);

            if ($comment->trashed()) {
                $comment->restore();
            }

            $comment->created_at = now()->subDays(10 - $index);
            $comment->saveQuietly();
        }
    }
}
