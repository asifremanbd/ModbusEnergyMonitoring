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
            ['group' => 'Meter_1', 'label' => 'Active Power L1', 'register' => 1000],
            ['group' => 'Meter_1', 'label' => 'Active Power L2', 'register' => 1002],
            ['group' => 'Meter_1', 'label' => 'Active Power L3', 'register' => 1004],
            ['group' => 'Meter_1', 'label' => 'Voltage L1', 'register' => 1100],
            ['group' => 'Meter_1', 'label' => 'Voltage L2', 'register' => 1102],
            ['group' => 'Meter_1', 'label' => 'Voltage L3', 'register' => 1104],
            ['group' => 'Meter_1', 'label' => 'Current L1', 'register' => 1200],
            ['group' => 'Meter_1', 'label' => 'Current L2', 'register' => 1202],
            ['group' => 'Meter_1', 'label' => 'Current L3', 'register' => 1204],
            ['group' => 'Meter_2', 'label' => 'Active Power L1', 'register' => 2000],
            ['group' => 'Meter_2', 'label' => 'Active Power L2', 'register' => 2002],
            ['group' => 'Meter_2', 'label' => 'Active Power L3', 'register' => 2004],
        ];

        $metric = $this->faker->randomElement($energyMetrics);

        return [
            'gateway_id' => \App\Models\Gateway::factory(),
            'group_name' => $metric['group'],
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
            'group_name' => 'Meter_' . $this->faker->numberBetween(1, 3),
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
            'group_name' => 'Meter_' . $this->faker->numberBetween(1, 3),
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
            'group_name' => 'Meter_' . $this->faker->numberBetween(1, 3),
            'register_address' => $this->faker->numberBetween(1200, 1210),
            'data_type' => 'float32',
            'scale_factor' => 0.01,
        ]);
    }
}
