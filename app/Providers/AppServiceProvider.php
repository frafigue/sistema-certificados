<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ✅ Forzar HTTPS y root URL con el prefijo /certificados
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        URL::forceRootUrl(rtrim(config('app.url'), '/') . '/');

        // ✅ Bootstrap pagination
        Paginator::useBootstrap();

        // ✅ Gates
        Gate::define('is-root', function (User $user) {
            return $user->role->name === 'Root';
        });

        Gate::define('is-admin-or-root', function (User $user) {
            return in_array($user->role->name, ['Administrador', 'Root']);
        });

        Gate::define('is-persona', function (User $user) {
            return $user->role->name === 'Persona';
        });

        // 🔥 RATE LIMIT EMAILS
        RateLimiter::for('emails', function () {
            return Limit::perMinute(30);
        });
    }
}
