<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\SubmissionRepository;
use Kilowhat\Formulaire\Serializers\SubmissionSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class SubmissionShowController extends AbstractShowController
{
    public $serializer = SubmissionSerializer::class;

    public $include = [
        'user',
        'files',
        'form',
    ];

    protected $repository;

    public function __construct(SubmissionRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $id = Arr::get($request->getQueryParams(), 'id');

        return $this->repository->findStandaloneOrFail($id, RequestUtil::getActor($request));
    }
}
