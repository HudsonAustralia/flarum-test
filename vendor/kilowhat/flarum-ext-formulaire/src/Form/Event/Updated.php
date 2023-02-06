<?php

namespace Kilowhat\Formulaire\Form\Event;

use Flarum\User\User;
use Kilowhat\Formulaire\Form;

/**
 * After a form has been successfully modified via the edit endpoint.
 * This event serves as a general catch-all for all the kinds of edit that can happen.
 */
class Updated
{
    public $form;
    public $actor;

    public function __construct(Form $form, User $actor = null)
    {
        $this->form = $form;
        $this->actor = $actor;
    }
}
