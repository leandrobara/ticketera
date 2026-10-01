<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Show;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Show>
 */
class ShowFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'organization_id' => function () {
                $organization = new Organization(['name' => fake()->company()]);
                $organization->creator_user_id = User::factory()->create()->id;
                $organization->save();

                return $organization->id;
            },
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'subtitle' => fake()->optional()->sentence(),
            'synopsis' => fake()->paragraphs(2, true),
            'production_note' => fake()->optional()->paragraph(),
            'faqs' => [],
            'service_fee_type' => 'fixed_amount',
            'service_fee_fixed_amount' => '0.000000',
            'service_fee_percentage' => null,
            'service_fee_minimum_unit_amount' => '2000.000000',
        ];
    }
}
