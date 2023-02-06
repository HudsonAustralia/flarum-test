<?php

namespace Kilowhat\Formulaire\Scout;

use Kilowhat\Formulaire\Submission;

class ScoutSubmissionAttributes
{
    use FormulaireScoutTrait;

    public function __invoke(Submission $submission): array
    {
        return [
            'uid' => $submission->uid,
            // TODO: currently no event listener refreshes the search index when a user is deleted
            'user' => optional($submission->user)->display_name,
            'data' => $this->formatSubmission($submission->data, $submission->form->template),
        ];
    }
}
