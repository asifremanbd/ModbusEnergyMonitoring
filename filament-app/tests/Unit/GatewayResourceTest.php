<?php

namespace Tests\Unit;

use App\Filament\Resources\GatewayResource;
use App\Models\Gateway;
use Tests\TestCase;

class GatewayResourceTest extends TestCase
{
    /** @test */
    public function gateway_resource_has_correct_model()
    {
        $this->assertEquals(Gateway::class, GatewayResource::getModel());
    }

    /** @test */
    public function gateway_resource_has_correct_navigation_properties()
    {
        $this->assertEquals('heroicon-o-server', GatewayResource::getNavigationIcon());
        $this->assertEquals('Gateways', GatewayResource::getNavigationLabel());
        $this->assertEquals('Gateway', GatewayResource::getModelLabel());
        $this->assertEquals('Gateways', GatewayResource::getPluralModelLabel());
    }

    /** @test */
    public function gateway_resource_has_required_pages()
    {
        $pages = GatewayResource::getPages();
        
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    /** @test */
    public function gateway_resource_classes_exist()
    {
        $this->assertTrue(class_exists(GatewayResource::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\ListGateways::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\CreateGateway::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\ViewGateway::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\EditGateway::class));
    }

    /** @test */
    public function gateway_resource_extends_correct_base_class()
    {
        $this->assertInstanceOf(\Filament\Resources\Resource::class, new class extends GatewayResource {});
    }
}