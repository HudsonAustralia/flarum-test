<?php

namespace Kilowhat\Formulaire\Formatter;

/**
 * Will be passed as formatter context when rendering a rich text answer
 *
 * @property array $field The associative array of the field definition
 */
class AnswerContext
{
    public $field;

    public function __construct(array $field)
    {
        $this->field = $field;
    }
}
