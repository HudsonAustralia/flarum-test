<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Serializers\FormSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class FormUpdateController extends AbstractShowController
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
        $id = Arr::get($request->getQueryParams(), 'id');

        $actor = RequestUtil::getActor($request);

        $form = $this->repository->findOrFail($id, $actor);

        return $this->repository->update($form, $request->getParsedBody(), $actor);
    }
}
