<?php

namespace Kilowhat\Formulaire;

use Flarum\Database\AbstractModel;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Kilowhat\Formulaire\Repositories\FormRepository;
use Kilowhat\Formulaire\Repositories\SubmissionRepository;
use Kilowhat\Formulaire\Validators\TemplateValidator;

abstract class AbstractSaveLinked
{
    protected $formRepository;
    protected $submissionRepository;
    protected $templateValidator;
    protected $translator;
    protected $forms;
    protected $handledForms;

    public function __construct(FormRepository $formRepository, SubmissionRepository $submissionRepository, TemplateValidator $templateValidator, Translator $translator)
    {
        $this->formRepository = $formRepository;
        $this->submissionRepository = $submissionRepository;
        $this->templateValidator = $templateValidator;
        $this->translator = $translator;
    }

    public function handle($event)
    {
        // We cannot set the default in the constructor, because in unit tests the extender instance is reused
        // And that would cause the list of forms to never update
        $this->forms = null;
        $this->handledForms = [];

        $linked = $this->handleEvent($event);

        // If it already exists, we're done
        // Otherwise we will check for missing required forms
        if ($linked->exists) {
            return;
        }

        if (is_null($this->forms)) {
            $this->forms = $this->loadForms($event->actor, $linked);
        }

        $this->forms->filter(function (Form $form) {
            if (in_array($form->uid, $this->handledForms)) {
                return false;
            }

            return $this->shouldBeValidatedWhenMissing($form);
        })->each(function (Form $form) use ($event) {
            // We run the validation with an empty input, just to catch required field errors
            $this->templateValidator->cleanAndValidateSubmission(
                [],
                $form->template ?? [],
                $event->actor,
                [],
                'formulaireForms/' . $form->uid . '/'
            );

            if ($this->templateValidator->messages->isNotEmpty()) {
                throw new ValidationExceptionFromErrorBag($this->templateValidator->messages);
            }
        });
    }

    abstract protected function handleEvent($event): AbstractModel;

    abstract protected function loadForms(User $actor, AbstractModel $linked): Collection;

    abstract protected function canEdit(User $actor, Form $form, AbstractModel $linked): bool;

    protected function getForm(User $actor, AbstractModel $linked, string $id): Form
    {
        if (is_null($this->forms)) {
            $this->forms = $this->loadForms($actor, $linked);
        }

        $this->handledForms[] = $id;

        /**
         * @var $form Form|null
         */
        $form = $this->forms->where('uid', $id)->first();

        if (!$form) {
            throw new ValidationException([
                'formulaire' => $this->translator->trans('kilowhat-formulaire.api.unknown-form', [
                    '{id}' => json_encode($id),
                ]),
            ]);
        }

        if (!$this->canEdit($actor, $form, $linked)) {
            throw new ValidationException([
                'formulaire' => $this->translator->trans('kilowhat-formulaire.api.unauthorized-form', [
                    '{id}' => json_encode($id),
                ]),
            ]);
        }

        return $form;
    }

    protected function handleForm(AbstractModel $linked, User $actor, Form $form, $data)
    {
        $submission = $this->submissionRepository->findLinked($form, $linked);

        $attributes = Arr::get($data, 'attributes');

        // We don't actually place the sanitized content in a variable here because the repository will do it again
        // Doing this check here ensures most errors are caught before the linked model is saved
        $this->templateValidator->cleanAndValidateSubmission(
            Arr::get($attributes, 'data', []),
            $form->template ?? [],
            $actor,
            optional($submission)->data ?? [],
            'formulaireForms/' . $form->uid . '/'
        );

        if ($this->templateValidator->messages->isNotEmpty()) {
            throw new ValidationExceptionFromErrorBag($this->templateValidator->messages);
        }

        $linked->afterSave(function (AbstractModel $linked) use ($form, $actor, $attributes, $submission) {
            // TODO: errors happening here can leave a new linked model without its required fields
            $this->submissionRepository->updateLinked($form, $actor, $linked, $attributes, $submission);
        });
    }

    protected function shouldBeValidatedWhenMissing(Form $form)
    {
        // We don't check canEdit() here
        // show_on_creation forms apply to all new creations and it's assumed access is granted
        return $form->show_on_creation;
    }
}
