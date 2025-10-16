<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DataPoint>
 */
class DataPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $energyMetrics = [
            ['application' => 'monitoring', 'label' => 'Active Power L1', 'register' => 1000, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'monitoring', 'label' => 'Active Power L2', 'register' => 1002, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'monitoring', 'label' => 'Active Power L3', 'register' => 1004, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'monitoring', 'label' => 'Voltage L1', 'register' => 1100, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'monitoring', 'label' => 'Voltage L2', 'register' => 1102, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'monitoring', 'label' => 'Voltage L3', 'register' => 1104, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'monitoring', 'label' => 'Current L1', 'register' => 1200, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'monitoring', 'label' => 'Current L2', 'register' => 1202, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'monitoring', 'label' => 'Current L3', 'register' => 1204, 'unit' => 'kWh', 'load_type' => 'power'],
            ['application' => 'automation', 'label' => 'Water Flow L1', 'register' => 2000, 'unit' => 'm³', 'load_type' => 'water'],
            ['application' => 'automation', 'label' => 'Water Flow L2', 'register' => 2002, 'unit' => 'm³', 'load_type' => 'water'],
            ['application' => 'automation', 'label' => 'Water Flow L3', 'register' => 2004, 'unit' => 'm³', 'load_type' => 'water'],
        ];

        $metric = $this->faker->randomElement($energyMetrics);

        return [
            'gateway_id' => \App\Models\Gateway::factory(),
            'application' => $metric['application'],
            'unit' => $metric['unit'],
            'load_type' => $metric['load_type'],
            'label' => $metric['label'],
            'modbus_function' => $this->faker->randomElement([3, 4]), // Holding or Input registers
            'register_address' => $metric['register'],
            'register_count' => 2, // Default for float32
            'data_type' => $this->faker->randomElement(['float32', 'uint32', 'int32']),
            'byte_order' => $this->faker->randomElement(['word_swapped', 'big_endian', 'little_endian']),
            'scale_factor' => $this->faker->randomElement([1.0, 0.1, 0.01, 10.0]),
            'is_enabled' => $this->faker->boolean(90), // 90% chance of being enabled
        ];
    }

    /**
     * Create a Teltonika energy meter data point.
     */
    public function teltonikaEnergy(): static
    {
        return $this->state(fn (array $attributes) => [
            'modbus_function' => 4, // Input registers
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 1.0,
        ]);
    }

    /**
     * Create a voltage measurement data point.
     */
    public function voltage(): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => 'Voltage L' . $this->faker->numberBetween(1, 3),
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'register_address' => $this->faker->numberBetween(1100, 1110),
            'data_type' => 'float32',
            'scale_factor' => 0.1,
        ]);
    }

    /**
     * Create a power measurement data point.
     */
    public function power(): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => 'Active Power L' . $this->faker->numberBetween(1, 3),
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'register_address' => $this->faker->numberBetween(1000, 1010),
            'data_type' => 'float32',
            'scale_factor' => 1.0,
        ]);
    }

    /**
     * Create a current measurement data point.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => 'Current L' . $this->faker->numberBetween(1, 3),
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'register_address' => $this->faker->numberBetween(1200, 1210),
            'data_type' => 'float32',
            'scale_factor' => 0.01,
        ]);
    }
}
