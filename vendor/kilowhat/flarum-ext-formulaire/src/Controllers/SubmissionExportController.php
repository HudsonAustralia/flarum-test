<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\ExportRepository;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SubmissionExportController implements RequestHandlerInterface
{
    protected $repository;
    protected $exporter;

    public function __construct(FormRepository $repository, ExportRepository $exporter)
    {
        $this->repository = $repository;
        $this->exporter = $exporter;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = Arr::get($request->getQueryParams(), 'id');

        $actor = RequestUtil::getActor($request);

        $form = $this->repository->findOrFail($id, $actor);

        $actor->assertCan('export', $form);

        return $this->exporter->export($form, $request->getQueryParams(), $actor);
    }
}
