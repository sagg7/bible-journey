<?php

namespace App\Support;

/**
 * Maps a Bible book (by canonical_order) to the matching volume in Ellen G.
 * White's "Conflict of the Ages" series, per locale. The series is itself a
 * chronological narrative commentary on Bible history, written roughly in
 * this same order, which is what makes chapter-level lookups viable.
 *
 * Ranges are a pragmatic v1 heuristic, not doctrinally precise boundaries:
 * - Genesis-1 Samuel:      Patriarchs and Prophets (ends at Saul's death)
 * - 2 Samuel-Malachi:      Prophets and Kings (best-fit bucket; weak for
 *                          Psalms/Wisdom literature, which PK doesn't cover
 *                          verse-by-verse)
 * - Matthew-John:          The Desire of Ages
 * - Acts-Jude:             The Acts of the Apostles (weak for epistle bodies,
 *                          which AA covers as historical context, not exegesis)
 * - Revelation:            The Great Controversy
 */
class EgwVolumeMap
{
    /** @var array<string, array{es: array{pubnr:int, code:string, title:string}, en: array{pubnr:int, code:string, title:string}}> */
    private const VOLUMES = [
        'PP' => [
            'es' => ['pubnr' => 1704, 'code' => 'PP', 'title' => 'Historia de los Patriarcas y Profetas'],
            'en' => ['pubnr' => 84, 'code' => 'PP', 'title' => 'Patriarchs and Prophets'],
        ],
        'PK' => [
            'es' => ['pubnr' => 217, 'code' => 'PR', 'title' => 'Profetas y Reyes'],
            'en' => ['pubnr' => 88, 'code' => 'PK', 'title' => 'Prophets and Kings'],
        ],
        'DA' => [
            'es' => ['pubnr' => 174, 'code' => 'DTG', 'title' => 'El Deseado de Todas las Gentes'],
            'en' => ['pubnr' => 130, 'code' => 'DA', 'title' => 'The Desire of Ages'],
        ],
        'AA' => [
            'es' => ['pubnr' => 198, 'code' => 'HAp', 'title' => 'Los Hechos de los Apóstoles'],
            'en' => ['pubnr' => 127, 'code' => 'AA', 'title' => 'The Acts of the Apostles'],
        ],
        'GC' => [
            'es' => ['pubnr' => 1710, 'code' => 'CS', 'title' => 'El Conflicto de los Siglos'],
            'en' => ['pubnr' => 132, 'code' => 'GC', 'title' => 'The Great Controversy'],
        ],
    ];

    /**
     * @return array{pubnr:int, code:string, title:string}|null
     */
    public static function volumeFor(int $canonicalOrder, string $locale): ?array
    {
        $key = match (true) {
            $canonicalOrder <= 9 => 'PP',   // Genesis..1 Samuel
            $canonicalOrder <= 39 => 'PK',  // 2 Samuel..Malachi
            $canonicalOrder <= 43 => 'DA',  // Matthew..John
            $canonicalOrder <= 65 => 'AA',  // Acts..Jude
            default => 'GC',                // Revelation
        };

        return self::VOLUMES[$key][$locale] ?? null;
    }
}
