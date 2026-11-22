<?php

namespace App\Providers;

use App\Services\Notifications\WhatsApp\MessagingConfigRepository;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Remembers which .env values it overrode with saved provider settings (§13.6).
        $this->app->singleton(MessagingConfigRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
