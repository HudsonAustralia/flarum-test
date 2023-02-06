<?php

namespace Kilowhat\Formulaire\Repositories;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Formatter\Formatter;
use Flarum\Group\Group;
use Flarum\Locale\Translator;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Factory;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\Form\Event;
use Kilowhat\Formulaire\GroupIdHelper;
use Kilowhat\Formulaire\ValidationExceptionFromErrorBag;
use Kilowhat\Formulaire\Validators\TemplateValidator;
use Ramsey\Uuid\Uuid;
use Flarum\Tags\TagRepository;
use Flarum\Tags\Tag;

class FormRepository
{
    protected $events;

    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    public function query(): Builder
    {
        return Form::query();
    }

    public function scopeVisibleTo(User $actor, string $ability = 'view'): Builder
    {
        return $this->query()->whereVisibleTo($actor, $ability);
    }

    /**
     * @param string $uid
     * @param User $actor
     * @return Model|Form
     */
    public function findOrFail(string $uid, User $actor): Form
    {
        return $this->scopeVisibleTo($actor)
            ->where('uid', $uid)
            ->orWhere('slug', $uid)
            ->firstOrFail();
    }

    /**
     * @param string $uid
     * @return Model|Form
     */
    public function findOrFailWithoutScope(string $uid): Form
    {
        return $this->query()
            ->where('uid', $uid)
            ->orWhere('slug', $uid)
            ->firstOrFail();
    }

    protected function hasLocalOrGlobalPermission(User $actor, Form $form, string $localAbility, string $globalAbility, $globalArguments = []): bool
    {
        if ($actor->hasPermission('formulaire.moderate')) {
            return true;
        }

        /**
         * @var $localAbility array|null
         */
        $local = $form->{'permission_' . $localAbility};

        if (!is_null($local)) {
            // Admin group is not listed in the JSON column, but it doesn't matter because admins
            // have already passed the moderate check above and this code never actually runs
            return GroupIdHelper::userIsInOneOfTheGroups($actor, $local);
        }

        return $actor->can($globalAbility, $globalArguments);
    }

    protected function isSelf(User $actor, User $user): bool
    {
        // When posting fields for a new user, we need guest to be able to see the fields using the "see own" permission
        return $user->id === $actor->id || ($actor->isGuest() && !$user->exists);
    }

    public function forUserProfile(User $user, User $actor): Collection
    {
        return $this->scopeVisibleTo($actor, 'viewLinked')
            ->where('link_type', 'groups')
            ->where(function (Builder $query) use ($user) {
                $query->whereNull('link_id')
                    ->orWhereIn('link_id', $user->groups->pluck('id')->all());
            })
            ->orderBy('title')
            ->get()
            ->filter(function (Form $form) use ($user, $actor) {
                if ($this->isSelf($actor, $user)) {
                    return $this->hasLocalOrGlobalPermission($actor, $form, 'see_own', 'formulaire.seeOwnUser');
                }

                return $this->hasLocalOrGlobalPermission($actor, $form, 'see_any', 'formulaire.seeAnyUser');
            })->each(function (Form $form) use ($user, $actor) {
                $form->canSubmit = $this->canEditUser($actor, $form, $user);
            });
    }

    /**
     * @param Discussion $discussion
     * @param User $actor
     * @param bool $editing
     * @return Collection|Form[]
     */
    public function forDiscussion(Discussion $discussion, User $actor, bool $editing = false): Collection
    {
        return $this->scopeVisibleTo($actor, 'viewLinked')
            ->where('link_type', 'tags')
            ->where(function (Builder $query) use ($discussion, $editing) {
                // During creation and editing, we need to use the tags provided to the save event
                // So we'll return everything here and validate later
                if ($editing) {
                    $query->whereRaw('TRUE');

                    return;
                }

                $query->whereNull('link_id');

                // If the Tags extension is not enabled, the relationship will return null
                if ($discussion->tags) {
                    $query->orWhereIn('link_id', $discussion->tags->pluck('id')->all());
                }
            })
            ->orderBy('title')
            ->get()
            ->filter(function (Form $form) use ($discussion, $actor) {
                if (($discussion->user_id && $discussion->user_id === $actor->id) || !$discussion->exists) {
                    return $this->hasLocalOrGlobalPermission($actor, $form, 'see_own', 'seeOwnFormulaire', $discussion);
                }

                return $this->hasLocalOrGlobalPermission($actor, $form, 'see_any', 'seeAnyFormulaire', $discussion);
            })->each(function (Form $form) use ($discussion, $actor) {
                $form->canSubmit = $this->canEditDiscussion($actor, $form, $discussion);
            });
    }

    public function forSignUp(User $actor): EloquentCollection
    {
        // We don't apply any additional permission filtering
        // We assume the user properly set any show_on_creation form to allow guest/disabled submissions
        // There will be an error on submission if the permissions are not set correctly
        return $this->scopeVisibleTo($actor, 'viewLinked')
            ->where('link_type', 'groups')
            ->whereNull('link_id')
            ->where('show_on_creation', true)
            ->orderBy('title')
            ->get();
    }

