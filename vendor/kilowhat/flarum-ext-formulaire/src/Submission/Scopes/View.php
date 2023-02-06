<?php

namespace Kilowhat\Formulaire\Submission\Scopes;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Kilowhat\Formulaire\Contracts\ScopeVisibilityInterface;

class View implements ScopeVisibilityInterface
{
    public function __invoke(User $actor, Builder $query)
    {
        if ($actor->hasPermission('formulaire.moderate')) {
            return;
        }

        // TODO: allow form owner to see all submissions to their forms
        if ($actor->isGuest()) {
            $query->whereRaw('FALSE');
        } else {
            $query
                ->where('user_id', $actor->id)
                ->whereNull('hidden_at')
                ->whereHas('form', function (Builder $query) {
                    $query
                        ->whereNull('link_type')
                        ->whereNull('hidden_at');
                });
        }
    }
}
