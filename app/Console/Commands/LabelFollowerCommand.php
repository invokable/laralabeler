<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Revolution\Bluesky\Facades\Bluesky;
use Revolution\Bluesky\Types\RepoRef;

class LabelFollowerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bsky:label-follower';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Label to all followers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cursor = null;

        Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'));

        do {
            $res = Bluesky::client()
                ->withHeader('atproto-accept-labelers', config('bluesky.labeler.did'))
                ->getFollowers(
                    actor: config('bluesky.labeler.did'),
                    cursor: $cursor,
                );

            $res->collect('followers')
                ->reject(function ($follower) {
                    return Arr::has($follower, ['labels.0.val']);
                })->each(function ($follower) {
                    $res = Bluesky::createLabels(
                        subject: RepoRef::to(data_get($follower, 'did')),
                        labels: ['artisan'],
                    );

                    $this->line($res->body());
                });

            $cursor = $res->json('cursor');
        } while (! empty($cursor));

        return 0;
    }
}
