<?php

namespace Kilowhat\Formulaire\Providers;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Paths;
use Kilowhat\Formulaire\Repositories\FileUploader;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;

class StorageServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->bind('kilowhat-formulaire-assets', function () {
            /**
             * @var $paths Paths
             */
            $paths = $this->container->make(Paths::class);

            return new Filesystem(new Local($paths->public . '/assets/formulaire'));
        });

        $this->container->when(FileUploader::class)
            ->needs(FilesystemInterface::class)
            ->give('kilowhat-formulaire-assets');
    }
}
