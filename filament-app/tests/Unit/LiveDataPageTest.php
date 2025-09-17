<?php

namespace Tests\Unit;

use App\Filament\Pages\LiveData;
use Tests\TestCase;

class LiveDataPageTest extends TestCase
{
    /** @test */
    public function it_has_correct_navigation_properties()
    {
        $page = new LiveData();
        
        $this->assertEquals('heroicon-o-chart-bar', LiveData::getNavigationIcon());
        $this->assertEquals('Live Data', LiveData::getNavigationLabel());
        $this->assertEquals(3, LiveData::getNavigationSort());
        $this->assertEquals('live-data', LiveData::getSlug());
    }

    /** @test */
    public function it_has_correct_page_properties()
    {
        $page = new LiveData();
        
        $this->assertEquals('Live Data Readings', $page->getTitle());
        $this->assertEquals('Live Data Readings', $page->getHeading());
    }

    /** @test */
    public function it_uses_correct_view()
    {
        $page = new LiveData();
        $this->assertEquals('filament.pages.live-data', $page->getView());
    }
}