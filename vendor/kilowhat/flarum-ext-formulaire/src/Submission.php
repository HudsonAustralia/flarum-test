<?php

namespace Kilowhat\Formulaire;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property string $uid
 * @property int $user_id
 * @property int $form_id
 * @property string $link_type
 * @property int $link_id
 * @property array $data
 * @property Carbon $locked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $hidden_at
 *
 * @property User $user
 * @property Form $form
 * @property User|Discussion|null $link
 * @property File[]|Collection $files
 */
class Submission extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'formulaire_submissions';

    public $timestamps = true;

    protected $casts = [
        'data' => 'array',
        'locked_at' => 'datetime',
        'hidden_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function form(): Relations\BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function link(): Relations\MorphTo
    {
        return $this->morphTo('link');
    }

    public function files(): Relations\HasMany
    {
        return $this->hasMany(File::class);
    }

    public function replaceVariablesInString(string $value)
    {
        return preg_replace_callback('~{\s?([A-Za-z0-9._-]+)\s?}~', function (array $matches) {
            $replacementValue = Arr::get($this->data, $matches[1], '');

            if (!$replacementValue) {
                switch ($matches[1]) {
                    case 'user_id':
                        $replacementValue = $this->user->id;
                        break;
                    case 'user_display_name':
                        $replacementValue = $this->user->display_name;
                        break;
                    case 'user_username':
                        $replacementValue = $this->user->username;
                        break;
                    case 'user_email':
                        $replacementValue = $this->user->email;
                        break;
                    case 'user_group_ids':
                        $replacementValue = $this->user->groups()->pluck('id')->implode(',');
                        break;
                    case 'user_group_names':
                        $replacementValue = $this->user->groups()->pluck('name_singular')->implode(',');
                        break;
                }
            }

            return $replacementValue;
        }, $value);
    }
}
