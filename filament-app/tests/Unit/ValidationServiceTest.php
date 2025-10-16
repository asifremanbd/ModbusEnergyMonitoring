<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValidationService;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new ValidationService();
    }

    /** @test */
    public function it_validates_gateway_data_correctly()
    {
        // Valid gateway data
        $validData = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'is_active' => true,
        ];

        $errors = $this->validationService->validateGateway($validData);
        $this->assertEmpty($errors);

        // Invalid IP address
        $invalidData = $validData;
        $invalidData['ip_address'] = 'invalid-ip';
        $errors = $this->validationService->validateGateway($invalidData);
        $this->assertArrayHasKey('ip_address', $errors);

        // Invalid port range
        $invalidData = $validData;
        $invalidData['port'] = 70000;
        $errors = $this->validationService->validateGateway($invalidData);
        $this->assertArrayHasKey('port', $errors);
    }

    /** @test */
    public function it_validates_device_data_correctly()
    {
        $gateway = Gateway::factory()->create();

        // Valid device data
        $validData = [
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
            'enabled' => true,
        ];

        $errors = $this->validationService->validateDevice($validData, $gateway->id);
        $this->assertEmpty($errors);

        // Invalid device type
        $invalidData = $validData;
        $invalidData['device_type'] = 'invalid_type';
        $errors = $this->validationService->validateDevice($invalidData, $gateway->id);
        $this->assertArrayHasKey('device_type', $errors);

        // Invalid load category
        $invalidData = $validData;
        $invalidData['load_category'] = 'invalid_category';
        $errors = $this->validationService->validateDevice($invalidData, $gateway->id);
        $this->assertArrayHasKey('load_category', $errors);
    }

    /** @test */
    public function it_validates_register_data_correctly()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Valid register data
        $validData = [
            'device_id' => $device->id,
            'technical_label' => 'Test Register',
            'function' => 4,
            'register_address' => 1025,
            'data_type' => 'float32',
            'byte_order' => 'word_swap',
            'scale' => 1.0,
            'count' => 2,
            'enabled' => true,
        ];

        $errors = $this->validationService->validateRegister($validData, $device->id);
        $this->assertEmpty($errors);

        // Invalid register address
        $invalidData = $validData;
        $invalidData['register_address'] = 70000;
        $errors = $this->validationService->validateRegister($invalidData, $device->id);
        $this->assertArrayHasKey('register_address', $errors);

        // Invalid Modbus function
        $invalidData = $validData;
        $invalidData['function'] = 10;
        $errors = $this->validationService->validateRegister($invalidData, $device->id);
        $this->assertArrayHasKey('function', $errors);
    }

    /** @test */
    public function it_validates_ip_addresses_correctly()
    {
        $this->assertTrue($this->validationService->validateIpAddress('192.168.1.1'));
        $this->assertTrue($this->validationService->validateIpAddress('10.0.0.1'));
        $this->assertFalse($this->validationService->validateIpAddress('invalid-ip'));
        $this->assertFalse($this->validationService->validateIpAddress('999.999.999.999'));
    }

    /** @test */
    public function it_validates_port_ranges_correctly()
    {
        $this->assertTrue($this->validationService->validatePort(1));
        $this->assertTrue($this->validationService->validatePort(502));
        $this->assertTrue($this->validationService->validatePort(65535));
        $this->assertFalse($this->validationService->validatePort(0));
        $this->assertFalse($this->validationService->validatePort(65536));
    }

    /** @test */
    public function it_validates_modbus_addresses_correctly()
    {
        $this->assertTrue($this->validationService->validateModbusAddress(0));
        $this->assertTrue($this->validationService->validateModbusAddress(1025));
        $this->assertTrue($this->validationService->validateModbusAddress(65535));
        $this->assertFalse($this->validationService->validateModbusAddress(-1));
        $this->assertFalse($this->validationService->validateModbusAddress(65536));
    }

    /** @test */
    public function it_validates_modbus_address_ranges_correctly()
    {
        $this->assertTrue($this->validationService->validateModbusAddressRange(0, 1));
        $this->assertTrue($this->validationService->validateModbusAddressRange(65533, 2));
        $this->assertTrue($this->validationService->validateModbusAddressRange(65535, 1));
        $this->assertFalse($this->validationService->validateModbusAddressRange(65534, 2));
        $this->assertFalse($this->validationService->validateModbusAddressRange(65535, 2));
    }

    /** @test */
    public function it_validates_scale_factors_correctly()
    {
        $this->assertTrue($this->validationService->validateScaleFactor(0.001));
        $this->assertTrue($this->validationService->validateScaleFactor(1.0));
        $this->assertTrue($this->validationService->validateScaleFactor(1000000.0));
        $this->assertFalse($this->validationService->validateScaleFactor(0.0));
        $this->assertFalse($this->validationService->validateScaleFactor(-1.0));
        $this->assertFalse($this->validationService->validateScaleFactor(1000001.0));
    }

    /** @test */
    public function it_checks_gateway_ip_port_uniqueness()
    {
        $gateway = Gateway::factory()->create([
            'ip_address' => '192.168.1.100',
            'port' => 502,
        ]);

        // Same IP and port should not be unique
        $this->assertFalse($this->validationService->isGatewayIpPortUnique('192.168.1.100', 502));

        // Same IP but different port should be unique
        $this->assertTrue($this->validationService->isGatewayIpPortUnique('192.168.1.100', 503));

        // Different IP same port should be unique
        $this->assertTrue($this->validationService->isGatewayIpPortUnique('192.168.1.101', 502));

        // Excluding the existing gateway should make it unique
        $this->assertTrue($this->validationService->isGatewayIpPortUnique('192.168.1.100', 502, $gateway->id));
    }

    /** @test */
    public function it_checks_device_name_uniqueness_in_gateway()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
        ]);

        // Same name in same gateway should not be unique
        $this->assertFalse($this->validationService->isDeviceNameUniqueInGateway('Test Device', $gateway->id));

        // Different name in same gateway should be unique
        $this->assertTrue($this->validationService->isDeviceNameUniqueInGateway('Different Device', $gateway->id));

        // Same name in different gateway should be unique
        $otherGateway = Gateway::factory()->create();
        $this->assertTrue($this->validationService->isDeviceNameUniqueInGateway('Test Device', $otherGateway->id));

        // Excluding the existing device should make it unique
        $this->assertTrue($this->validationService->isDeviceNameUniqueInGateway('Test Device', $gateway->id, $device->id));
    }

    /** @test */
    public function it_checks_register_address_uniqueness_in_device()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create([
            'device_id' => $device->id,
            'register_address' => 1025,
        ]);

        // Same address in same device should not be unique
        $this->assertFalse($this->validationService->isRegisterAddressUniqueInDevice(1025, $device->id));

        // Different address in same device should be unique
        $this->assertTrue($this->validationService->isRegisterAddressUniqueInDevice(1026, $device->id));

        // Same address in different device should be unique
        $otherDevice = Device::factory()->create(['gateway_id' => $gateway->id]);
        $this->assertTrue($this->validationService->isRegisterAddressUniqueInDevice(1025, $otherDevice->id));

        // Excluding the existing register should make it unique
        $this->assertTrue($this->validationService->isRegisterAddressUniqueInDevice(1025, $device->id, $register->id));
    }
}