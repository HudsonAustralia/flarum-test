<?php

namespace Kilowhat\Formulaire\Form;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Form;

class FormPolicy extends AbstractPolicy
{
    public function createStandaloneSubmission(User $actor, Form $form)
    {
        if (!$form->isStandalone()) {
            return $this->deny();
        }

        if ($actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }

        if (
            $actor->hasPermission('formulaire.fill') &&
            $form->accept_submissions &&
            !$form->hidden_at &&
            (!$form->max_submissions || $form->submission_count < $form->max_submissions)
        ) {
            return $this->allow();
        }
    }

    /**
     * The permission to use linked forms as a standalone form
     * @param User $actor
     * @param Form $form
     * @return bool
     */
    public function createAutoLinkSubmission(User $actor, Form $form)
    {
        if (!$form->isAutoLink()) {
            return $this->deny();
        }

        if ($actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }

        // For now we use the same permission as standalone forms for automatic discussions
        // But we limit to registered users for 2 reasons:
        // Technical: Discussion::start() can't handle guests
        // Practical: Discussions/posts made by guests are a whole level of complexity in themselves
        if ($actor->isGuest()) {
            return $this->deny();
        }

        // Mostly same logic as standalone
        if (
            $actor->hasPermission('formulaire.fill') &&
            $form->accept_submissions &&
            !$form->hidden_at &&
            (!$form->max_submissions || $form->submission_count < $form->max_submissions)
        ) {
            return $this->allow();
        }
    }

    public function create(User $actor)
    {
        if ($actor->hasPermission('formulaire.create') || $actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }
    }

    public function viewStandalone(User $actor, Form $form)
    {
        if ($actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }

        if ($form->hidden_at) {
            return $this->deny();
        }

        // Both "real" standalone forms and automatic discussion forms can be accessed standalone
        if ($form->link_type && !Arr::get($form->automatic_discussion_options, 'enabled')) {
            return $this->deny();
        }

        if ($actor->hasPermission('formulaire.fill')) {
            return $this->allow();
        }
    }

    public function edit(User $actor, Form $form)
    {
        if ($actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }

        if ($actor->id === $form->user_id && $actor->hasPermission('formulaire.create')) {
            return $this->allow();
        }
    }

    public function hide(User $actor, Form $form)
    {
        return $this->edit($actor, $form);
    }

    public function delete(User $actor, Form $form)
    {
        return $this->edit($actor, $form);
    }

    public function uploadFile(User $actor, Form $form)
    {
        if ($actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }

        if ($form->hidden_at) {
            return $this->deny();
        }

        if ($form->isStandalone()) {
            return $actor->hasPermission('formulaire.fill') ? $this->allow() : $this->deny();
        }

        // TODO: temporary permission for linked fields
        // Doing this leaks whether a file field exists and what type of files it accepts because of the validation
        // It also technically allows spamming files, but those will be deleted after a while since the user can't link them to a submission
        return $this->allow();
    }

    public function export(User $actor, Form $form)
    {
        if ($actor->hasPermission('formulaire.export')) {
            return $this->allow();
        }
    }

    public function exportUserDetails(User $actor, Form $form)
    {
        if ($actor->hasPermission('formulaire.exportUserDetails')) {
            return $this->allow();
        }
    }
}
