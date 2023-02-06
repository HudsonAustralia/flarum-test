<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('formulaire_files', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uid')->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('submission_id')->nullable();
            $table->unsignedInteger('validated_for_form_id')->nullable();
            $table->string('validated_for_field_key')->nullable();
            $table->string('path');
            $table->string('filename');
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Cascade might leave files behind. By setting null we can garbage collect them later
            $table->foreign('submission_id')->references('id')->on('formulaire_submissions')->onDelete('set null');
            $table->foreign('validated_for_form_id')->references('id')->on('formulaire_forms')->onDelete('set null');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('formulaire_files');
    },
];
