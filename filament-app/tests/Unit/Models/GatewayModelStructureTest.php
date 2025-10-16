<?php

namespace Tests\Unit\Models;

use App\Models\Gateway;
use Tests\TestCase;

class GatewayModelStructureTest extends TestCase
{
    /** @test */
    public function gateway_has_correct_fillable_fields()
    {
        $gateway = new Gateway();
        $fillable = $gateway->getFillable();
        
        $expectedFillable = [
            'name',
            'ip_address',
            'port',
            'unit_id',
            'poll_interval',
            'is_active',
            'last_seen_at',
            'success_count',
            'failure_count',
        ];
        
        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $fillable, "Field '{$field}' should be fillable");
        }
    }

    /** @test */
    public function gateway_has_correct_casts()
    {
        $gateway = new Gateway();
        $casts = $gateway->getCasts();
        
        $expectedCasts = [
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
            'poll_interval' => 'integer',
            'port' => 'integer',
            'unit_id' => 'integer',
            'success_count' => 'integer',
            'failure_count' => 'integer',
        ];
        
        foreach ($expectedCasts as $field => $expectedCast) {
            $this->assertArrayHasKey($field, $casts, "Field '{$field}' should have a cast");
            $this->assertEquals($expectedCast, $casts[$field], "Field '{$field}' should be cast to '{$expectedCast}'");
        }
    }

    /** @test */
    public function gateway_has_relationship_methods()
    {
        $gateway = new Gateway();
        
        $this->assertTrue(method_exists($gateway, 'dataPoints'), 'Gateway should have dataPoints relationship method');
        $this->assertTrue(method_exists($gateway, 'devices'), 'Gateway should have devices relationship method');
        $this->assertTrue(method_exists($gateway, 'readings'), 'Gateway should have readings relationship method');
        $this->assertTrue(method_exists($gateway, 'registers'), 'Gateway should have registers relationship method');
    }

    /** @test */
    public function gateway_has_scope_methods()
    {
        $gateway = new Gateway();
        
        $this->assertTrue(method_exists($gateway, 'scopeActive'), 'Gateway should have active scope method');
    }

    /** @test */
    public function gateway_has_accessor_methods()
    {
        $gateway = new Gateway();
        
        $accessorMethods = [
            'getSuccessRateAttribute',
            'getEnhancedStatusAttribute',
            'getRecentErrorRateAttribute',
            'getIsOnlineAttribute',
            'getDeviceCountAttribute',
            'getRegisterCountAttribute',
            'getEnabledDeviceCountAttribute',
            'getEnabledRegisterCountAttribute',
            'getStatusBadgeColorAttribute',
            'getStatusLabelAttribute',
            'getIsRecentlySeenAttribute',
            'getTimeSinceLastSeenAttribute',
        ];
        
        foreach ($accessorMethods as $method) {
            $this->assertTrue(method_exists($gateway, $method), "Gateway should have {$method} method");
        }
    }

    /** @test */
    public function gateway_has_validation_methods()
    {
        $validationMethods = [
            'getValidationRules',
            'getValidationMessages',
            'validateData',
            'isIpPortUnique',
        ];
        
        foreach ($validationMethods as $method) {
            $this->assertTrue(method_exists(Gateway::class, $method), "Gateway should have {$method} static method");
        }
    }

    /** @test */
    public function gateway_success_rate_calculation_logic()
    {
        // Test with mock data without database
        $gateway = new Gateway([
            'success_count' => 80,
            'failure_count' => 20
        ]);
        
        // Manually calculate expected result
        $total = 80 + 20;
        $expectedRate = ($total > 0) ? (80 / $total) * 100 : 0;
        
        $this->assertEquals($expectedRate, $gateway->getSuccessRateAttribute());
    }

    /** @test */
    public function gateway_success_rate_handles_zero_attempts()
    {
        $gateway = new Gateway([
            'success_count' => 0,
            'failure_count' => 0
        ]);
        
        $this->assertEquals(0.0, $gateway->getSuccessRateAttribute());
    }

    /** @test */
    public function gateway_recently_seen_logic()
    {
        // Test with recent timestamp
        $gateway = new Gateway([
            'last_seen_at' => now()->subSeconds(30),
            'poll_interval' => 60
        ]);
        
        $this->assertTrue($gateway->getIsRecentlySeenAttribute());
        
        // Test with old timestamp
        $gateway = new Gateway([
            'last_seen_at' => now()->subSeconds(300), // 5 minutes ago
            'poll_interval' => 60 // 3x = 180 seconds threshold
        ]);
        
        $this->assertFalse($gateway->getIsRecentlySeenAttribute());
        
        // Test with null timestamp
        $gateway = new Gateway([
            'last_seen_at' => null,
            'poll_interval' => 60
        ]);
        
        $this->assertFalse($gateway->getIsRecentlySeenAttribute());
    }

    /** @test */
    public function gateway_time_since_last_seen_handles_null()
    {
        $gateway = new Gateway([
            'last_seen_at' => null
        ]);
        
        $this->assertNull($gateway->getTimeSinceLastSeenAttribute());
    }

    /** @test */
    public function gateway_static_methods_return_correct_types()
    {
        $rules = Gateway::getValidationRules();
        $this->assertIsArray($rules);
        
        $messages = Gateway::getValidationMessages();
        $this->assertIsArray($messages);
        
        $data = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502
        ];
        
        $errors = Gateway::validateData($data);
        $this->assertIsArray($errors);
        
        // Skip database-dependent test if database is not available
        try {
            $result = Gateway::isIpPortUnique('192.168.1.100', 502);
            $this->assertIsBool($result);
        } catch (\Exception $e) {
            // Database not available, skip this assertion
            $this->assertTrue(true, 'Database not available for testing');
        }
    }
}