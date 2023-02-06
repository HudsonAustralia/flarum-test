<?php

namespace Kilowhat\Formulaire;

use Exception;
use Flarum\Foundation\ValidationException;
use Illuminate\Support\MessageBag;

/**
 * We need to throw validation errors from just a MessageBag content
 * The Illuminate ValidationException requires a validator object which we don't have
 * The Flarum ValidationException has problems with the use of multiple messages per key
 * (it can serialize them fine in responses, but the creation of the parent error message causes an array to string conversion error because it's not accounting for an array of messages for a single key)
 *
 * We extend the Flarum exception and add support for multiple message per keys, automatically extracted from a MessageBag
 */
class ValidationExceptionFromErrorBag extends ValidationException
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(MessageBag $bag)
    {
        $this->attributes = $bag->messages();
        $this->relationships = [];

        // We call the root parent method, because the code we are overriding is inside the ValidationException constructor
        Exception::__construct(implode("\n", array_map(function ($field, $messages) {
            return implode("\n", $messages);
        }, array_keys($this->attributes), $this->attributes)));
    }
}
