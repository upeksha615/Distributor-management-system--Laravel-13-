<?php

namespace App\Providers;

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
        \App\Support\Settings::apply();
        \App\Support\MailSettings::apply();

        // Every ability is a permission key from App\Support\Permissions: @can('payments.verify'), ->middleware('can:...').
        \Illuminate\Support\Facades\Gate::before(fn (\App\Models\User $user, string $ability) => $user->is_active && $user->hasPermission($ability) ? true : null);
        \Illuminate\Pagination\Paginator::useTailwind();
    }
}
