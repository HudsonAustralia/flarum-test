<?php

namespace Kilowhat\Formulaire\Discussion;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Kilowhat\Formulaire\AbstractSaveLinked;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\ValidationExceptionFromErrorBag;

class SaveDiscussion extends AbstractSaveLinked
{
    protected $payloadTagIds;

    /**
     * @param Saving $event
     * @return AbstractModel
     * @throws ValidationException
     * @throws ValidationExceptionFromErrorBag
     */
    public function handleEvent($event): AbstractModel
    {
        $this->payloadTagIds = collect();

        // This data is untrusted, but we know flarum-tags will throw permissions error before anything is persisted if those are invalid
        $tagsData = Arr::get($event->data, 'relationships.tags.data');

        if (is_array($tagsData)) {
            foreach ($tagsData as $tagData) {
                $this->payloadTagIds->push(Arr::get($tagData, 'id'));
            }
        } else {
            $existingDiscussionTags = $event->discussion->tags;

            if ($existingDiscussionTags) {
                $this->payloadTagIds = $existingDiscussionTags->pluck('id');
            }
        }

        foreach (Arr::get($event->data, 'relationships.formulaireForms.data') ?? [] as $formData) {
            $id = (string)Arr::get($formData, 'id');

            $form = $this->getForm($event->actor, $event->discussion, $id);

            $tag = $form->link;

            if ($tag) {
                if (!$event->actor->can('startDiscussion', $tag) || !$this->payloadTagIds->contains($tag->id)) {
                    throw new ValidationException([
                        'formulaire' => $this->translator->trans('kilowhat-formulaire.api.unauthorized-scoped-form', [
                            '{id}' => json_encode($id),
                        ]),
                    ]);
                }
            }

            $this->handleForm($event->discussion, $event->actor, $form, $formData);
        }

        return $event->discussion;
    }

    /**
     * @param User $actor
     * @param AbstractModel|Discussion $linked
     * @return Collection
     */
    protected function loadForms(User $actor, AbstractModel $linked): Collection
    {
        return $this->formRepository->forDiscussion($linked, $actor, true);
    }

    /**
     * @param User $actor
     * @param Form $form
     * @param AbstractModel|Discussion $linked
     * @return bool
     */
    protected function canEdit(User $actor, Form $form, AbstractModel $linked): bool
    {
        return $this->formRepository->canEditDiscussion($actor, $form, $linked);
    }

    protected function shouldBeValidatedWhenMissing(Form $form)
    {
        if ($form->link_id && !$this->payloadTagIds->contains($form->link_id)) {
            return false;
        }

        return parent::shouldBeValidatedWhenMissing($form);
    }
}
