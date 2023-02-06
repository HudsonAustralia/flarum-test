<?php

namespace Kilowhat\Formulaire\Discussion\Event;

use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\Submission;

/**
 * After a discussion custom field has been modified.
 * This event will be triggered after Kilowhat\Formulaire\Submission\Event\DataChanged
 */
class SubmissionChanged
{
    public $discussion;
    public $form;
    public $submission;
    public $actor;
    public $oldData;

    public function __construct(Discussion $discussion, Form $form, Submission $submission, User $actor = null, array $oldData = [])
    {
        $this->discussion = $discussion;
        $this->form = $form;
        $this->submission = $submission;
        $this->actor = $actor;
        $this->oldData = $oldData;
    }
}