    public function forComposer(User $actor): EloquentCollection
    {
        // We don't apply any additional permission filtering
        // We assume that show_on_creation is always accompanied by "edit own" permission for everyone able to use the tag
        return $this->scopeVisibleTo($actor, 'viewLinked')
            ->where('link_type', 'tags')
            ->where(function (Builder $query) use ($actor) {
                $query->whereNull('link_id');

                /**
                 * @var $manager ExtensionManager
                 */
                $manager = resolve(ExtensionManager::class);

                // If the Tags extension is enabled, we will include the forms for all tags
                // that the user could use in new discussions
                if ($manager->isEnabled('flarum-tags')) {
                    $repository = resolve(TagRepository::class);

                    /**
                     * @var $visibleTags EloquentCollection
                     */
                    $visibleTags = $repository->all($actor);

                    $usableTags = $visibleTags->filter(function (Tag $tag) use ($actor) {
                        return $actor->can('startDiscussion', $tag);
                    });

                    $query->orWhereIn('link_id', $usableTags->pluck('id'));
                }
            })
            ->where('show_on_creation', true)
            ->orderBy('title')
            ->get();
    }

    public function canEditUser(User $actor, Form $form, User $user): bool
    {
        // Some user-specific checks are placed in a policy
        if ($actor->cannot('fillFormulaire', $user)) {
            return false;
        }

        if ($this->isSelf($actor, $user)) {
            return $this->hasLocalOrGlobalPermission($actor, $form, 'edit_own', 'formulaire.editOwnUser');
        }

        return $this->hasLocalOrGlobalPermission($actor, $form, 'edit_any', 'formulaire.editAnyUser');
    }

    public function canEditDiscussion(User $actor, Form $form, Discussion $discussion): bool
    {
        // Some discussion-specific checks are placed in a policy
        if ($actor->cannot('fillFormulaire', $discussion)) {
            return false;
        }

        if ($discussion->user_id && $discussion->user_id === $actor->id) {
            return $this->hasLocalOrGlobalPermission($actor, $form, 'edit_own', 'editOwnFormulaire', $discussion);
        }

        return $this->hasLocalOrGlobalPermission($actor, $form, 'edit_any', 'editAnyFormulaire', $discussion);
    }

    public function delete(Form $form, User $actor)
    {
        $actor->assertCan('delete', $form);

        // Loop through submissions instead ?
        $form->files()->chunk(100, function (EloquentCollection $files) {
            /**
             * @var FileRepository $fileRepository
             */
            $fileRepository = resolve(FileRepository::class);

            foreach ($files as $file) {
                $fileRepository->delete($file);
            }
        });

        $form->delete();
    }

