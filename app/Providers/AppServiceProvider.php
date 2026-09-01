<?php

namespace App\Providers;

use App\Database\PostgresConnectorWithOptions;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Let the pgsql connection pass a libpq "options" string (DB_OPTIONS)
        // through to the DSN. Needed for Neon when libpq is too old for SNI.
        $this->app->bind('db.connector.pgsql', fn () => new PostgresConnectorWithOptions);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
