<?php

namespace Kilowhat\Formulaire\Providers;

use Flarum\Discussion\Discussion;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Group\Group;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\Relation;

class RelationServiceProvider extends AbstractServiceProvider
{
    public function boot()
    {
        Relation::morphMap([
            'discussions' => Discussion::class,
            'groups' => Group::class,
            'tags' => Tag::class,
            'users' => User::class,
        ]);
    }
}
