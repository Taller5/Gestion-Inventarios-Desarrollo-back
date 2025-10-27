<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class HaciendaTokenService
{
    public function getAccessToken(): string
    {
    // Solo staging: forzar entorno a 'stag'
    $env = 'stag';
        $username = (string) (config('services.hacienda.username') ?? env('HACIENDA_USERNAME', ''));
        $password = (string) (config('services.hacienda.password') ?? env('HACIENDA_PASSWORD', ''));

        if ($username === '' || $password === '') {
            throw new \RuntimeException('Hacienda API credentials are not configured (HACIENDA_USERNAME/PASSWORD).');
        }

        $cacheKey = 'hacienda_token_' . md5($env . '|' . $username);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['access_token']) && ($cached['expires_at'] ?? 0) > time() + 30) {
            return $cached['access_token'];
        }

    // URL de token solo para staging (realm rut-stag)
        $tokenUrl = (string) (config('services.hacienda.token_url')
            ?? config('services.hacienda.token_url_stag')
            ?? env('HACIENDA_TOKEN_URL', 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut-stag/protocol/openid-connect/token'));

    // Client id solo para staging
        $clientId = (string) (config('services.hacienda.client_id')
            ?? (config('services.hacienda.client_id_stag') ?? 'api-stag')
            ?? env('HACIENDA_CLIENT_ID', 'api-stag'));

        $http = new Client([ 'timeout' => 20 ]);
        $response = $http->post($tokenUrl, [
            'headers' => [ 'Accept' => 'application/json' ],
            'form_params' => [
                'grant_type' => 'password',
                'client_id' => $clientId,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true) ?: [];
        if (empty($data['access_token']) || empty($data['expires_in'])) {
            throw new \RuntimeException('Failed to obtain Hacienda access token.');
        }

        $ttl = max(60, (int) $data['expires_in'] - 60);
        Cache::put($cacheKey, [
            'access_token' => $data['access_token'],
            'expires_at' => time() + $ttl,
        ], $ttl);

        return $data['access_token'];
    }
}
