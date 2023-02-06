<?php

namespace Kilowhat\Formulaire\Form\Event;

use Flarum\User\User;
use Kilowhat\Formulaire\Form;

/**
 * While the form is being saved.
 * This event only triggers if the user is already authorized to create or update a form.
 */
class Saving
{
    public $form;
    public $actor;
    public $data;

    public function __construct(Form $form, User $actor, array $data = [])
    {
        $this->form = $form;
        $this->actor = $actor;
        $this->data = $data;
    }
}
