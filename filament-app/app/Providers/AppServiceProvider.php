<?php

namespace App\Providers;

use App\Models\Gateway;
use App\Observers\GatewayObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gateway::observe(GatewayObserver::class);
    }
}
