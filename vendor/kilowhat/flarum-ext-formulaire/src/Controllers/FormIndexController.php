<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Query\QueryCriteria;
use Kilowhat\Formulaire\Form\Search\FormSearcher;
use Kilowhat\Formulaire\Serializers\FormSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class FormIndexController extends AbstractListController
{
    public $serializer = FormSerializer::class;

    public $include = [
        'user',
    ];

    public $sortFields = [
        'createdAt',
    ];

    public $sort = [
        'createdAt' => 'desc',
    ];

    public $limit = 24;

    protected $searcher;
    protected $url;

    public function __construct(FormSearcher $searcher, UrlGenerator $url)
    {
        $this->searcher = $searcher;
        $this->url = $url;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        $filters = $this->extractFilter($request) + ['q' => ''];
        $sort = $this->extractSort($request);

        $criteria = new QueryCriteria($actor, $filters, $sort);

        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);
        $load = $this->extractInclude($request);

        $results = $this->searcher->search($criteria, $limit, $offset);

        $document->addPaginationLinks(
            $this->url->to('api')->route('formulaire.forms.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $results->areMoreResults() ? null : 0
        );

        $this->loadRelations($results->getResults(), $load);

        return $results->getResults();
    }
}
