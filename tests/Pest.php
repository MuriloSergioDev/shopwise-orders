<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shopwise\Kit\Auth\KeySource;
use Shopwise\Kit\Messaging\Envelope;
use Shopwise\Kit\Messaging\OutboxMessage;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

const CUSTOMER_ID = '01JBCDEFGHJKMNPQRSTVWXYZ11';

/**
 * Loads the contract example envelope for a message (shopwise-contracts/examples), optionally overriding data fields
 * and envelope fields.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $envelope
 */
function contractExample(string $typeAndVersion, array $data = [], array $envelope = []): Envelope
{
    $json = file_get_contents(base_path("vendor/shopwise/contracts/examples/{$typeAndVersion}.json"));
    /** @var array<string, mixed> $payload */
    $payload = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
    /** @var array<string, mixed> $original */
    $original = $payload['data'];
    $payload['data'] = array_replace($original, $data);

    return Envelope::fromArray(array_replace($payload, $envelope));
}

/**
 * Authorization header with an identity-style access token for `$subject`. The test key replaces identity's JWKS,
 * so no HTTP call is made.
 *
 * @param  list<string>  $roles
 * @return array{Authorization: string}
 */
function bearer(string $subject = CUSTOMER_ID, array $roles = ['customer']): array
{
    static $privateKey = null;
    static $publicKey = null;
    if ($privateKey === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];
    }

    app()->instance(KeySource::class, new readonly class($publicKey) implements KeySource
    {
        public function __construct(private string $publicKey) {}

        public function keys(bool $refresh = false): array
        {
            return ['test' => new Key($this->publicKey, 'RS256')];
        }
    });

    $token = JWT::encode([
        'iss' => 'shopwise-identity',
        'sub' => $subject,
        'email' => 'customer@shopwise.test',
        'roles' => $roles,
        'iat' => time(),
        'exp' => time() + 900,
    ], $privateKey, 'RS256', 'test');

    return ['Authorization' => "Bearer {$token}"];
}

/**
 * @return array{Authorization: string}
 */
function adminBearer(): array
{
    return bearer('01JBCDEFGHJKMNPQRSTVWXYZ99', ['customer', 'admin']);
}

/**
 * The outbox rows recorded for one message type, oldest first, as [subject, data].
 *
 * @return list<array{subject: string, data: array<string, mixed>}>
 */
function recordedEvents(string $type): array
{
    return OutboxMessage::query()->where('type', $type)->orderBy('id')->get()
        ->map(fn (OutboxMessage $message): array => ['subject' => $message->subject, 'data' => $message->envelope['data']])
        ->values()->all();
}
