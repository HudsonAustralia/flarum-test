<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\SubmissionRepository;
use Kilowhat\Formulaire\Serializers\SubmissionSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class SubmissionUpdateController extends AbstractShowController
{
    public $serializer = SubmissionSerializer::class;

    public $include = [
        'user',
    ];

    protected $repository;

    public function __construct(SubmissionRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $id = Arr::get($request->getQueryParams(), 'id');

        $actor = RequestUtil::getActor($request);

        $submission = $this->repository->findStandaloneOrFail($id, $actor);

        $attributes = (array)Arr::get($request->getParsedBody(), 'data.attributes');

        return $this->repository->updateStandalone($submission, $attributes, $actor);
    }
}
