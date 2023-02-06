<?php

namespace Kilowhat\Formulaire\Submission\Scopes;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class Enumerate extends View
{
    public function __invoke(User $actor, Builder $query)
    {
        if (
            $actor->hasPermission('formulaire.moderate') ||
            $actor->hasPermission('formulaire.listSubmissions')
        ) {
            parent::__invoke($actor, $query);
        } else {
            $query->whereRaw('FALSE');
        }
    }
}
