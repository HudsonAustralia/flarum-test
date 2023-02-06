<?php

namespace Kilowhat\Formulaire\Formatter;

use Flarum\User\User;

/**
 * Will be passed as formatter context when rendering a content block in a form template
 *
 * @property User $actor The actor
 * @property array $field The associative array of the field definition
 */
class FieldContext
{
    public $actor;
    public $field;

    public function __construct(User $actor, array $field)
    {
        $this->actor = $actor;
        $this->field = $field;
    }
}
