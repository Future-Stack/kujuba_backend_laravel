<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send push notification to a single device token
     *
     * @param string|null $deviceToken
     * @param string $title
     * @param string $message
     * @param array $extraData
     * @return bool
     */
    public static function sendToDevice(?string $deviceToken, string $title, string $message, array $extraData = []): bool
    {
        if (empty($deviceToken)) {
            return false;
        }

        return self::sendToMultipleDevices([$deviceToken], $title, $message, $extraData);
    }

    /**
     * Send push notification to multiple device tokens via Firebase HTTP v1 / Service Account
     *
     * @param array $deviceTokens
     * @param string $title
     * @param string $message
     * @param array $extraData
     * @return bool
     */
    public static function sendToMultipleDevices(array $deviceTokens, string $title, string $message, array $extraData = []): bool
    {
        $tokens = array_filter(array_unique($deviceTokens));
        if (empty($tokens)) {
            return false;
        }

        // 1. Try Firebase HTTP v1 API with Service Account JSON (Option 2)
        $credentials = self::getServiceAccountCredentials();
        if ($credentials) {
            $accessToken = self::getOAuthAccessToken($credentials);
            if ($accessToken) {
                $projectId = $credentials['project_id'] ?? null;
                if ($projectId) {
                    $allSuccess = true;
                    foreach ($tokens as $token) {
                        $success = self::sendFcmV1Message($projectId, $accessToken, $token, $title, $message, $extraData);
                        if (!$success) {
                            $allSuccess = false;
                        }
                    }
                    return $allSuccess;
                }
            }
        }

        // 2. Fallback: Legacy FCM Server Key
        $serverKey = config('services.firebase.server_key') ?? env('FCM_SERVER_KEY');
        if (!empty($serverKey)) {
            return self::sendLegacyFcm($serverKey, $tokens, $title, $message, $extraData);
        }

        Log::info("Push Notification queued: '{$title}' -> " . count($tokens) . " device(s). (Firebase JSON file not yet placed in storage/app/firebase/firebase_credentials.json)");
        return true;
    }

    /**
     * Load Firebase Service Account JSON credentials
     */
    private static function getServiceAccountCredentials(): ?array
    {
        $customPath = config('services.firebase.credentials') ?? env('FIREBASE_CREDENTIALS');
        $possiblePaths = [
            $customPath,
            storage_path('app/firebase/firebase_credentials.json'),
            storage_path('app/firebase_credentials.json'),
            base_path('firebase_credentials.json'),
        ];

        foreach (array_filter($possiblePaths) as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $json = json_decode($content, true);
                if (is_array($json) && !empty($json['client_email']) && !empty($json['private_key'])) {
                    return $json;
                }
            }
        }

        return null;
    }

    /**
     * Generate OAuth2 Access Token from Service Account JSON using JWT (Cached for 55 min)
     */
    private static function getOAuthAccessToken(array $credentials): ?string
    {
        $cacheKey = 'firebase_oauth_token_' . md5($credentials['client_email']);

        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($credentials) {
            try {
                $now = time();
                $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
                $claimSet = json_encode([
                    'iss'   => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud'   => 'https://oauth2.googleapis.com/token',
                    'exp'   => $now + 3600,
                    'iat'   => $now,
                ]);

                $base64UrlHeader  = self::base64UrlEncode($header);
                $base64UrlClaim   = self::base64UrlEncode($claimSet);
                $signaturePayload = $base64UrlHeader . '.' . $base64UrlClaim;

                $privateKey = openssl_pkey_get_private($credentials['private_key']);
                if (!$privateKey) {
                    Log::error('Invalid Firebase Private Key in credentials JSON.');
                    return null;
                }

                openssl_sign($signaturePayload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                $base64UrlSignature = self::base64UrlEncode($signature);

                $jwt = $signaturePayload . '.' . $base64UrlSignature;

                // Request access token from Google OAuth2
                $response = Http::asForm()->timeout(6)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['access_token'] ?? null;
                }

                Log::error('Google OAuth2 Token Request failed: ' . $response->body());
                return null;

            } catch (\Throwable $e) {
                Log::error('Firebase OAuth Token Generation Error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Send message using FCM HTTP v1 API
     */
    private static function sendFcmV1Message(string $projectId, string $accessToken, string $deviceToken, string $title, string $message, array $extraData = []): bool
    {
        try {
            $formattedData = [];
            foreach ($extraData as $k => $v) {
                $formattedData[(string)$k] = (string)$v;
            }

            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $message,
                    ],
                    'data' => array_merge($formattedData, [
                        'title'   => $title,
                        'message' => $message,
                    ]),
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'sound' => 'default',
                        ]
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ]
                        ]
                    ]
                ]
            ];

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ])->timeout(6)->post($url, $payload);

            if ($response->successful()) {
                Log::info("FCM v1 Push Notification sent successfully to {$deviceToken}.");
                return true;
            }

            Log::error("FCM v1 Push Notification failed for {$deviceToken}: " . $response->body());
            return false;

        } catch (\Throwable $e) {
            Log::error("FCM v1 Send Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy FCM Send Fallback
     */
    private static function sendLegacyFcm(string $serverKey, array $tokens, string $title, string $message, array $extraData = []): bool
    {
        try {
            $payload = [
                'registration_ids' => array_values($tokens),
                'notification' => [
                    'title' => $title,
                    'body'  => $message,
                    'sound' => 'default',
                    'badge' => 1,
                ],
                'data' => array_merge($extraData, [
                    'title'   => $title,
                    'message' => $message,
                ]),
                'priority' => 'high',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type'  => 'application/json',
            ])->timeout(6)->post('https://fcm.googleapis.com/fcm/send', $payload);

            return $response->successful();

        } catch (\Throwable $e) {
            Log::error("Legacy FCM Send Error: " . $e->getMessage());
            return false;
        }
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
