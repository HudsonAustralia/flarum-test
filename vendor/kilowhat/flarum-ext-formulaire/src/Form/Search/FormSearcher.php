<?php

namespace Kilowhat\Formulaire\Form\Search;

use Flarum\Search\AbstractSearcher;
use Flarum\Search\GambitManager;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Kilowhat\Formulaire\Repositories\FormRepository;

class FormSearcher extends AbstractSearcher
{
    protected $repository;

    public function __construct(GambitManager $gambits, array $searchMutators, FormRepository $repository)
    {
        parent::__construct($gambits, $searchMutators);

        $this->repository = $repository;
    }

    protected function getQuery(User $actor): Builder
    {
        return $this->repository->scopeVisibleTo($actor, 'viewEnumerate')->select('formulaire_forms.*');
    }
}
