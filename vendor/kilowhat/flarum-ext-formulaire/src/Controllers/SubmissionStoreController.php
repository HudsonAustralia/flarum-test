<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Repositories\SubmissionRepository;
use Kilowhat\Formulaire\Serializers\SubmissionSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class SubmissionStoreController extends AbstractCreateController
{
    public $serializer = SubmissionSerializer::class;

    public $include = [
        'user',
    ];

    protected $forms;
    protected $submissions;

    public function __construct(FormRepository $forms, SubmissionRepository $submissions)
    {
        $this->forms = $forms;
        $this->submissions = $submissions;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $id = Arr::get($request->getQueryParams(), 'id');

        $actor = RequestUtil::getActor($request);

        $form = $this->forms->findOrFail($id, $actor);

        $attributes = (array)Arr::get($request->getParsedBody(), 'data.attributes');

        return $this->submissions->storeStandalone($form, $attributes, $actor);
    }
}
