<?php

namespace App\Listeners;

use Revolution\AtProto\Lexicon\Enum\Graph;
use Revolution\Bluesky\Events\Jetstream\JetstreamCommitMessage;
use Revolution\Bluesky\Facades\Bluesky;
use Revolution\Bluesky\Labeler\Labeler;
use Revolution\Bluesky\Session\LegacySession;
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

        if ($operation === 'create' && $collection === Graph::Follow->value && $subject === config('bluesky.labeler.did')) {
            Labeler::log(self::class, $message);

            $labeler_session = cache('labeler_session');
            if (empty($labeler_session)) {
                Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'));
                $labeler_session = Bluesky::agent()->session()->toArray();
                cache()->forever('labeler_session', $labeler_session);
            }

            Bluesky::withToken(LegacySession::create($labeler_session));

            if (! Bluesky::check()) {
                Bluesky::refreshSession();
            }

            $res = Bluesky::createLabels(
                subject: RepoRef::to($did),
                labels: ['artisan'],
            );

            Labeler::log(self::class, $res->json());
        }
    }
}
