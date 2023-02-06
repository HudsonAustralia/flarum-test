<?php

namespace Kilowhat\Formulaire\Form\Scopes;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Kilowhat\Formulaire\Contracts\ScopeVisibilityInterface;

/**
 * This scope controls who can see a particular linked form
 */
class ViewLinked implements ScopeVisibilityInterface
{
    public function __invoke(User $actor, Builder $query)
    {
        if (!$actor->hasPermission('formulaire.moderate')) {
            $query->where('accept_submissions', true)
                ->whereNull('hidden_at');
        }
    }
}
