<?php

namespace Kilowhat\Formulaire\Form\Scopes;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Kilowhat\Formulaire\Contracts\ScopeVisibilityInterface;

/**
 * This scope controls who can see a particular form using the manager or standalone pages when they know its SEO ID
 * It does not control the enumeration scope, though enumeration extends it
 */
class View implements ScopeVisibilityInterface
{
    public function __invoke(User $actor, Builder $query)
    {
        if ($actor->hasPermission('formulaire.moderate')) {
            return;
        }

        if (!$actor->hasPermission('formulaire.fill')) {
            $query->whereRaw('FALSE');

            return;
        }

        $query->where(function (Builder $query) use ($actor) {
            $query->where(function (Builder $query) use ($actor) {
                $query
                    ->whereNull('formulaire_forms.link_type')
                    ->where(function (Builder $query) use ($actor) {
                        $query->where(function (Builder $query) use ($actor) {
                            $query->whereNull('formulaire_forms.hidden_at');

                            // Users with listForms permission are allowed to see closed forms
                            // This is useful when used together with listSubmissions as you might need the list
                            // of all forms that visible submissions belong to
                            if (!$actor->hasPermission('formulaire.listForms')) {
                                $query->where('accept_submissions', true);
                            }
                        });

                        if ($actor->hasPermission('formulaire.create')) {
                            $query->orWhere('formulaire_forms.user_id', $actor->id);
                        }
                    });
            });

            // Include autolink forms for users with fill permission AND not guest
            if (!$actor->isGuest()) {
                $query->orWhere(function (Builder $query) {
                    // The first where should help with performance since it's indexed
                    // The JSON select is probably a lot slower
                    $query->where('formulaire_forms.link_type', 'tags')
                        ->where('formulaire_forms.automatic_discussion_options->enabled', true);
                });
            }
        });
    }
}
