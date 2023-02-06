<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Builder;

return Migration::addColumns('formulaire_forms', [
    'private_title' => ['string', 'length' => Builder::$defaultStringLength, 'nullable' => true],
]);
