<?php

namespace Kilowhat\Formulaire\Submission\Event;

use Flarum\User\User;
use Kilowhat\Formulaire\Submission;

/**
 * After the data for a submission for any form type has been modified.
 */
class DataChanged
{
    public $submission;
    public $actor;
    public $oldData;

    public function __construct(Submission $submission, User $actor = null, array $oldData = [])
    {
        $this->submission = $submission;
        $this->actor = $actor;
        $this->oldData = $oldData;
    }
}
