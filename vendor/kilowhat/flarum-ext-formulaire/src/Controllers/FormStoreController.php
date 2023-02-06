<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Serializers\FormSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class FormStoreController extends AbstractCreateController
{
    public $serializer = FormSerializer::class;

    public $include = [
        'user',
    ];

    protected $repository;

    public function __construct(FormRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        return $this->repository->store((array)$request->getParsedBody(), RequestUtil::getActor($request));
    }
}
