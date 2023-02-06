<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Psr\Http\Message\ServerRequestInterface;

class FormDeleteController extends AbstractDeleteController
{
    protected $repository;

    public function __construct(FormRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function delete(ServerRequestInterface $request)
    {
        $id = Arr::get($request->getQueryParams(), 'id');

        $actor = RequestUtil::getActor($request);

        $form = $this->repository->findOrFail($id, $actor);

        $this->repository->delete($form, $actor);
    }
}
