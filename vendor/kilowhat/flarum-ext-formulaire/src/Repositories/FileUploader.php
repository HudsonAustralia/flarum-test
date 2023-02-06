<?php

namespace Kilowhat\Formulaire\Repositories;

use Illuminate\Support\Str;
use Kilowhat\Formulaire\File;
use League\Flysystem\FilesystemInterface;

class FileUploader
{
    protected $assets;

    public function __construct(FilesystemInterface $assets)
    {
        $this->assets = $assets;
    }

    public function upload(File $file, string $tmpFile)
    {
        $file->path = date('Ymd') . '-' . Str::random(16);

        $this->assets->write($file->path . '/' . $file->filename, file_get_contents($tmpFile));
    }

    public function remove(File $file)
    {
        if ($this->assets->has($file->path . '/' . $file->filename)) {
            $this->assets->delete($file->path . '/' . $file->filename);
        }

        if ($file->path && $this->assets->has($file->path)) {
            $this->assets->deleteDir($file->path);
        }

        // No need to set filename to null as the database record will be deleted as well just after this
    }
}
