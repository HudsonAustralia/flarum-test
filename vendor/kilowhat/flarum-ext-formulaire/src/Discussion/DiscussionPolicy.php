<?php

namespace Kilowhat\Formulaire\Discussion;

use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class DiscussionPolicy extends AbstractPolicy
{
    /**
     * This check is in addition to the global/local Formulaire checks
     * Returning true simply allows the process to go to the next check
     * @param User $actor
     * @param Discussion $discussion
     * @return bool
     */
    public function fillFormulaire(User $actor, Discussion $discussion)
    {
        // If it's a new discussion in the process of being created, we want to skip the permission check
        if ($discussion->first_post_id === null) {
            return $this->allow();
        }

        // For most visitors, this is already handled by the visibility scope of the discussion
        // But we want to restrict further and prevent editing the form on a soft-deleted discussion that the user can still see
        if (
            $discussion->hidden_at &&
            $actor->cannot('hide', $discussion) &&
            !$actor->hasPermission('formulaire.moderate')
        ) {
            return $this->deny();
        }

        /**
         * @var $manager ExtensionManager
         */
        $manager = resolve(ExtensionManager::class);

        if (
            $manager->isEnabled('flarum-lock') &&
            $discussion->is_locked &&
            $actor->cannot('lock', $discussion) &&
            !$actor->hasPermission('formulaire.moderate')
        ) {
            // Here we could instead check for the "reply" permission, but it's a bit restrictive
            // because you might want to use Formulaire together with discussions where you can't reply
            return $this->deny();
        }

        return $this->allow();
    }
}
