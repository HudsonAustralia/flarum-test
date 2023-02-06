<?php

namespace Kilowhat\Formulaire\Submission\Event;

use Flarum\User\User;
use Kilowhat\Formulaire\Submission;

/**
 * After a submission for any form type has been successfully created.
 */
class Created
{
    public $submission;
    public $actor;

    public function __construct(Submission $submission, User $actor = null)
    {
        $this->submission = $submission;
        $this->actor = $actor;
    }
}
