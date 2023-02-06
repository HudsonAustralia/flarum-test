<?php

use Flarum\Database\Migration;

return Migration::addColumns('formulaire_forms', [
    'show_on_creation' => ['boolean', 'default' => false],
]);
