<?php

use Illuminate\Support\Facades\Artisan;
use Revolution\Bluesky\Facades\Bluesky;
use Revolution\Bluesky\Types\RepoRef;
use Revolution\Bluesky\Types\StrongRef;
use Revolution\Bluesky\Core\CBOR;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Worker;

Artisan::command('bsky:label', function () {
    $did = Bluesky::resolveHandle(config('bluesky.identifier'))['did'];

    $res = Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'))
        ->createLabels(
            subject: RepoRef::to($did),
            labels: ['artisan'],
        );

    $profile = Bluesky::client(auth: true)
        ->withHeader('atproto-accept-labelers', config('bluesky.labeler.did'))
        ->getProfile(
            actor: $did,
        );

    $this->comment($res->body());
    $this->comment($profile->body());
});

Artisan::command('bsky:label-record', function () {

    $post = Bluesky::getPost('');

    $res = Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'))
        ->createLabels(
            subject: StrongRef::to(uri: $post->json('uri'), cid: $post->json('cid')),
            labels: ['artisan'],
        );

    $this->comment($res->body());
});

Artisan::command('bsky:label-delete', function () {

    $did = Bluesky::resolveHandle(config('bluesky.identifier'))['did'];

    $res = Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'))
        ->deleteLabels(
            subject: RepoRef::to($did),
            labels: ['artisan'],
        );

    $this->comment($res->body());
});

Artisan::command('bsky:label-record-delete', function () {

    $post = Bluesky::getPost('');

    $res = Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'))
        ->deleteLabels(
            subject: StrongRef::to(uri: $post->json('uri'), cid: $post->json('cid')),
            labels: ['artisan'],
        );

    $this->comment($res->body());
});

Artisan::command('bsky:ws {cmd}', function () {
    $worker = new Worker();

    $worker->onWorkerStart = function ($worker) {
        $con = new AsyncTcpConnection('ws://laralabeler.invokable.net/xrpc/com.atproto.label.subscribeLabels:443?cursor=0');

        $con->transport = 'ssl';

        $con->onMessage = function (AsyncTcpConnection $con, $data) {
            dump($data);
            $decode = CBOR::decodeAll($data);
            dump($decode);
        };

        $con->connect();
    };

    Worker::runAll();
});
