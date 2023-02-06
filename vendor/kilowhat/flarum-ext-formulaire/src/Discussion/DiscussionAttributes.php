<?php

namespace Kilowhat\Formulaire\Discussion;

use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Discussion\Discussion;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Submission;

class DiscussionAttributes
{
    protected $repository;

    public function __construct(FormRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(DiscussionSerializer $serializer, Discussion $discussion): array
    {
        $forms = $this->repository->forDiscussion($discussion, $serializer->getActor());

        $submissions = Submission::query()
            ->whereIn('form_id', $forms->pluck('id'))
            ->where('link_type', 'discussions')
            ->where('link_id', $discussion->id)
            ->get();

        $discussion->setRelation('formulaireForms', $forms);
        $discussion->setRelation('formulaireSubmissions', $submissions);

        return [];
    }
}
