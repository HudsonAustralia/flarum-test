<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('formulaire_submissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uid')->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('form_id')->nullable();
            $table->nullableMorphs('link');
            $table->json('data');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->timestamp('hidden_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('form_id')->references('id')->on('formulaire_forms')->onDelete('cascade');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('formulaire_submissions');
    },
];
