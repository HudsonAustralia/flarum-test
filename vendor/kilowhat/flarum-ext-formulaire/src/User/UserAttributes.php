<?php

namespace Kilowhat\Formulaire\User;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Submission;

class UserAttributes
{
    protected $repository;

    public function __construct(FormRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(UserSerializer $serializer, User $user): array
    {
        $forms = $this->repository->forUserProfile($user, $serializer->getActor());

        $submissions = Submission::query()
            ->whereIn('form_id', $forms->pluck('id'))
            ->where('link_type', 'users')
            ->where('link_id', $user->id)
            ->get();

        $user->setRelation('formulaireForms', $forms);
        $user->setRelation('formulaireSubmissions', $submissions);

        return [];
    }
}
