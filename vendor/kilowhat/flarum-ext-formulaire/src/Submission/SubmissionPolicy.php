<?php

namespace Kilowhat\Formulaire\Submission;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use Kilowhat\Formulaire\Submission;

class SubmissionPolicy extends AbstractPolicy
{
    public function editStandalone(User $actor, Submission $submission)
    {
        if (!$submission->form->isStandalone()) {
            return $this->deny();
        }

        if ($actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }

        if (
            $actor->id === $submission->user_id &&
            !$submission->locked_at &&
            !$submission->hidden_at &&
            !$submission->form->hidden_at &&
            $submission->form->allow_modification
        ) {
            return $this->allow();
        };
    }

    public function lock(User $actor, Submission $submission)
    {
        if (!$submission->form->isStandalone()) {
            return $this->deny();
        }

        if ($actor->can('edit', $submission->form) || $actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }
    }

    public function hide(User $actor, Submission $submission)
    {
        if (!$submission->form->isStandalone()) {
            return $this->deny();
        }

        if ($actor->can('edit', $submission->form) || $actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }
    }

    public function restore(User $actor, Submission $submission)
    {
        return $this->hide($actor, $submission);
    }

    public function delete(User $actor, Submission $submission)
    {
        if ($actor->hasPermission('formulaire.moderate')) {
            return $this->allow();
        }
    }
}
