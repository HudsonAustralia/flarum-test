<?php

namespace Kilowhat\Formulaire\Form\Event;

use Flarum\User\User;
use Kilowhat\Formulaire\Form;

/**
 * After a form has been successfully deleted permanently.
 */
class Deleted
{
    public $form;
    public $actor;

    public function __construct(Form $form, User $actor = null)
    {
        $this->form = $form;
        $this->actor = $actor;
    }
}
