<?php

namespace Kilowhat\Formulaire\Form\Search\Gambits;

use ClarkWinkelmann\Scout\ScoutStatic;
use Flarum\Extension\ExtensionManager;
use Flarum\Search\GambitInterface;
use Flarum\Search\SearchState;
use Kilowhat\Formulaire\Form;

class FullTextGambit implements GambitInterface
{
    public function apply(SearchState $search, $searchValue)
    {
        if (empty($searchValue)) {
            return;
        }

        if (!resolve(ExtensionManager::class)->isEnabled('clarkwinkelmann-scout')) {
            $search->getQuery()->where('title', 'like', '%' . $searchValue . '%');

            return;
        }

        $builder = ScoutStatic::makeBuilder(Form::class, $searchValue);

        $ids = $builder->keys();

        $search->getQuery()->whereIn('id', $ids);

        $search->setDefaultSort(function ($query) use ($ids) {
            if (!count($ids)) {
                return;
            }

            $query->orderByRaw('FIELD(id' . str_repeat(', ?', count($ids)) . ')', $ids);
        });
    }
}
