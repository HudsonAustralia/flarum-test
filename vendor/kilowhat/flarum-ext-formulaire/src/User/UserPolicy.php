<?php

namespace Kilowhat\Formulaire\User;

use Flarum\Extension\ExtensionManager;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class UserPolicy extends AbstractPolicy
{
    public function fillFormulaire(User $actor, User $user)
    {
        /**
         * @var $manager ExtensionManager
         */
        $manager = resolve(ExtensionManager::class);

        if (
            $manager->isEnabled('flarum-suspend') &&
            $user->suspended_until &&
            !$actor->hasPermission('formulaire.moderate')
        ) {
            return $this->deny();
        }

        return $this->allow();
    }
}
