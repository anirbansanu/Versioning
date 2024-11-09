<?php

namespace AniV\Versioning;

use Illuminate\Support\ServiceProvider;

class VersioningServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish migrations, config files, or anything the package needs
        if ($this->app->runningInConsole()) {
            // Use config path if it exists; otherwise, provide a fallback path
            $this->publishes([
                __DIR__ . '/../config/versioning.php' => config_path('versioning.php'),
            ], 'config');
        }
    }

    public function register()
    {
        // Register the config file
        $this->mergeConfigFrom(__DIR__.'/../config/versioning.php', 'versioning');
    }
}
