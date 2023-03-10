<?php

/*
 * This file is part of fof/subscribed.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Subscribed\Jobs;

use Flarum\Notification\NotificationSyncer;
use Flarum\Post\Post;
use Flarum\User\User;
use FoF\Subscribed\Blueprints\PostCreatedBlueprint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Expression;
use Illuminate\Queue\SerializesModels;

class SendNotificationWhenPostIsCreated implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @var Post
     */
    protected $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    public function handle(NotificationSyncer $notifications)
    {
        $post = $this->post;

        $notify = User::query()
            ->where('users.id', '!=', $post->user_id)
            ->where('preferences', 'regexp', new Expression('\'"notify_postCreated_[a-z]+":true\''))
            ->get();

        $notify = $notify->filter(function (User $recipient) use ($post) {
            return $recipient->can('subscribePostCreated') && $post->isVisibleTo($recipient);
        });

        $notifications->sync(
            new PostCreatedBlueprint($post),
            $notify->all()
        );
    }
}
