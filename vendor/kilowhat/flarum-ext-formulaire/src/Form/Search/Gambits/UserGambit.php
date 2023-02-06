<?php

namespace Kilowhat\Formulaire\Form\Search\Gambits;

use Flarum\Search\AbstractRegexGambit;
use Flarum\Search\SearchState;
use Flarum\User\UserRepository;

class UserGambit extends AbstractRegexGambit
{
    protected $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    protected function getGambitPattern(): string
    {
        return 'user:(.+)';
    }

    protected function conditions(SearchState $search, array $matches, $negate)
    {
        $usernames = trim($matches[1], '"');
        $usernames = explode(',', $usernames);

        $ids = [];
        foreach ($usernames as $username) {
            $ids[] = $this->users->getIdForUsername($username);
        }

        $search->getQuery()->whereIn('formulaire_forms.user_id', $ids, 'and', $negate);
    }
}
