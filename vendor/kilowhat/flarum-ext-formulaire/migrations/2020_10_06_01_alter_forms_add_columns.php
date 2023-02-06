<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Builder;

return Migration::addColumns('formulaire_forms', [
    'email_notification_message' => ['text', 'nullable' => true],
    'email_confirmation_title' => ['string', 'length' => Builder::$defaultStringLength, 'nullable' => true], // Used for confirmation emails to actor
    'email_notification_title' => ['string', 'length' => Builder::$defaultStringLength, 'nullable' => true], // Used for notification to listed emails
    'automatic_discussion_options' => ['json', 'nullable' => true],
]);
