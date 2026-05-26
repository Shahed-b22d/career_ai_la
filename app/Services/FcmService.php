<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private string $projectId;
    private array  $credentials;

    public function __construct()
    {
        $this->projectId = env('FIREBASE_PROJECT_ID', 'career-ai-shahed');

        $credFile = base_path(env('FIREBASE_CREDENTIALS', 'career-ai-shahed-da65f470655a.json'));
        $this->credentials = json_decode(file_get_contents($credFile), true);
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (empty($token)) return false;

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        // Convert all data values to strings (FCM v1 requirement)
        $stringData = array_map('strval', $data);

        try {
            $response = Http::withToken($accessToken)
                ->post($url, [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body'  => $body,
                        ],
                        'data'          => $stringData,
                        'android'       => [
                            'priority' => 'high',
                            'notification' => [
                                'sound'        => 'default',
                                'channel_id'   => 'career_ai_channel',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('FCM v1 error: ' . $response->status() . ' — ' . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error('FCM Exception: ' . $e->getMessage());
            return false;
        }
    }

    public function sendMultiple(array $tokens, string $title, string $body, array $data = []): void
    {
        foreach (array_filter($tokens) as $token) {
            $this->send($token, $title, $body, $data);
        }
    }

    // ─── JWT / OAuth2 Access Token ───────────────────────────────────────────

    private function getAccessToken(): ?string
    {
        // Cache for 55 minutes (token expires in 60)
        return Cache::remember('fcm_access_token', 3300, function () {
            return $this->fetchAccessToken();
        });
    }

    private function fetchAccessToken(): ?string
    {
        try {
            $now = time();
            $exp = $now + 3600;

            $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = $this->base64url(json_encode([
                'iss'   => $this->credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $exp,
            ]));

            $signingInput = "{$header}.{$payload}";

            // Sign with private key
            $privateKey = openssl_pkey_get_private($this->credentials['private_key']);
            openssl_sign($signingInput, $signature, $privateKey, 'SHA256');
            $jwt = "{$signingInput}." . $this->base64url($signature);

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('FCM token fetch failed: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('FCM JWT error: ' . $e->getMessage());
            return null;
        }
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
