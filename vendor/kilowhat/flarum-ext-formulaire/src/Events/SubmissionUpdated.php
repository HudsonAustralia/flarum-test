<?php

namespace Kilowhat\Formulaire\Events;

use Kilowhat\Formulaire\Submission;

/**
 * @deprecated Use Kilowhat\Formulaire\Submission\Event\DataChanged instead
 */
class SubmissionUpdated
{
    public $submission;

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }
}
