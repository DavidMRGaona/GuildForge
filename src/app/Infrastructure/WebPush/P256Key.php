<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

/**
 * Checks raw P-256 keys with OpenSSL (core#803): Web Push keys travel as bare bytes (a 65-byte
 * uncompressed point, a 32-byte private scalar), so they are wrapped in the standard key formats
 * to let OpenSSL decide. Never logs or keeps the keys.
 */
final class P256Key
{
    /** SubjectPublicKeyInfo of an id-ecPublicKey on prime256v1, up to the point's bytes. */
    private const string PUBLIC_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** ECPrivateKey (RFC 5915) version 1 with a 32-byte scalar on prime256v1. */
    private const string PRIVATE_PREFIX = '30310201010420';

    private const string PRIVATE_CURVE = 'a00a06082a8648ce3d030107';

    /**
     * Answers of pairs() for this process, by a hash of both keys: the configuration asks on every
     * request and a signature is not free. Never the keys themselves.
     *
     * @var array<string, bool>
     */
    private static array $pairs = [];

    /**
     * Whether the bytes are an uncompressed point on the P-256 curve: OpenSSL refuses any other.
     */
    public static function isPublicPoint(#[\SensitiveParameter] string $raw): bool
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
            return false;
        }

        return @openssl_pkey_get_public(self::publicPem($raw)) !== false;
    }

    /**
     * Whether the private scalar and the public point form one key pair: a signature made with the
     * first verifies with the second.
     */
    public static function pairs(#[\SensitiveParameter] string $rawPrivate, #[\SensitiveParameter] string $rawPublic): bool
    {
        return self::$pairs[hash('sha256', $rawPrivate."\0".$rawPublic)] ??= self::checkPair($rawPrivate, $rawPublic);
    }

    private static function checkPair(#[\SensitiveParameter] string $rawPrivate, #[\SensitiveParameter] string $rawPublic): bool
    {
        if (strlen($rawPrivate) !== 32 || ! self::isPublicPoint($rawPublic)) {
            return false;
        }

        $private = @openssl_pkey_get_private(self::privatePem($rawPrivate));

        if ($private === false || ! @openssl_sign('web-push-key-check', $signature, $private, OPENSSL_ALGO_SHA256)) {
            return false;
        }

        return @openssl_verify('web-push-key-check', (string) $signature, self::publicPem($rawPublic), OPENSSL_ALGO_SHA256) === 1;
    }

    private static function publicPem(string $raw): string
    {
        return self::pem('PUBLIC KEY', (hex2bin(self::PUBLIC_PREFIX)).$raw);
    }

    private static function privatePem(string $raw): string
    {
        return self::pem('EC PRIVATE KEY', (hex2bin(self::PRIVATE_PREFIX)).$raw.(hex2bin(self::PRIVATE_CURVE)));
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$label}-----\n";
    }
}
