<?php

namespace Kilowhat\Formulaire\User\Event;

use Flarum\User\User;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\Submission;

/**
 * After a user custom field has been modified.
 * This event will be triggered after Kilowhat\Formulaire\Submission\Event\DataChanged
 */
class SubmissionChanged
{
    public $user;
    public $form;
    public $submission;
    public $actor;
    public $oldData;

    public function __construct(User $user, Form $form, Submission $submission, User $actor = null, array $oldData = [])
    {
        $this->user = $user;
        $this->form = $form;
        $this->submission = $submission;
        $this->actor = $actor;
        $this->oldData = $oldData;
    }
}
