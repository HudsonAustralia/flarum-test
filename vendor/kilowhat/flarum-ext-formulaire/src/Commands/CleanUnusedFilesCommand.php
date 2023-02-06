<?php

namespace Kilowhat\Formulaire\Commands;

use Flarum\Console\AbstractCommand;
use Kilowhat\Formulaire\Repositories\FileRepository;

class CleanUnusedFilesCommand extends AbstractCommand
{
    protected $files;

    public function __construct(FileRepository $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    protected function configure()
    {
        $this
            ->setName('formulaire:clean')
            ->setDescription('Remove unused files from disk');
    }

    protected function fire()
    {
        $total = $this->files->cleanUnused();

        $this->info("Removed $total files");
    }
}
