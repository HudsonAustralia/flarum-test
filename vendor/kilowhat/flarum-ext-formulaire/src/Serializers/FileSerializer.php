<?php

namespace Kilowhat\Formulaire\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use Kilowhat\Formulaire\File;

class FileSerializer extends AbstractSerializer
{
    protected $type = 'formulaire-files';

    /**
     * @param File $file
     * @return string
     */
    public function getId($file): string
    {
        return $file->uid;
    }

    /**
     * @param File $file
     * @return array
     */
    protected function getDefaultAttributes($file): array
    {
        return [
            'filename' => $file->filename,
            'humanSize' => $file->humanSize(),
            'url' => $file->url(),
        ];
    }
}
