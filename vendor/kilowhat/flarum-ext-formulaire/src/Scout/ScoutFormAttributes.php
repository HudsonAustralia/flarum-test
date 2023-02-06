<?php

namespace Kilowhat\Formulaire\Scout;

use Illuminate\Support\Arr;
use Kilowhat\Formulaire\Form;

class ScoutFormAttributes
{
    public function __invoke(Form $form): array
    {
        $fields = [];

        foreach ($form->template as $field) {
            $title = Arr::get($field, 'title');

            if ($title) {
                $fields[] = $title;
            }
        }

        return [
            'uid' => $form->uid,
            'title' => $form->title,
            'privateTitle' => $form->private_title,
            'slug' => $form->slug,
            'fields' => $fields,
        ];
    }
}
