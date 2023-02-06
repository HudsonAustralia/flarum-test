<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\FileRepository;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Serializers\FileSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class FileUploadController extends AbstractListController
{
    public $serializer = FileSerializer::class;

    protected $forms;
    protected $files;

    public function __construct(FormRepository $forms, FileRepository $files)
    {
        $this->forms = $forms;
        $this->files = $files;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $formId = Arr::get($request->getQueryParams(), 'id');

        $form = $this->forms->findOrFailWithoutScope($formId);

        // 10% percent change of running the clean method
        if (mt_rand(1, 100) <= 10) {
            $this->files->cleanUnused();
        }

        return $this->files->upload($form, $request->getUploadedFiles(), RequestUtil::getActor($request));
    }
}
