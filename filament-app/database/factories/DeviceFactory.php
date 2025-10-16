<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway_id' => Gateway::factory(),
            'device_name' => $this->faker->randomElement([
                'Energy Meter 1',
                'Water Meter A',
                'HVAC Controller',
                'Lighting Panel',
                'Socket Controller',
                'Main Meter',
                'Sub Meter 1',
                'Control Unit'
            ]),
            'device_type' => $this->faker->randomElement(array_keys(Device::DEVICE_TYPES)),
            'load_category' => $this->faker->randomElement(array_keys(Device::LOAD_CATEGORIES)),
            'enabled' => $this->faker->boolean(85), // 85% chance of being enabled
        ];
    }

    /**
     * Create an energy meter device.
     */
    public function energyMeter(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_name' => 'Energy Meter ' . $this->faker->numberBetween(1, 10),
            'device_type' => 'energy_meter',
            'load_category' => $this->faker->randomElement(['hvac', 'lighting', 'sockets']),
        ]);
    }

    /**
     * Create a water meter device.
     */
    public function waterMeter(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_name' => 'Water Meter ' . $this->faker->randomElement(['A', 'B', 'C']),
            'device_type' => 'water_meter',
            'load_category' => 'other',
        ]);
    }

    /**
     * Create a control device.
     */
    public function controlDevice(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_name' => $this->faker->randomElement([
                'HVAC Controller',
                'Lighting Controller',
                'Socket Controller',
                'Main Control Unit'
            ]),
            'device_type' => 'control',
            'load_category' => $this->faker->randomElement(['hvac', 'lighting', 'sockets']),
        ]);
    }

    /**
     * Create a disabled device.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    /**
     * Create a device with HVAC load category.
     */
    public function hvac(): static
    {
        return $this->state(fn (array $attributes) => [
            'load_category' => 'hvac',
            'device_name' => 'HVAC ' . $this->faker->randomElement(['Unit 1', 'Unit 2', 'Controller']),
        ]);
    }

    /**
     * Create a device with lighting load category.
     */
    public function lighting(): static
    {
        return $this->state(fn (array $attributes) => [
            'load_category' => 'lighting',
            'device_name' => 'Lighting ' . $this->faker->randomElement(['Panel A', 'Panel B', 'Controller']),
        ]);
    }

    /**
     * Create a device with sockets load category.
     */
    public function sockets(): static
    {
        return $this->state(fn (array $attributes) => [
            'load_category' => 'sockets',
            'device_name' => 'Socket ' . $this->faker->randomElement(['Panel 1', 'Panel 2', 'Controller']),
        ]);
    }
}