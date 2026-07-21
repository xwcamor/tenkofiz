<?php

namespace App\Support;

use Hashids\Hashids;

/**
 * Reversible obfuscation of integer ids for URLs (e.g. 3 -> "X7gk2"), so pages
 * and filters never expose sequential database ids. This is NOT a security
 * control — authorization/tenant scoping is (see CompanyScope / SiteScope and the
 * route-binding guards) — it just hides the numbers and stops casual enumeration.
 */
class Hashid
{
    private static ?Hashids $codec = null;

    private static function codec(): Hashids
    {
        // Salt from the app key so ids are stable per deployment but not guessable.
        // min length 8 keeps short ids from producing 1-2 char codes.
        return self::$codec ??= new Hashids('tenkofiz:'.config('app.key'), 8);
    }

    public static function encode(int|string|null $id): ?string
    {
        return ($id === null || $id === '') ? null : self::codec()->encode((int) $id);
    }

    /** Decodes a hash back to its int id, or null when the value is not a valid hash. */
    public static function decode(?string $hash): ?int
    {
        if ($hash === null || $hash === '') {
            return null;
        }
        $decoded = self::codec()->decode($hash);

        return isset($decoded[0]) ? (int) $decoded[0] : null;
    }
}
