<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PharUpdater::class, fn () => new PharUpdater(
            githubRepo: 'jeffersongoncalves/laravel-zero-cli',
            assetName: 'laravel-zero-cli.phar',
            tempPrefix: 'laravel_zero_cli_',
            currentVersion: (string) config('app.version', 'unreleased'),
        ));
    }
}
