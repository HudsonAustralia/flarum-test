<?php

namespace Kilowhat\Formulaire\Repositories;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Formatter\Formatter;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Foundation\ValidationException;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\Translator;
use Flarum\Notification\NotificationSyncer;
use Flarum\Post\CommentPost;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Discussion\Event\SubmissionChanged as DiscussionChanged;
use Kilowhat\Formulaire\Events\SubmissionCreated;
use Kilowhat\Formulaire\Events\SubmissionUpdated;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\Submission;
use Kilowhat\Formulaire\Submission\Event;
use Kilowhat\Formulaire\User\Event\SubmissionChanged as UserChanged;
use Kilowhat\Formulaire\ValidationExceptionFromErrorBag;
use Kilowhat\Formulaire\Validators\TemplateValidator;
use Ramsey\Uuid\Uuid;

class SubmissionRepository
{
    use DispatchEventsTrait;

    protected $notifications;
    protected $translator;

    public function __construct(Dispatcher $events, NotificationSyncer $notifications, Translator $translator)
    {
        $this->events = $events;
        $this->notifications = $notifications;
        $this->translator = $translator;
    }

    public function query(): Builder
    {
        return Submission::query();
    }

    public function scopeVisibleTo(User $actor, string $ability = 'view'): Builder
    {
        return $this->query()->whereVisibleTo($actor, $ability);
    }

    /**
     * @param string $uid
     * @param User $actor
     * @return Submission|Model
     */
    public function findStandaloneOrFail(string $uid, User $actor): Submission
    {
        return $this->scopeVisibleTo($actor)
            ->where('uid', $uid)
            ->firstOrFail();
    }

    /**
     * @param string $uid
     * @return Submission|Model
     */
    public function findOrFailWithoutScope(string $uid): Submission
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    /**
     * This method assumes the access to $form has been checked beforehand
     * @param Form $form
     * @param User|Discussion|AbstractModel $linked
     * @return Submission|Model|null
     */
    public function findLinked(Form $form, AbstractModel $linked): ?Submission
    {
        return $form->submissions()
            ->where('link_type', $linked->getMorphClass())
            ->where('link_id', $linked->id)
            ->first();
    }

    public function delete(Submission $submission, User $actor)
    {
        $actor->assertCan('delete', $submission);

        $files = $submission->files;

        $submission->delete();

        $submission->form->refreshSubmissionCount();

        if ($files->isNotEmpty()) {
            /**
             * @var FileRepository $fileRepository
             */
            $fileRepository = resolve(FileRepository::class);

            foreach ($files as $file) {
                $fileRepository->delete($file);
            }
        }
    }

    protected function validateAndSave(Submission $submission, array $attributes, User $actor)
    {
        /**
         * @var TemplateValidator $validator
         */
        $validator = resolve(TemplateValidator::class);

        $data = $validator->cleanAndValidateSubmission(
            Arr::get($attributes, 'data', []),
            $submission->form->template ?? [],
            $actor,
            $submission->data ?? []
        );

        if ($validator->messages->isNotEmpty()) {
            throw new ValidationExceptionFromErrorBag($validator->messages);
        }

        $oldData = $submission->data;

        if (json_encode($data) !== json_encode($oldData)) {
            $submission->data = $data;

            // Check for issues that the TemplateValidator cannot check (as they require the form or submission ID)
            foreach ($validator->files as $file) {
                if ($file->validated_for_form_id !== $submission->form_id) {
                    throw new ValidationException([
                        $file->validated_for_field_key => $this->translator->trans('kilowhat-formulaire.api.file-already-belongs-form', [
                            '{id}' => $file->uid,
                        ]),
                    ]);
                }

                // Will check that the submission ID matches, or that it's null when it's a new submission
                if (!is_null($file->submission_id) && $file->submission_id !== $submission->id) {
                    throw new ValidationException([
                        $file->validated_for_field_key => $this->translator->trans('kilowhat-formulaire.api.file-already-belongs-submission', [
                            '{id}' => $file->uid,
                        ]),
                    ]);
                }
            }

            $submission->afterSave(function () use ($validator, $submission) {
                // Associate new files with the submission
                // Must be done after save to have access to the submission ID
                foreach ($validator->files as $file) {
                    if (is_null($file->submission_id)) {
                        $file->submission()->associate($submission);
                        $file->save();
                    }
                }

                // Unlink old files
                $oldFiles = $submission->files()->whereNotIn('uid', Arr::pluck($validator->files, 'uid'))->get();

                foreach ($oldFiles as $file) {
                    /**
                     * @var FileRepository $fileRepository
                     */
                    $fileRepository = resolve(FileRepository::class);
                    $fileRepository->delete($file);
                }
            });
        }

        if ($submission->isDirty()) {
            $submission->save();

            if ($submission->wasRecentlyCreated) {
                $this->events->dispatch(new Event\Created($submission, $actor));
            } else {
                $this->events->dispatch(new Event\DataChanged($submission, $actor, $oldData));
            }
        }
    }

