<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gateway>
 */
class GatewayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Gateway',
            'ip_address' => $this->faker->localIpv4(),
            'port' => $this->faker->randomElement([502, 1502, 2502]),
            'unit_id' => $this->faker->numberBetween(1, 10),
            'poll_interval' => $this->faker->randomElement([5, 10, 15, 30, 60]),
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
            'last_seen_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'success_count' => $this->faker->numberBetween(100, 1000),
            'failure_count' => $this->faker->numberBetween(0, 50),
        ];
    }

    /**
     * Indicate that the gateway is offline.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'last_seen_at' => $this->faker->dateTimeBetween('-2 hours', '-1 hour'),
        ]);
    }

    /**
     * Indicate that the gateway is online and recently seen.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'last_seen_at' => $this->faker->dateTimeBetween('-5 minutes', 'now'),
        ]);
    }

    /**
     * Indicate that the gateway has high failure rate.
     */
    public function unreliable(): static
    {
        return $this->state(fn (array $attributes) => [
            'success_count' => $this->faker->numberBetween(50, 200),
            'failure_count' => $this->faker->numberBetween(100, 300),
        ]);
    }
}
