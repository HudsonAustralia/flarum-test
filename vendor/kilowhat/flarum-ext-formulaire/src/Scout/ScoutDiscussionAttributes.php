<?php

namespace Kilowhat\Formulaire\Scout;

use Flarum\Discussion\Discussion;
use Flarum\User\Guest;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Submission;

class ScoutDiscussionAttributes
{
    use FormulaireScoutTrait;

    protected $repository;

    public function __construct(FormRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Discussion $discussion): array
    {
        $forms = $this->repository->forDiscussion($discussion, new Guest());

        $submissions = Submission::query()
            ->whereIn('form_id', $forms->pluck('id'))
            ->where('link_type', 'discussions')
            ->where('link_id', $discussion->id)
            ->get();

        return $this->linkedModel($forms, $submissions);
    }
}
