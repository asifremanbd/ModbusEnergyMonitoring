<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Rules\ModbusAddressRule;
use App\Rules\ModbusAddressRangeRule;
use App\Rules\PortRangeRule;
use App\Rules\ScaleFactorRule;
use App\Rules\RegisterCountForDataTypeRule;
use App\Rules\UniqueGatewayIpPortRule;
use App\Rules\UniqueDeviceNameInGatewayRule;
use App\Rules\UniqueRegisterAddressInDeviceRule;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function modbus_address_rule_validates_correctly()
    {
        $rule = new ModbusAddressRule();
        
        // Valid addresses
        $this->assertRulePasses($rule, 0);
        $this->assertRulePasses($rule, 1025);
        $this->assertRulePasses($rule, 65535);
        
        // Invalid addresses
        $this->assertRuleFails($rule, -1);
        $this->assertRuleFails($rule, 65536);
        $this->assertRuleFails($rule, 'invalid');
    }

    /** @test */
    public function modbus_address_range_rule_validates_correctly()
    {
        $rule = new ModbusAddressRangeRule(2);
        
        // Valid ranges
        $this->assertRulePasses($rule, 0);
        $this->assertRulePasses($rule, 65533);
        
        // Invalid ranges
        $this->assertRuleFails($rule, 65534);
        $this->assertRuleFails($rule, 65535);
        $this->assertRuleFails($rule, -1);
    }

    /** @test */
    public function port_range_rule_validates_correctly()
    {
        $rule = new PortRangeRule();
        
        // Valid ports
        $this->assertRulePasses($rule, 1);
        $this->assertRulePasses($rule, 502);
        $this->assertRulePasses($rule, 65535);
        
        // Invalid ports
        $this->assertRuleFails($rule, 0);
        $this->assertRuleFails($rule, 65536);
        $this->assertRuleFails($rule, 'invalid');
    }

    /** @test */
    public function scale_factor_rule_validates_correctly()
    {
        $rule = new ScaleFactorRule();
        
        // Valid scale factors
        $this->assertRulePasses($rule, 0.001);
        $this->assertRulePasses($rule, 1.0);
        $this->assertRulePasses($rule, 1000000.0);
        $this->assertRulePasses($rule, null); // Null should be allowed
        
        // Invalid scale factors
        $this->assertRuleFails($rule, 0.0);
        $this->assertRuleFails($rule, -1.0);
        $this->assertRuleFails($rule, 1000001.0);
        $this->assertRuleFails($rule, 'invalid');
    }

    /** @test */
    public function register_count_for_data_type_rule_validates_correctly()
    {
        // Int16 requires 1 register
        $rule = new RegisterCountForDataTypeRule('int16');
        $this->assertRulePasses($rule, 1);
        $this->assertRulePasses($rule, 2);
        $this->assertRuleFails($rule, 0);
        
        // Float32 requires 2 registers
        $rule = new RegisterCountForDataTypeRule('float32');
        $this->assertRulePasses($rule, 2);
        $this->assertRulePasses($rule, 3);
        $this->assertRuleFails($rule, 1);
        
        // Float64 requires 4 registers
        $rule = new RegisterCountForDataTypeRule('float64');
        $this->assertRulePasses($rule, 4);
        $this->assertRuleFails($rule, 3);
        $this->assertRuleFails($rule, 'invalid');
    }

    /** @test */
    public function unique_gateway_ip_port_rule_validates_correctly()
    {
        $gateway = Gateway::factory()->create([
            'ip_address' => '192.168.1.100',
            'port' => 502,
        ]);

        // Same IP/port should fail
        $rule = new UniqueGatewayIpPortRule(null, 502);
        $this->assertRuleFails($rule, '192.168.1.100');

        // Different IP should pass
        $this->assertRulePasses($rule, '192.168.1.101');

        // Same IP/port but excluding the existing gateway should pass
        $rule = new UniqueGatewayIpPortRule($gateway->id, 502);
        $this->assertRulePasses($rule, '192.168.1.100');
    }

    /** @test */
    public function unique_device_name_in_gateway_rule_validates_correctly()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
        ]);

        // Same name in same gateway should fail
        $rule = new UniqueDeviceNameInGatewayRule($gateway->id);
        $this->assertRuleFails($rule, 'Test Device');

        // Different name should pass
        $this->assertRulePasses($rule, 'Different Device');

        // Same name but excluding the existing device should pass
        $rule = new UniqueDeviceNameInGatewayRule($gateway->id, $device->id);
        $this->assertRulePasses($rule, 'Test Device');
    }

    /** @test */
    public function unique_register_address_in_device_rule_validates_correctly()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create([
            'device_id' => $device->id,
            'register_address' => 1025,
        ]);

        // Same address in same device should fail
        $rule = new UniqueRegisterAddressInDeviceRule($device->id);
        $this->assertRuleFails($rule, 1025);

        // Different address should pass
        $this->assertRulePasses($rule, 1026);

        // Same address but excluding the existing register should pass
        $rule = new UniqueRegisterAddressInDeviceRule($device->id, $register->id);
        $this->assertRulePasses($rule, 1025);
    }

    /**
     * Helper method to test if a rule passes.
     */
    protected function assertRulePasses($rule, $value): void
    {
        $failed = false;
        $rule->validate('test', $value, function () use (&$failed) {
            $failed = true;
        });
        
        $this->assertFalse($failed, "Rule should pass for value: {$value}");
    }

    /**
     * Helper method to test if a rule fails.
     */
    protected function assertRuleFails($rule, $value): void
    {
        $failed = false;
        $rule->validate('test', $value, function () use (&$failed) {
            $failed = true;
        });
        
        $this->assertTrue($failed, "Rule should fail for value: {$value}");
    }
}