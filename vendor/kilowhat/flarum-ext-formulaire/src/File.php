<?php

namespace Kilowhat\Formulaire;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations;

/**
 * @property int $id
 * @property string $uid
 * @property int $user_id
 * @property int $submission_id
 * @property int $validated_for_form_id
 * @property string $validated_for_field_key
 * @property string $filename
 * @property string $path
 * @property int $size
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Submission $submission
 */
class File extends AbstractModel
{
    protected $table = 'formulaire_files';

    public $timestamps = true;

    public function submission(): Relations\BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validatedForForm(): Relations\BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function humanSize(): ?string
    {
        $bytes = $this->size;

        if (!$bytes) {
            return null;
        }

        // Based on https://stackoverflow.com/a/23888858/3133038
        $size = ['B', 'kB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.1f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    public function url(): string
    {
        /**
         * @var $generator UrlGenerator
         */
        $generator = resolve(UrlGenerator::class);

        return $generator->to('forum')->path('assets/formulaire/' . $this->path . '/' . $this->filename);
    }
}
