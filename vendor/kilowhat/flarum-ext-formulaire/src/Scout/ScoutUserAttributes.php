<?php

namespace Kilowhat\Formulaire\Scout;

use Flarum\User\Guest;
use Flarum\User\User;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Submission;

class ScoutUserAttributes
{
    use FormulaireScoutTrait;

    protected $repository;

    public function __construct(FormRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(User $user): array
    {
        $forms = $this->repository->forUserProfile($user, new Guest());

        $submissions = Submission::query()
            ->whereIn('form_id', $forms->pluck('id'))
            ->where('link_type', 'users')
            ->where('link_id', $user->id)
            ->get();

        return $this->linkedModel($forms, $submissions);
    }
}
