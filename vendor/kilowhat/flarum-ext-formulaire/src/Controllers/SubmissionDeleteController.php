<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\SubmissionRepository;
use Psr\Http\Message\ServerRequestInterface;

class SubmissionDeleteController extends AbstractDeleteController
{
    protected $repository;

    public function __construct(SubmissionRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function delete(ServerRequestInterface $request)
    {
        $id = Arr::get($request->getQueryParams(), 'id');

        $submission = $this->repository->findOrFailWithoutScope($id);

        $this->repository->delete($submission, RequestUtil::getActor($request));
    }
}
