<?php

namespace Kilowhat\Formulaire\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use Flarum\Formatter\Formatter;
use Kilowhat\Formulaire\Form;
use Kilowhat\Formulaire\TemplateRenderer;

class FormSerializer extends AbstractSerializer
{
    protected $type = 'formulaire-forms';

    protected $renderer;
    protected $formatter;

    public function __construct(TemplateRenderer $renderer, Formatter $formatter)
    {
        $this->renderer = $renderer;
        $this->formatter = $formatter;
    }

    /**
     * @param Form $form
     * @return string
     */
    public function getId($form): string
    {
        return $form->uid;
    }

    /**
     * @param Form $form
     * @return array
     */
    protected function getDefaultAttributes($form): array
    {
        $attributes = [
            // Readonly attribute used to build URLs or fetch from store based on URL parameter
            'seoId' => $form->slug ?? $form->uid,
            'title' => $form->title,
        ];

        // Ideally we would want to hide the template if the form is full
        // But it's just too complicated because you still need to see it if you're editing a full form
        $attributes += [
            'template' => $this->renderer->prepareTemplate($form->template ?? [], $this->actor),
        ];

        if ($form->isStandalone()) {
            $attributes['canSubmit'] = $this->actor->can('createStandaloneSubmission', $form);
        } else if ($form->isAutoLink()) {
            $attributes['canSubmit'] = $this->actor->can('createAutoLinkSubmission', $form);
        } else if (!is_null($form->canSubmit)) {
            $attributes['canSubmit'] = $form->canSubmit;
        }
        // Last case is when a linked form is loaded as a relationship without canSubmit preloaded
        // In that situation we want the attribute to be missing as to not override whatever is already in the store

        // TODO: only show to people who submitted the form once
        if ($form->web_confirmation_message) {
            $attributes += [
                'confirmationMessageHtml' => $this->formatter->render($form->web_confirmation_message, null, $this->request),
            ];
        }

        if ($form->max_submissions && $form->submission_count >= $form->max_submissions) {
            $attributes['isFull'] = true;
        }

        if ($this->actor->can('edit', $form)) {
            $attributes += [
                'slug' => $form->slug,
                // TODO: defaulting to title is only for migration, it's easier that way versus copying the data in the migration
                'private_title' => $form->private_title ?? $form->title,
                'link_type' => $form->link_type,
                'link_id' => $form->link_id,
                'accept_submissions' => $form->accept_submissions,
                'allow_modification' => $form->allow_modification,
                'max_submissions' => $form->max_submissions,
                'submission_count' => $form->submission_count,
                'send_confirmation_to_participants' => $form->send_confirmation_to_participants,
                'notify_emails' => $form->notify_emails,
                'web_confirmation_message' => $form->web_confirmation_message ? $this->formatter->unparse($form->web_confirmation_message) : null,
                'email_confirmation_message' => $form->email_confirmation_message ? $this->formatter->unparse($form->email_confirmation_message) : null,
                'email_notification_message' => $form->email_notification_message ? $this->formatter->unparse($form->email_notification_message) : null,
                'email_confirmation_title' => $form->email_confirmation_title,
                'email_notification_title' => $form->email_notification_title,
                'automatic_discussion_options' => $form->automatic_discussion_options,
                'permission_see_own' => $form->permission_see_own,
                'permission_see_any' => $form->permission_see_any,
                'permission_edit_own' => $form->permission_edit_own,
                'permission_edit_any' => $form->permission_edit_any,
                'show_on_creation' => $form->show_on_creation,
                'canEdit' => true,
                'canHide' => $this->actor->can('hide', $form),
                'canRestore' => $this->actor->can('restore', $form),
                'canDelete' => $this->actor->can('delete', $form),
                'canExport' => $this->actor->can('export', $form),
                'canExportUserDetails' => $this->actor->can('exportUserDetails', $form),
            ];

            if ($form->hidden_at) {
                $attributes['isHidden'] = true;
                $attributes['hiddenAt'] = $this->formatDate($form->hidden_at);
            }
        }

        return $attributes;
    }

    public function user($form)
    {
        return $this->hasOne($form, BasicUserSerializer::class, 'user');
    }
}
