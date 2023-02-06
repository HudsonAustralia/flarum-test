<?php

namespace Kilowhat\Formulaire\Form\Scopes;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * This scope controls who can see the list of forms
 * It extends on the base View scope
 */
class Enumerate extends View
{
    public function __invoke(User $actor, Builder $query)
    {
        parent::__invoke($actor, $query);

        if (
            !$actor->hasPermission('formulaire.moderate') &&
            !$actor->hasPermission('formulaire.listForms') &&
            $actor->id // We dont want to compare against null/zero. If it's a guest, there should already be a WHERE FALSE on the query
        ) {
            // If you're not an admin and don't have the special listForms permissions,
            // then you can only enumerate forms you created yourself
            // The actual permission check is in find(), and this one further filters out non-yours forms in listing
            $query->where('formulaire_forms.user_id', $actor->id);
        }
    }
}