    protected function sendMail($userOrEmail, bool $own, Submission $submission)
    {
        $email = $userOrEmail instanceof User ? $userOrEmail->email : $userOrEmail;

        $title = null;
        $html = '';

        if ($own) {
            $title = $submission->form->email_confirmation_title;

            if ($submission->form->email_confirmation_message) {
                /**
                 * @var Formatter $formatter
                 */
                $formatter = resolve(Formatter::class);

                $html = $formatter->render($submission->form->email_confirmation_message);
            }
        } else {
            $title = $submission->form->email_notification_title;

            if ($submission->form->email_notification_message) {
                /**
                 * @var Formatter $formatter
                 */
                $formatter = resolve(Formatter::class);

                $html = $formatter->render($submission->form->email_notification_message);
            }
        }

        $editLink = null;

        if ($submission->form->allow_modification) {
            /**
             * @var UrlGenerator $url
             */
            $url = resolve(UrlGenerator::class);

            $editLink = $url->to('forum')->route('formulaire.submissions.show', [
                'id' => $submission->uid,
            ]);
        }

        /**
         * @var Mailer $mailer
         */
        $mailer = resolve(Mailer::class);

        $mailer->send('kilowhat-formulaire::mail', [
            'html' => $html,
            'submission' => $submission->data,
            'fields' => $submission->form->template,
            'editLink' => $editLink,
            'files' => $submission->files->keyBy('uid'),
        ], function (Message $message) use ($email, $own, $submission, $title) {
            $title = $submission->replaceVariablesInString($title ?? '');

            if (!$title) {
                $title = $this->translator->trans('kilowhat-formulaire.mail.title-' . ($own ? 'your' : 'new') . '-submission', [
                    '{title}' => $submission->form->title,
                ]);
            }

            $message->to($email);
            $message->subject($title);
        });
    }

