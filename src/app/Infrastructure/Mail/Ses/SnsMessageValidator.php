<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Ses;

use Illuminate\Support\Facades\Http;

final readonly class SnsMessageValidator implements SnsMessageValidatorInterface
{
    private const REQUIRED_FIELDS = ['Type', 'SignatureVersion', 'Signature', 'SigningCertURL'];

    private const NOTIFICATION_SIGNING_FIELDS = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];

    private const SUBSCRIPTION_SIGNING_FIELDS = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];

    /**
     * @param  array<string, mixed>  $message
     */
    public function isValid(array $message): bool
    {
        if (! $this->hasRequiredFields($message)) {
            return false;
        }

        if ($message['SignatureVersion'] !== '1') {
            return false;
        }

        if (! $this->isValidCertUrl($message['SigningCertURL'])) {
            return false;
        }

        return $this->verifySignature($message);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function hasRequiredFields(array $message): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! isset($message[$field]) || ! is_string($message[$field])) {
                return false;
            }
        }

        return true;
    }

    private function isValidCertUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (! isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        if ($parsed['scheme'] !== 'https') {
            return false;
        }

        return str_ends_with($parsed['host'], '.amazonaws.com');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function verifySignature(array $message): bool
    {
        $certPem = $this->fetchCertificate($message['SigningCertURL']);

        if ($certPem === null) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($certPem);

        if ($publicKey === false) {
            return false;
        }

        $signingString = $this->buildSigningString($message);
        $signature = base64_decode($message['Signature'], true);

        if ($signature === false) {
            return false;
        }

        return openssl_verify($signingString, $signature, $publicKey, OPENSSL_ALGO_SHA1) === 1;
    }

    private function fetchCertificate(string $url): ?string
    {
        $response = Http::timeout(5)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->body();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function buildSigningString(array $message): string
    {
        $type = $message['Type'] ?? '';

        $fields = $type === 'Notification'
            ? self::NOTIFICATION_SIGNING_FIELDS
            : self::SUBSCRIPTION_SIGNING_FIELDS;

        $pairs = '';

        foreach ($fields as $field) {
            if (isset($message[$field])) {
                $pairs .= $field."\n".$message[$field]."\n";
            }
        }

        return $pairs;
    }
}
