<?php

namespace Kilowhat\Formulaire\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use Kilowhat\Formulaire\JsonUtil;
use Kilowhat\Formulaire\Submission;
use Kilowhat\Formulaire\TemplateRenderer;
use Tobscure\JsonApi\Relationship;

class SubmissionSerializer extends AbstractSerializer
{
    protected $type = 'formulaire-submissions';

    protected $renderer;

    public function __construct(TemplateRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * @param Submission $submission
     * @return string
     */
    public function getId($submission): string
    {
        return $submission->uid;
    }

    /**
     * @param Submission $submission
     * @return array
     */
    protected function getDefaultAttributes($submission): array
    {
        $attributes = [
            'data' => JsonUtil::associativeArrayToObject($this->renderer->prepareSubmission(
                $submission->data ?? [],
                $submission->form->template ?? []
            )),
            'createdAt' => $this->formatDate($submission->created_at),
        ];

        if ($submission->form->isStandalone()) {
            if ($submission->locked_at) {
                $attributes['isLocked'] = true;
                $attributes['lockedAt'] = $this->formatDate($submission->locked_at);
            }

            if ($submission->hidden_at) {
                $attributes['isHidden'] = true;
                $attributes['hiddenAt'] = $this->formatDate($submission->hidden_at);
            }

            $attributes += [
                'canEdit' => $this->actor->can('editStandalone', $submission),
                'canLock' => $this->actor->can('lock', $submission),
                'canHide' => $this->actor->can('hide', $submission),
                'canRestore' => $this->actor->can('restore', $submission),
            ];
        }

        if ($this->actor->can('delete', $submission)) {
            $attributes['canDelete'] = true;
        }

        return $attributes;
    }

    public function user($submission):? Relationship
    {
        return $this->hasOne($submission, BasicUserSerializer::class, 'user');
    }

    public function form($submission):? Relationship
    {
        return $this->hasOne($submission, FormSerializer::class, 'form');
    }

    public function files($submission):? Relationship
    {
        return $this->hasMany($submission, FileSerializer::class, 'files');
    }
}
