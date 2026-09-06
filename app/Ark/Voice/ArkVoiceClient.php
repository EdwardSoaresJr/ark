<?php

namespace App\Ark\Voice;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Box → Platform ARK Voice client. Provider credentials never leave Platform.
 */
final class ArkVoiceClient
{
    public function isConfigured(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    /**
     * @return array{ok: bool, access_token?: string, identity?: string, expires_in?: int, transport?: string, supports_inbound?: bool, reason_code?: string, message?: string, correlation_id?: string, missing?: list<string>}
     */
    public function issueSession(
        ?string $identity = null,
        ?string $platform = null,
        ?string $deviceRef = null,
        ?string $correlationId = null,
    ): array {
        $cloud = PlatformConnection::current();
        $base = $cloud->baseUrl();
        $path = '/api/v1/services/voice/sessions';
        $credential = (string) $cloud->credential();
        $installationUuid = InstallationIdentity::uuid();

        $payload = array_filter([
            'identity' => $identity,
            'platform' => $platform,
            'device_ref' => $deviceRef,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
        ], fn (mixed $v): bool => $v !== null && $v !== '');

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = Str::random(24);
        $signature = hash_hmac('sha256', implode("\n", [
            $timestamp,
            $nonce,
            'POST',
            $path,
            hash('sha256', $raw),
        ]), $credential);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Ark-Installation-Id' => $installationUuid,
                'X-Ark-Timestamp' => $timestamp,
                'X-Ark-Nonce' => $nonce,
                'X-Ark-Signature' => $signature,
            ])->withBody($raw, 'application/json')
                ->timeout(20)
                ->post($base.$path);
        } catch (\Throwable $e) {
            Log::warning('ark_voice.client.http_error', [
                'correlation_id' => $payload['correlation_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason_code' => 'provider_unavailable',
                'message' => 'ARK Voice is unavailable.',
                'correlation_id' => $payload['correlation_id'] ?? null,
            ];
        }

        $json = $response->json() ?? [];
        $correlation = is_string($json['correlation_id'] ?? null)
            ? $json['correlation_id']
            : ($payload['correlation_id'] ?? null);

        if ($response->successful() && ($json['ok'] ?? false) === true) {
            return [
                'ok' => true,
                'access_token' => is_string($json['access_token'] ?? null) ? $json['access_token'] : null,
                'identity' => is_string($json['identity'] ?? null) ? $json['identity'] : null,
                'expires_in' => is_numeric($json['expires_in'] ?? null) ? (int) $json['expires_in'] : null,
                'transport' => is_string($json['transport'] ?? null) ? $json['transport'] : null,
                'supports_inbound' => (bool) ($json['supports_inbound'] ?? false),
                'correlation_id' => $correlation,
            ];
        }

        $reason = is_string($json['reason_code'] ?? null) ? $json['reason_code'] : 'rejected';
        $message = is_string($json['message'] ?? null) ? $json['message'] : 'ARK Voice rejected the session request.';

        if (in_array($reason, ['tenant_suspended', 'installation_suspended', 'installation_revoked'], true)) {
            $cloud->markSuspended();
        }

        return [
            'ok' => false,
            'reason_code' => $reason,
            'message' => $message,
            'correlation_id' => $correlation,
            'missing' => is_array($json['missing'] ?? null) ? array_values($json['missing']) : [],
        ];
    }
}
