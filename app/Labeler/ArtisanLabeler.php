<?php

declare(strict_types=1);

namespace App\Labeler;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;
use phpseclib3\Crypt\EC;
use Revolution\Bluesky\Core\CBOR;
use Revolution\Bluesky\Crypto\DidKey;
use Revolution\Bluesky\Crypto\JsonWebToken;
use Revolution\Bluesky\Crypto\Signature;
use Revolution\Bluesky\Facades\Bluesky;
use Revolution\Bluesky\Labeler\AbstractLabeler;
use Revolution\Bluesky\Labeler\LabelDefinition;
use Revolution\Bluesky\Labeler\Labeler;
use Revolution\Bluesky\Labeler\LabelerException;
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
    private const VERIFY = false;

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
     * @throws LabelerException
     */
    public function subscribeLabels(?int $cursor): iterable
    {
        if (is_null($cursor)) {
            return null;
        }

        // Always throw a LabelerException when returning an error response.
        if ($cursor > Label::max('id')) {
            throw new LabelerException('FutureCursor', 'Cursor is in the future');
        }

        foreach (Label::oldest()->where('id', '>', $cursor)->lazy() as $label) {
            $arr = $label->toArray();
            $arr = Labeler::formatLabel($arr);

            // verify is optional.
            if (self::VERIFY && $this->verify($arr) === false) {
                Labeler::log('subscribeLabels: verify failed', $arr);
                continue;
            } else {
                Labeler::log('subscribeLabels: verify success', $arr);
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
     * @throws LabelerException
     *
     * @link https://docs.bsky.app/docs/api/tools-ozone-moderation-emit-event
     */
    public function emitEvent(Request $request, ?string $did, ?string $token): iterable
    {
        $type = data_get($request->input('event'), '$type');
        if ($type !== 'tools.ozone.moderation.defs#modEventLabel') {
            throw new LabelerException('InvalidRequest', 'Unsupported event type');
        }

        $subject = $request->input('subject');
        $uri = data_get($subject, 'uri', data_get($subject, 'did'));
        $cid = data_get($subject, 'cid');

        $createLabelVals = (array) data_get($request->input('event'), 'createLabelVals');
        $negateLabelVals = (array) data_get($request->input('event'), 'negateLabelVals');

        foreach ($createLabelVals as $val) {
            yield $this->createUnsignedLabel($uri, $cid, $val);
        }

        foreach ($negateLabelVals as $val) {
            Labeler::log('negateLabelVals', Arr::wrap($val));

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
            // If you don't want to store microseconds in the database, set this to 0.
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
        Labeler::log('createReport', $request->all());
        Labeler::log('createReport header', $request->header());

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
        } elseif (data_get($subject, '$type') === 'com.atproto.repo.strongRef') {
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
        Labeler::log('queryLabels', $request->all());
        Labeler::log('queryLabels header', $request->header());

        //$uriPatterns = Arr::wrap($request->input('uriPatterns', '*'));
        $limit = Number::clamp($request->input('limit', 1), min: 1, max: 50);

        $labels = Label::latest()->limit($limit)->get();

        return [
            'cursor' => (string) $labels->isNotEmpty() ? $labels->first()->id : '',
            'labels' => collect($labels->toArray())
                ->map(fn ($label) => Labeler::formatLabel($label))
                ->toArray(),
        ];
    }
}
