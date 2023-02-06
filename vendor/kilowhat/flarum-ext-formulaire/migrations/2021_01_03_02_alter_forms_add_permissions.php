<?php

use Flarum\Database\Migration;

return Migration::addColumns('formulaire_forms', [
    'permission_see_own' => ['json', 'nullable' => true],
    'permission_see_any' => ['json', 'nullable' => true],
    'permission_edit_own' => ['json', 'nullable' => true],
    'permission_edit_any' => ['json', 'nullable' => true],
]);
