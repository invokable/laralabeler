<?php

declare(strict_types=1);

namespace App\Labeler;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use phpseclib3\Crypt\EC;
use Revolution\Bluesky\Core\CBOR;
use Revolution\Bluesky\Crypto\DidKey;
use Revolution\Bluesky\Crypto\JsonWebToken;
use Revolution\Bluesky\Crypto\Signature;
use Revolution\Bluesky\Facades\Bluesky;
use Revolution\Bluesky\Labeler\AbstractLabeler;
use Revolution\Bluesky\Labeler\LabelDefinition;
use Revolution\Bluesky\Labeler\Labeler;
use Revolution\Bluesky\Labeler\LabelLocale;
use Revolution\Bluesky\Labeler\SavedLabel;
use Revolution\Bluesky\Labeler\SignedLabel;
use Revolution\Bluesky\Labeler\Response\SubscribeLabelResponse;
use Revolution\Bluesky\Labeler\UnsignedLabel;
use Revolution\Bluesky\Support\DidDocument;
use Revolution\Bluesky\Types\RepoRef;
use Revolution\Bluesky\Types\StrongRef;

readonly class ArtisanLabeler extends AbstractLabeler
{
    /**
     * Label definitions.
     *
     * @return array<LabelDefinition>
     */
    public function labels(): array
    {
        return [
            new LabelDefinition(
                identifier: 'artisan',
                locales: [
                    new LabelLocale(
                        lang: 'en',
                        name: 'artisan',
                        description: 'Web artisan'),
                ],
                severity: 'inform',
                blurs: 'none',
                defaultSetting: 'warn',
                adultOnly: false,
            ),
        ];
    }

    /**
     * @return iterable<SubscribeLabelResponse>
     *
     * @throw LabelerException
     */
    public function subscribeLabels(?int $cursor): iterable
    {
        //info('subscribeLabels', ['cursor' => $cursor]);

        if (is_null($cursor)) {
            return null;
        }

        foreach (Label::where('id', '>', $cursor)->lazy() as $label) {
            $arr = $label->toArray();
            $arr = Labeler::formatLabel($arr);

            //info('subscribeLabels arr', $arr);

            if ($this->verify($arr) === false) {
                info('subscribeLabels: verify failed', $arr);
                continue;
                //} else {
                //info('subscribeLabels: verify success', $arr);
            }

            yield new SubscribeLabelResponse(
                seq: $label->id,
                labels: [$arr],
            );
        }
    }

    protected function verify(array $signed): bool
    {
        $sig = data_get($signed, 'sig.$bytes');

        $sig = base64_decode($sig);
        $sig = Signature::fromCompact($sig);

        $unsigned = Arr::except($signed, 'sig');
        //info('unsigned', $unsigned);

        $cbor = CBOR::encode($unsigned);

        $did = Config::string('bluesky.labeler.did');

        /** @var array $didKey */
        $didKey = cache()->remember(
            key: 'bluesky:labeler:did:key:'.$did,
            ttl: now()->addDay(),
            callback: fn () => DidKey::parse(DidDocument::make(Bluesky::identity()->resolveDID($did)->json())->publicKey('atproto_label'))->toArray(),
        );

        $pk = EC::loadPublicKey(data_get($didKey, 'key'));

        return $pk->verify($cbor, $sig);
    }

    /**
     * @return iterable<UnsignedLabel>
     *
     * @link https://docs.bsky.app/docs/api/tools-ozone-moderation-emit-event
     */
    public function emitEvent(Request $request, ?string $did, ?string $token): iterable
    {
        $subject = $request->input('subject');
        $uri = data_get($subject, 'uri', data_get($subject, 'did'));
        $cid = data_get($subject, 'cid');

        $createLabelVals = (array) data_get($request->input('event'), 'createLabelVals');
        $negateLabelVals = (array) data_get($request->input('event'), 'negateLabelVals');

        foreach ($createLabelVals as $val) {
            yield $this->createUnsignedLabel($uri, $cid, $val);
        }

        foreach ($negateLabelVals as $val) {
            info('negateLabelVals', Arr::wrap($val));
            yield $this->createUnsignedLabel($uri, $cid, $val, true);
        }
    }

    private function createUnsignedLabel(string $uri, ?string $cid, string $val, bool $neg = false): UnsignedLabel
    {
        return new UnsignedLabel(
            uri: $uri,
            cid: $cid,
            val: $val,
            src: Config::string('bluesky.labeler.did'),
            cts: now()->micro(0)->toISOString(),
            exp: null,
            neg: $neg,
        );
    }

    public function saveLabel(SignedLabel $signed, string $sign): ?SavedLabel
    {
        $saved = Label::create($signed->toArray());

        return new SavedLabel(
            $saved->id,
            $signed,
        );
    }

    /**
     * @return array{id: int, reasonType: string, reason: string, subject: array, reportedBy: string, createdAt: string}
     *
     * @link https://docs.bsky.app/docs/api/com-atproto-moderation-create-report
     */
    public function createReport(Request $request): array
    {
        info('createReport', $request->all());
        info('createReport header', $request->header());

        $reasonType = $request->input('reasonType');
        if ($reasonType !== 'com.atproto.moderation.defs#reasonAppeal') {
            return [];
        }

        $jwt = $request->bearerToken();
        [, $payload] = JsonWebToken::explode($jwt);
        $reportedBy = data_get($payload, 'iss');

        $reason = $request->input('reason', '');
        $subject = $request->input('subject');

        if (data_get($subject, '$type') === 'com.atproto.admin.defs#repoRef') {
            $did = data_get($subject, 'did');
            $delete = RepoRef::to($did);
        } elseif (data_get($subject, '$type') === 'com.atproto.admin.defs#repoRef') {
            $uri = data_get($subject, 'uri');
            $cid = data_get($subject, 'cid');
            $delete = StrongRef::to($uri, $cid);
        } else {
            return [];
        }

        $res = Bluesky::login(config('bluesky.labeler.identifier'), config('bluesky.labeler.password'))
            ->deleteLabels(
                subject: $delete,
                labels: ['artisan'],
            );

        return [
            'id' => $res->json('id'),
            'reasonType' => $reasonType,
            'reason' => $reason,
            'subject' => $subject,
            'reportedBy' => $reportedBy,
            'createdAt' => now()->toISOString(),
        ];
    }

    /**
     * @return array{cursor: string, labels: array{ver?: int, src: string, uri: string, cid?: string, val: string, neg?: bool, cts: string, exp?: string, sig?: mixed}}
     *
     * @link https://docs.bsky.app/docs/api/com-atproto-label-query-labels
     */
    public function queryLabels(Request $request): array
    {
        info('queryLabels', $request->all());
        info('queryLabels header', $request->header());

        $uriPatterns = Arr::wrap($request->input('uriPatterns', '*'));
        $limit = max(min($request->input('limit', 10), 250), 1);

        $labels = Label::latest()->limit($limit)
//            ->unless($uriPatterns === ['*'], function (Builder $query) use ($uriPatterns) {
//                foreach ($uriPatterns as $pattern) {
//                    $query->orWhereLike('uri', Str::remove('*', $pattern).'%');
//                }
//            }
//            )
            ->get();

        return [
            'cursor' => (string) $labels->isNotEmpty() ? $labels->first()->id : '',
            'labels' => collect($labels->toArray())
                ->map(fn ($label) => Labeler::formatLabel($label))
                ->toArray(),
        ];
    }
}
