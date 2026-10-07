<?php

namespace SoftTechMX\LaravelMade;

use Illuminate\Support\ServiceProvider;
use SoftTechMX\LaravelMade\Console\Commands\InstallCommand;

class LaravelMadeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LaravelMadeInstallCommand::class,
            ]);
        }
    }
}