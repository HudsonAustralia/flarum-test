<?php

namespace Kilowhat\Formulaire\User;

use Flarum\Database\AbstractModel;
use Flarum\Foundation\ValidationException;
use Flarum\User\Event\Saving;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Kilowhat\Formulaire\AbstractSaveLinked;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\ValidationExceptionFromErrorBag;

class SaveUser extends AbstractSaveLinked
{
    /**
     * @param Saving $event
     * @return AbstractModel
     * @throws ValidationException
     * @throws ValidationExceptionFromErrorBag
     */
    protected function handleEvent($event): AbstractModel
    {
        $formDataArray = Arr::get($event->data, 'relationships.formulaireForms.data');

        // This is a workaround for RegisterController not allowing JSON:API and placing the full payload in `attributes`
        // In this situation we instead read the relationship from an attribute
        if (is_null($formDataArray)) {
            $formDataArray = Arr::get($event->data, 'attributes.formulaireForms') ?? [];
        }

        foreach ($formDataArray as $formData) {
            $id = (string)Arr::get($formData, 'id');

            $form = $this->getForm($event->actor, $event->user, $id);

            $this->handleForm($event->user, $event->actor, $form, $formData);
        }

        return $event->user;
    }

    /**
     * @param User $actor
     * @param AbstractModel|User $linked
     * @return Collection
     */
    protected function loadForms(User $actor, AbstractModel $linked): Collection
    {
        return $this->formRepository->forUserProfile($linked, $actor);
    }

    /**
     * @param User $actor
     * @param Form $form
     * @param AbstractModel|User $linked
     * @return bool
     */
    protected function canEdit(User $actor, Form $form, AbstractModel $linked): bool
    {
        return $this->formRepository->canEditUser($actor, $form, $linked);
    }
}