    protected function validateAndSetAttributes(Form $form, array $attributes)
    {
        // Transform zero into null for the max submissions attribute
        if (Arr::exists($attributes, 'max_submissions') && Arr::get($attributes, 'max_submissions') === 0) {
            $attributes['max_submissions'] = null;
        }

        /**
         * @var Factory $validatorFactory
         */
        $validatorFactory = resolve(Factory::class);

        $formValidator = $validatorFactory->make($attributes, [
            'link_type' => 'nullable|in:groups,tags',
            'slug' => 'nullable|alpha_dash|max:255|unique:formulaire_forms,slug,' . $form->id,
            'title' => 'required|string|max:255',
            'private_title' => 'required|string|max:255',
            'accept_submissions' => 'boolean',
            'allow_modification' => 'boolean',
            'max_submissions' => 'nullable|integer|min:1',
            'send_confirmation_to_participants' => 'boolean',
            'notify_emails' => 'nullable|string|max:255',
            'web_confirmation_message' => 'nullable|string',
            'email_confirmation_message' => 'nullable|string',
            'email_notification_message' => 'nullable|string',
            'email_confirmation_title' => 'nullable|string|max:255',
            'email_notification_title' => 'nullable|string|max:255',
            'automatic_discussion_options' => 'nullable', // JSON
            'permission_see_own' => 'nullable|array',
            'permission_see_own.*' => 'required|integer|min:0',
            'permission_see_any' => 'nullable|array',
            'permission_see_any.*' => 'required|integer|min:0',
            'permission_edit_own' => 'nullable|array',
            'permission_edit_own.*' => 'required|integer|min:0',
            'permission_edit_any' => 'nullable|array',
            'permission_edit_any.*' => 'required|integer|min:0',
            'show_on_creation' => 'boolean',
        ]);

        switch (Arr::get($attributes, 'link_type')) {
            case 'groups':
                $formValidator->addRules([
                    'link_id' => 'nullable|exists:groups,id|not_in:' . Group::GUEST_ID . ',' . Group::MEMBER_ID,
                ]);
                break;
            case 'tags':
                $formValidator->addRules([
                    'link_id' => 'nullable|exists:tags,id',
                ]);
                break;
            default:
                // Force a null value for the link if the type is null or unknown
                $attributes['link_id'] = null;
        }

        $errors = $formValidator->errors();

        // Validate each of the comma-separated emails
        if ($rawNotifyEmails = Arr::get($attributes, 'notify_emails')) {
            foreach (explode(',', $rawNotifyEmails) as $email) {
                $errors->merge($validatorFactory->make([
                    'notify_emails' => $email,
                ], [
                    'notify_emails' => 'required|email',
                ], [], [
                    'notify_emails' => $email,
                ])->errors());
            }
        }

        /**
         * @var TemplateValidator $templateValidator
         */
        $templateValidator = resolve(TemplateValidator::class);

        $data = $templateValidator->cleanAndValidateTemplate(Arr::get($attributes, 'template', []), 'template/');

        $errors->merge($templateValidator->messages);

        /**
         * @var Formatter $formatter
         */
        $formatter = resolve(Formatter::class);

        $webMessage = null;

        if ($rawWebMessage = Arr::get($attributes, 'web_confirmation_message')) {
            try {
                $webMessage = $formatter->parse($rawWebMessage);
            } catch (\Exception $exception) {
                $errors->add('web_confirmation_message', $exception->getMessage());
            }
        }

        $emailConfirmationMessage = null;

        if ($rawConfirmationMessage = Arr::get($attributes, 'email_confirmation_message')) {
            try {
                $emailConfirmationMessage = $formatter->parse($rawConfirmationMessage);
            } catch (\Exception $exception) {
                $errors->add('web_confirmation_message', $exception->getMessage());
            }
        }

        $emailNotificationMessage = null;

        if ($rawNotificationMessage = Arr::get($attributes, 'email_notification_message')) {
            try {
                $emailNotificationMessage = $formatter->parse($rawNotificationMessage);
            } catch (\Exception $exception) {
                $errors->add('email_notification_message', $exception->getMessage());
            }
        }

        if (
            $form->exists && (
                $form->link_type !== Arr::get($attributes, 'link_type') ||
                $form->link_id !== Arr::get($attributes, 'link_id')
            ) && $form->submission_count > 0) {
            /**
             * @var $translator Translator
             */
            $translator = resolve(Translator::class);

            $errors->add('link_type', $translator->trans('kilowhat-formulaire.api.cannot-change-form-type'));
        }

        if ($errors->isNotEmpty()) {
            throw new ValidationExceptionFromErrorBag($errors);
        }

        $form->link_type = Arr::get($attributes, 'link_type');
        $form->link_id = Arr::get($attributes, 'link_id');
        $form->slug = Arr::get($attributes, 'slug') ?: null;
        $form->title = Arr::get($attributes, 'title');
        $form->private_title = Arr::get($attributes, 'private_title');
        $form->template = $data;
        $form->accept_submissions = Arr::get($attributes, 'accept_submissions') ?? false;
        $form->allow_modification = Arr::get($attributes, 'allow_modification') ?? false;
        $form->max_submissions = Arr::get($attributes, 'max_submissions');
        $form->send_confirmation_to_participants = Arr::get($attributes, 'send_confirmation_to_participants') ?? false;
        $form->notify_emails = Arr::get($attributes, 'notify_emails');
        $form->web_confirmation_message = $webMessage;
        $form->email_confirmation_message = $emailConfirmationMessage;
        $form->email_notification_message = $emailNotificationMessage;
        $form->email_confirmation_title = Arr::get($attributes, 'email_confirmation_title');
        $form->email_notification_title = Arr::get($attributes, 'email_notification_title');
        $form->automatic_discussion_options = Arr::get($attributes, 'automatic_discussion_options');
        $form->permission_see_own = Arr::get($attributes, 'permission_see_own');
        $form->permission_see_any = Arr::get($attributes, 'permission_see_any');
        $form->permission_edit_own = Arr::get($attributes, 'permission_edit_own');
        $form->permission_edit_any = Arr::get($attributes, 'permission_edit_any');
        $form->show_on_creation = Arr::get($attributes, 'show_on_creation') ?? false;
    }

    public function store(array $data, User $actor): Form
    {
        $actor->assertCan('create', Form::class);

        $form = new Form();
        $form->uid = Uuid::uuid4()->toString();
        $form->user()->associate($actor);

        $this->validateAndSetAttributes($form, (array)Arr::get($data, 'data.attributes'));

        $this->events->dispatch(new Event\Saving($form, $actor, $data));

        $form->save();

        $this->events->dispatch(new Event\Created($form, $actor));

        return $form;
    }

    public function update(Form $form, array $data, User $actor): Form
    {
        $actor->assertCan('edit', $form);

        $attributes = (array)Arr::get($data, 'data.attributes');

        if (Arr::exists($attributes, 'isHidden')) {
            $actor->assertCan('hide', $form);

            $currentHidden = (bool)$form->hidden_at;
            $newHidden = (bool)Arr::get($attributes, 'isHidden');

            if ($newHidden !== $currentHidden) {
                $form->hidden_at = $newHidden ? Carbon::now() : null;
                $form->save();

                return $form;
            }
        }

        $this->validateAndSetAttributes($form, $attributes);

        $this->events->dispatch(new Event\Saving($form, $actor, $data));

        $form->save();

        $this->events->dispatch(new Event\Updated($form, $actor));

        return $form;
    }
}
