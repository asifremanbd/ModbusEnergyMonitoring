<?php

namespace Database\Factories;

use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reading>
 */
class ReadingFactory extends Factory
{
    protected $model = Reading::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_point_id' => DataPoint::factory(),
            'raw_value' => json_encode([
                fake()->numberBetween(0, 65535),
                fake()->numberBetween(0, 65535),
            ]),
            'scaled_value' => fake()->randomFloat(2, 0, 1000),
            'quality' => fake()->randomElement(['good', 'bad', 'uncertain']),
            'read_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    /**
     * Indicate that the reading has good quality.
     */
    public function goodQuality(): static
    {
        return $this->state(fn (array $attributes) => [
            'quality' => 'good',
        ]);
    }

    /**
     * Indicate that the reading has bad quality.
     */
    public function badQuality(): static
    {
        return $this->state(fn (array $attributes) => [
            'quality' => 'bad',
        ]);
    }

    /**
     * Indicate that the reading is recent.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Indicate that the reading is old.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now()->subHours(fake()->numberBetween(2, 24)),
        ]);
    }
}