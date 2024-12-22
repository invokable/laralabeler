<?php

declare(strict_types=1);

namespace App\Labeler;

use Illuminate\Support\Facades\Config;
use Revolution\Bluesky\Facades\Bluesky;
use Revolution\Bluesky\Labeler\AbstractLabeler;
use Revolution\Bluesky\Labeler\LabelDefinition;
use Revolution\Bluesky\Labeler\LabelLocale;

class ArtisanLabeler extends AbstractLabeler
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
                severity: 'inform',
                blurs: 'none',
                locales: [
                    new LabelLocale(lang: 'en', name: 'artisan', description: 'Web artisan'),
                ],
            ),
        ];
    }

    /**
     * @return array{cursor: string, labels: array{ver?: int, src: string, uri: string, cid?: string, val: string, neg?: bool, cts: string, exp?: string, sig?: mixed}}
     *
     * @link https://docs.bsky.app/docs/api/com-atproto-label-query-labels
     */
    public function queryLabels(array $uriPatterns, ?array $sources = null, ?int $limit = 50, ?string $cursor = null): array
    {
        info('queryLabels', compact('uriPatterns', 'sources', 'limit', 'cursor'));

        $res = Bluesky::getFollowers(Config::string('bluesky.labeler.identifier'));
        info('getFollowers', $res->json());

        return [];
    }

    /**
     * @return array{id: int, reasonType: string, reason: string, subject: array, reportedBy: string, createdAt: string}
     *
     * @link https://docs.bsky.app/docs/api/com-atproto-moderation-create-report
     */
    public function createReport(string $reasonType, array $subject, ?string $reason = null): array
    {
        info('createReport', compact('reasonType', 'subject', 'reason'));

        return [];
    }
}
