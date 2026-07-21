<?php

namespace App\Support;

/**
 * Reversible obfuscation of integer ids for URLs and filters (e.g. 3 -> "k7Qm2R9x"),
 * so pages never expose sequential database ids and casual enumeration
 * (/employees/1, /employees/2, …) is defeated.
 *
 * This is NOT a security control — authorization and tenant isolation are (see
 * CompanyScope / SiteScope and the route-binding guards). It only hides the numbers.
 *
 * Implemented WITHOUT any external dependency or PHP math extension (bcmath/gmp):
 * ids up to 2^32-1 are permuted with a small key-driven Feistel cipher (a perfect
 * 1:1 mapping that scrambles the sequence), a checksum char is appended so that
 * malformed/tampered codes decode to null instead of a random valid id, and the
 * result is written in a per-deployment shuffled base-62 alphabet.
 */
class Hashid
{
    /** 6 base-62 chars fully cover the 32-bit payload (62^6 > 2^32) + 1 checksum = 7-char codes. */
    private const PAYLOAD_LEN = 6;
    private const BITS = 32;
    private const ROUNDS = 4;

    private static ?string $alphabet = null;
    private static ?string $secret = null;

    private static function boot(): void
    {
        if (self::$alphabet !== null) {
            return;
        }

        self::$secret = 'tenkofiz:'.config('app.key');

        // Deterministically shuffle a base-62 alphabet from the secret so the codes
        // look different per deployment but are stable within one.
        $chars = str_split('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = hexdec(substr(hash('sha256', self::$secret.':a:'.$i), 0, 8)) % ($i + 1);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        self::$alphabet = implode('', $chars);
    }

    public static function encode(int|string|null $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        self::boot();
        $id = (int) $id;
        if ($id < 0 || $id > 0xFFFFFFFF) {
            return null;
        }

        $payload = self::feistel($id, false);
        $code = self::toBase62($payload, self::PAYLOAD_LEN);

        return $code.self::checksumChar($code);
    }

    /** Decodes a code back to its int id, or null when it is not a valid code. */
    public static function decode(int|string|null $hash): ?int
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        self::boot();
        $hash = (string) $hash;
        if (strlen($hash) !== self::PAYLOAD_LEN + 1) {
            return null;
        }

        $code = substr($hash, 0, self::PAYLOAD_LEN);
        if (self::checksumChar($code) !== $hash[self::PAYLOAD_LEN]) {
            return null; // tampered or not one of our codes
        }

        $payload = self::fromBase62($code);
        if ($payload === null) {
            return null;
        }

        return self::feistel($payload, true);
    }

    /**
     * A balanced Feistel network over 32 bits: a reversible permutation keyed by the
     * secret, so consecutive ids map to scattered payloads. Running it with the round
     * keys reversed is its own inverse.
     */
    private static function feistel(int $value, bool $invert): int
    {
        $half = self::BITS / 2;              // 16
        $mask = (1 << $half) - 1;            // 0xFFFF
        $left = ($value >> $half) & $mask;
        $right = $value & $mask;

        $rounds = range(0, self::ROUNDS - 1);
        if ($invert) {
            $rounds = array_reverse($rounds);
        }

        foreach ($rounds as $round) {
            $f = hexdec(substr(hash('sha256', self::$secret.':f:'.$round.':'.$right), 0, 4)) & $mask;
            $next = $left ^ $f;
            $left = $right;
            $right = $next;
        }

        // The classic Feistel output swaps halves once more; undo it so encode/decode
        // stay symmetric with the reversed round order above.
        return (($right & $mask) << $half) | ($left & $mask);
    }

    private static function toBase62(int $value, int $length): string
    {
        $base = strlen(self::$alphabet);
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out = self::$alphabet[$value % $base].$out;
            $value = intdiv($value, $base);
        }

        return $out;
    }

    private static function fromBase62(string $code): ?int
    {
        $base = strlen(self::$alphabet);
        $value = 0;
        for ($i = 0, $n = strlen($code); $i < $n; $i++) {
            $pos = strpos(self::$alphabet, $code[$i]);
            if ($pos === false) {
                return null;
            }
            $value = $value * $base + $pos;
        }

        return $value;
    }

    /** One alphabet char derived from the code + secret; guards against typos/tampering. */
    private static function checksumChar(string $code): string
    {
        $sum = hexdec(substr(hash('sha256', self::$secret.':c:'.$code), 0, 8)) % strlen(self::$alphabet);

        return self::$alphabet[$sum];
    }
}
