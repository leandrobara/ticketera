<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Show;
use App\Models\User;
use App\Models\Venue;
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
}
