<?php

namespace Kilowhat\Formulaire\Contracts;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Used to type-check all our scope visibility callbacks since Flarum doesn't provide an interface for it
 */
interface ScopeVisibilityInterface
{
    public function __invoke(User $actor, Builder $query);
}
