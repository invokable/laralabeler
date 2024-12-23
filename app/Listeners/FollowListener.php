<?php

namespace App\Listeners;

use Revolution\Bluesky\Events\Labeler\NotificationReceived;
use Revolution\Bluesky\Facades\Bluesky;
use Revolution\Bluesky\Types\RepoRef;

class FollowListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NotificationReceived $event): void
    {
        $reason = $event->reason;
        $notification = $event->notification;
        $did = data_get($notification, 'author.did');

//        $profile = Bluesky::getRecord(
//            repo: $did,
//            collection: 'app.bsky.actor.profile',
//            rkey: 'self',
//        );

        if ($reason === 'follow' && ! empty($did)) {
            $res = Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'))
                ->createLabels(
                    subject: RepoRef::to($did),
                    labels: ['artisan'],
                );

            info('follow', $notification);
            info($res->body());
        }
    }
}
