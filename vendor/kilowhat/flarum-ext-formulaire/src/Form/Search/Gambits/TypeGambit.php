<?php

namespace Kilowhat\Formulaire\Form\Search\Gambits;

use Flarum\Search\AbstractRegexGambit;
use Flarum\Search\SearchState;
use Flarum\User\UserRepository;

class TypeGambit extends AbstractRegexGambit
{
    protected $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    protected function getGambitPattern(): string
    {
        return 'type:(standalone|discussion|user)';
    }

    protected function conditions(SearchState $search, array $matches, $negate)
    {
        if ($matches[1] === 'standalone') {
            $search->getQuery()->whereNull('formulaire_forms.link_type', 'and', $negate);
        } else {
            $linkType = $matches[1] === 'discussion' ? 'tags' : 'groups';

            $search->getQuery()->where('formulaire_forms.link_type', $negate ? '!=' : '=', $linkType, 'and');
        }
    }
}
