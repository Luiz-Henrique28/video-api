<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // AQUI nasce o nome 'firebase.auth'
        $this->app->singleton('firebase.auth', function ($app) {

            // 1. Pega o caminho do config que conversamos antes
            $credentials = config('services.firebase.credentials');

            // 2. Cria o objeto "na unha" (igual fizemos no controller)
            return (new Factory)
                ->withServiceAccount($credentials)
                ->createAuth();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
