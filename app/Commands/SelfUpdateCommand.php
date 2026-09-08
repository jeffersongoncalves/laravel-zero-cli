<?php

namespace App\Commands;

use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;
use JeffersonGoncalves\LaravelZero\SelfUpdate\SelfUpdateCommand as BaseSelfUpdateCommand;

class SelfUpdateCommand extends BaseSelfUpdateCommand
{
    protected $description = 'Update the laravel-zero-cli CLI to the latest version';

    protected function githubRepo(): string
    {
        return 'jeffersongoncalves/laravel-zero-cli';
    }

    protected function assetName(): string
    {
        return 'laravel-zero-cli.phar';
    }

    protected function tempPrefix(): string
    {
        return 'laravel_zero_cli_';
    }

    protected function currentVersion(): string
    {
        return (string) config('app.version', 'unreleased');
    }

    protected function makeUpdater(): PharUpdater
    {
        return $this->getLaravel()->make(PharUpdater::class);
    }
}
