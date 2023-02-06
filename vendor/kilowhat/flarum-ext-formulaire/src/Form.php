<?php

namespace Kilowhat\Formulaire;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property string $uid
 * @property int $user_id
 * @property string $link_type
 * @property int $link_id
 * @property string $slug
 * @property string $title
 * @property string $private_title
 * @property array $template
 * @property boolean $accept_submissions
 * @property boolean $allow_modification
 * @property int $max_submissions
 * @property int $submission_count
 * @property boolean $send_confirmation_to_participants
 * @property string $notify_emails
 * @property string $web_confirmation_message
 * @property string $email_confirmation_message
 * @property string $email_notification_message
 * @property string $email_confirmation_title
 * @property string $email_notification_title
 * @property array $automatic_discussion_options
 * @property array $permission_see_own
 * @property array $permission_see_any
 * @property array $permission_edit_own
 * @property array $permission_edit_any
 * @property boolean $show_on_creation
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $hidden_at
 *
 * @property User $user
 * @property \Flarum\Tags\Tag|\Flarum\Group\Group|null $link
 * @property Submission[]|Collection $submissions
 */
class Form extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'formulaire_forms';

    public $timestamps = true;

    protected $casts = [
        'template' => 'array',
        'automatic_discussion_options' => 'array',
        'permission_see_own' => 'array',
        'permission_see_any' => 'array',
        'permission_edit_own' => 'array',
        'permission_edit_any' => 'array',
        'hidden_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Holds the state of the permission for linked forms
    // So that the serializer can access it
    public $canSubmit = null;

    public function user(): Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function link(): Relations\MorphTo
    {
        return $this->morphTo('link');
    }

    public function submissions(): Relations\HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function files(): Relations\HasManyThrough
    {
        return $this->hasManyThrough(File::class, Submission::class);
    }

    public function refreshSubmissionCount(): void
    {
        $this->submission_count = $this->submissions()->whereNull('hidden_at')->count();
        $this->save();
    }

    public function isStandalone(): bool
    {
        return $this->link_type === null;
    }

    public function isAutoLink(): bool
    {
        return !!Arr::get($this->automatic_discussion_options, 'enabled');
    }
}
