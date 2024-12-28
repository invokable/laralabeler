<?php

namespace App\Listeners;

use Revolution\AtProto\Lexicon\Enum\Graph;
use Revolution\Bluesky\Events\Jetstream\JetstreamCommitMessage;
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
    public function handle(JetstreamCommitMessage $event): void
    {
        $operation = $event->operation;
        $message = $event->message;
        $did = data_get($message, 'did');
        $collection = data_get($message, 'commit.collection');
        $subject = data_get($message, 'commit.record.subject');

        if ($operation === 'create' && $collection === Graph::Follow && $subject === config('bluesky.labeler.did')) {
            $res = Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'))
                ->createLabels(
                    subject: RepoRef::to($did),
                    labels: ['artisan'],
                );

            info('follow', $message);
        }
    }
}
