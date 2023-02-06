<?php

namespace Kilowhat\Formulaire\Providers;

use Flarum\Foundation\AbstractServiceProvider;
use Maatwebsite\Excel\Cache\CacheManager;
use Maatwebsite\Excel\SettingsProvider;

/**
 * Based on the original service provider for Laravel, adapted for Flarum
 * With everything not used removed
 */
class ExcelServiceProvider extends AbstractServiceProvider
{
    public function boot()
    {
        $this->container->make(SettingsProvider::class)->provide();
    }

    public function register()
    {
        $this->container->bind(CacheManager::class, function () {
            return new CacheManager($this->container);
        });
    }
}
