<?php

namespace Kilowhat\Formulaire\Controllers;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Query\QueryCriteria;
use Kilowhat\Formulaire\Serializers\SubmissionSerializer;
use Kilowhat\Formulaire\Submission\Search\SubmissionSearcher;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class SubmissionIndexController extends AbstractListController
{
    public $serializer = SubmissionSerializer::class;

    public $include = [
        'user',
        'form',
    ];

    public $optionalInclude = [
        'files',
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

    public function __construct(SubmissionSearcher $searcher, UrlGenerator $url)
    {
        $this->searcher = $searcher;
        $this->url = $url;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Document $document
     * @return mixed
     * @throws \Tobscure\JsonApi\Exception\InvalidParameterException
     */
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
            $this->url->to('api')->route('formulaire.submissions.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $results->areMoreResults() ? null : 0
        );

        $this->loadRelations($results->getResults(), $load);

        return $results->getResults();
    }
}
