<?php

namespace Hemant\Pagewire\Providers;

use Hemant\Pagewire\Livewire\Admin\Page\Builder;
use Hemant\Pagewire\Livewire\Admin\Page\Index;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class PagewireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/pagewire.php', 'pagewire');
    }

    public function boot(): void
    {
        // Routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/pagewire.php');

        // Views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'pagewire');
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/pagewire'),
        ], 'pagewire-views');

        // Migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'pagewire-migrations');

        // Config
        $this->publishes([
            __DIR__.'/../../config/pagewire.php' => config_path('pagewire.php'),
        ], 'pagewire-config');

        // Livewire components
        Livewire::component('pagewire.admin.page.index', Index::class);
        Livewire::component('pagewire.admin.page.builder', Builder::class);
    }
}
