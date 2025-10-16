<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Register;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Register>
 */
class RegisterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dataType = $this->faker->randomElement(array_keys(Register::DATA_TYPES));
        $count = $this->getDefaultCountForDataType($dataType);

        return [
            'device_id' => Device::factory(),
            'technical_label' => $this->faker->randomElement([
                'Active Power L1',
                'Active Power L2', 
                'Active Power L3',
                'Voltage L1',
                'Voltage L2',
                'Voltage L3',
                'Current L1',
                'Current L2',
                'Current L3',
                'Total Energy',
                'Power Factor',
                'Frequency',
                'Temperature',
                'Flow Rate',
                'Pressure',
                'Control State',
                'Alarm Status'
            ]),
            'function' => $this->faker->randomElement([3, 4]), // Holding or Input registers
            'register_address' => $this->faker->numberBetween(1000, 9999),
            'data_type' => $dataType,
            'byte_order' => $this->faker->randomElement(array_keys(Register::BYTE_ORDERS)),
            'scale' => $this->faker->randomElement([1.0, 0.1, 0.01, 10.0, 100.0]),
            'count' => $count,
            'enabled' => $this->faker->boolean(90), // 90% chance of being enabled
            'write_function' => null,
            'write_register' => null,
            'on_value' => null,
            'off_value' => null,
            'invert' => false,
            'schedulable' => false,
        ];
    }

    /**
     * Get default count for data type.
     */
    private function getDefaultCountForDataType(string $dataType): int
    {
        return match($dataType) {
            'int16', 'uint16' => 1,
            'int32', 'uint32', 'float32' => 2,
            'float64' => 4,
            default => 1,
        };
    }

    /**
     * Create a coil register (function 1).
     */
    public function coil(): static
    {
        return $this->state(fn (array $attributes) => [
            'function' => 1,
            'data_type' => 'uint16',
            'count' => 1,
            'register_address' => $this->faker->numberBetween(1, 9999),
        ]);
    }

    /**
     * Create a discrete input register (function 2).
     */
    public function discreteInput(): static
    {
        return $this->state(fn (array $attributes) => [
            'function' => 2,
            'data_type' => 'uint16',
            'count' => 1,
            'register_address' => $this->faker->numberBetween(10001, 19999),
        ]);
    }

    /**
     * Create a holding register (function 3).
     */
    public function holdingRegister(): static
    {
        return $this->state(fn (array $attributes) => [
            'function' => 3,
            'register_address' => $this->faker->numberBetween(40001, 49999),
        ]);
    }

    /**
     * Create an input register (function 4).
     */
    public function inputRegister(): static
    {
        return $this->state(fn (array $attributes) => [
            'function' => 4,
            'register_address' => $this->faker->numberBetween(30001, 39999),
        ]);
    }

    /**
     * Create a float32 register.
     */
    public function float32(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'float32',
            'count' => 2,
            'byte_order' => 'big_endian',
        ]);
    }

    /**
     * Create an int16 register.
     */
    public function int16(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'int16',
            'count' => 1,
        ]);
    }

    /**
     * Create a uint32 register.
     */
    public function uint32(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_type' => 'uint32',
            'count' => 2,
        ]);
    }

    /**
     * Create a disabled register.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    /**
     * Create a register with write capabilities.
     */
    public function writable(): static
    {
        return $this->state(fn (array $attributes) => [
            'write_function' => $this->faker->randomElement([5, 6, 15, 16]),
            'write_register' => $this->faker->numberBetween(1000, 9999),
            'on_value' => 1.0,
            'off_value' => 0.0,
            'schedulable' => true,
        ]);
    }

    /**
     * Create a schedulable register.
     */
    public function schedulable(): static
    {
        return $this->state(fn (array $attributes) => [
            'write_function' => 6,
            'write_register' => $this->faker->numberBetween(1000, 9999),
            'on_value' => 1.0,
            'off_value' => 0.0,
            'schedulable' => true,
        ]);
    }

    /**
     * Create a power measurement register.
     */
    public function power(): static
    {
        return $this->state(fn (array $attributes) => [
            'technical_label' => 'Active Power L' . $this->faker->numberBetween(1, 3),
            'function' => 4,
            'data_type' => 'float32',
            'count' => 2,
            'scale' => 1.0,
            'register_address' => $this->faker->numberBetween(1000, 1010),
        ]);
    }

    /**
     * Create a voltage measurement register.
     */
    public function voltage(): static
    {
        return $this->state(fn (array $attributes) => [
            'technical_label' => 'Voltage L' . $this->faker->numberBetween(1, 3),
            'function' => 4,
            'data_type' => 'float32',
            'count' => 2,
            'scale' => 0.1,
            'register_address' => $this->faker->numberBetween(1100, 1110),
        ]);
    }

    /**
     * Create a current measurement register.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'technical_label' => 'Current L' . $this->faker->numberBetween(1, 3),
            'function' => 4,
            'data_type' => 'float32',
            'count' => 2,
            'scale' => 0.01,
            'register_address' => $this->faker->numberBetween(1200, 1210),
        ]);
    }
}