    public function storeStandalone(Form $form, array $attributes, User $actor): Submission
    {
        $submission = new Submission();
        $submission->uid = Uuid::uuid4()->toString();
        $submission->form()->associate($form);
        if (!$actor->isGuest()) {
            $submission->user()->associate($actor);
        }

        if ($form->isAutoLink()) {
            $actor->assertCan('createAutoLinkSubmission', $form);

            $submission->afterSave(function (Submission $submission) use ($actor, $form) {
                $title = $submission->replaceVariablesInString(Arr::get($form->automatic_discussion_options, 'title', ''));

                if (!$title) {
                    $title = $submission->replaceVariablesInString('{title}');
                }

                if (!$title) {
                    $title = $form->title;
                }

                $discussion = Discussion::start($title, $actor);

                $discussion->save();

                /**
                 * @var $manager ExtensionManager
                 */
                $manager = resolve(ExtensionManager::class);

                if ($manager->isEnabled('flarum-tags')) {
                    $tagIds = $form->link_id ? [$form->link_id] : [];

                    if ($tagSlugs = Arr::get($form->automatic_discussion_options, 'tags', '')) {
                        $tagIds = array_merge($tagIds, Tag::query()
                            ->whereIn('slug', explode(',', $tagSlugs))
                            ->pluck('id')
                            ->all());
                    }

                    $discussion->tags()->sync($tagIds);
                }

                $content = $submission->replaceVariablesInString(Arr::get($form->automatic_discussion_options, 'content', ''));

                if (!$content) {
                    $content = $submission->replaceVariablesInString('{content}');
                }

                if (!$content) {
                    // We can't use an empty string because Flarum skips parsing falsy values, and then fails on render
                    // A space ensures the content is parsed with TextFormatter
                    $content = ' ';
                }

                $post = CommentPost::reply(
                    $discussion->id,
                    $content,
                    $actor->id,
                    '127.0.0.1'
                );

                $post->save();

                $this->notifications->onePerUser(function () use ($post, $actor) {
                    $this->dispatchEventsFor($post, $actor);
                });

                $discussion->setRawAttributes($post->discussion->getAttributes(), true);
                $discussion->setFirstPost($post);
                $discussion->setLastPost($post);

                $this->dispatchEventsFor($discussion, $actor);

                $discussion->save();

                $submission->link()->associate($discussion);
                $submission->save();
            });
        } else {
            $actor->assertCan('createStandaloneSubmission', $form);
        }

        $this->validateAndSave($submission, $attributes, $actor);

        $form->refreshSubmissionCount();

        if ($form->notify_emails) {
            foreach (explode(',', $form->notify_emails) as $email) {
                $this->sendMail($email, false, $submission);
            }
        }

        // If there's a field named email, we use its value as the recipient
        // TODO: make it configurable, verify it's an email field
        $emailFromData = Arr::get($submission->data, 'email');

        if ($emailFromData) {
            $this->sendMail($emailFromData, true, $submission);
        }

        if ($form->send_confirmation_to_participants && $actor->is_email_confirmed) {
            $this->sendMail($actor, true, $submission);
        }

        $this->events->dispatch(new SubmissionCreated($submission));

        return $submission;
    }

    public function updateStandalone(Submission $submission, array $attributes, User $actor): Submission
    {
        $actor->assertCan('editStandalone', $submission);

        if (Arr::exists($attributes, 'isLocked')) {
            $actor->assertCan('lock', $submission);

            $currentLock = (bool)$submission->locked_at;
            $newLock = (bool)Arr::get($attributes, 'isLocked');

            if ($newLock !== $currentLock) {
                $submission->locked_at = $newLock ? Carbon::now() : null;
                $submission->save();

                return $submission;
            }
        }

        if (Arr::exists($attributes, 'isHidden')) {
            $currentHidden = (bool)$submission->hidden_at;
            $newHidden = (bool)Arr::get($attributes, 'isHidden');

            if ($newHidden !== $currentHidden) {
                $actor->assertCan($newHidden ? 'hide' : 'restore', $submission);

                $submission->hidden_at = $newHidden ? Carbon::now() : null;
                $submission->save();

                $submission->form->refreshSubmissionCount();

                return $submission;
            }
        }

        $this->validateAndSave($submission, $attributes, $actor);

        $this->events->dispatch(new SubmissionUpdated($submission));

        return $submission;
    }

    public function updateLinked(Form $form, User $actor, AbstractModel $link, array $attributes, Submission $submission = null): Submission
    {
        $oldData = [];

        if ($submission) {
            $oldData = $submission->data;
        } else {
            $submission = new Submission();
            $submission->uid = Uuid::uuid4()->toString();
            $submission->form()->associate($form);
            $submission->link()->associate($link);
            $submission->user()->associate($link instanceof User ? $link->id : $link->user_id);
        }

        $this->validateAndSave($submission, $attributes, $actor);

        $form->refreshSubmissionCount();

        if ($link instanceof Discussion) {
            $this->events->dispatch(new DiscussionChanged($link, $form, $submission, $actor, $oldData));
        } else if ($link instanceof User) {
            $this->events->dispatch(new UserChanged($link, $form, $submission, $actor, $oldData));
        }

        return $submission;
    }
}
