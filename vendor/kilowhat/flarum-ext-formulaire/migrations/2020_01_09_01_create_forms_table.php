<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('formulaire_forms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uid')->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->nullableMorphs('link');
            $table->string('slug')->nullable()->unique();
            $table->string('title');
            $table->json('template');
            $table->boolean('accept_submissions')->default(false);
            $table->boolean('allow_modification')->default(false);
            $table->unsignedInteger('max_submissions')->nullable();
            $table->unsignedInteger('submission_count')->nullable();
            $table->boolean('send_confirmation_to_participants')->default(false);
            $table->text('notify_emails')->nullable();
            $table->text('web_confirmation_message')->nullable();
            $table->text('email_confirmation_message')->nullable();
            $table->timestamps();
            $table->timestamp('hidden_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('formulaire_forms');
    },
];
