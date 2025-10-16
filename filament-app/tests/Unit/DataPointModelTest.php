<?php

namespace Tests\Unit;

use App\Models\DataPoint;
use Tests\TestCase;

class DataPointModelTest extends TestCase
{
    /** @test */
    public function data_point_model_has_new_fillable_fields()
    {
        $dataPoint = new DataPoint();
        $fillable = $dataPoint->getFillable();
        
        $this->assertContains('device_type', $fillable);
        $this->assertContains('load_category', $fillable);
        $this->assertContains('custom_label', $fillable);
        $this->assertContains('write_function', $fillable);
        $this->assertContains('write_register', $fillable);
        $this->assertContains('on_value', $fillable);
        $this->assertContains('off_value', $fillable);
        $this->assertContains('invert', $fillable);
        $this->assertContains('is_schedulable', $fillable);
    }

    /** @test */
    public function data_point_model_has_correct_casts()
    {
        $dataPoint = new DataPoint();
        $casts = $dataPoint->getCasts();
        
        $this->assertArrayHasKey('write_function', $casts);
        $this->assertArrayHasKey('write_register', $casts);
        $this->assertArrayHasKey('invert', $casts);
        $this->assertArrayHasKey('is_schedulable', $casts);
        
        $this->assertEquals('integer', $casts['write_function']);
        $this->assertEquals('integer', $casts['write_register']);
        $this->assertEquals('boolean', $casts['invert']);
        $this->assertEquals('boolean', $casts['is_schedulable']);
    }
}