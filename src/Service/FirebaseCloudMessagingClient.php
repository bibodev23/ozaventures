<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FirebaseCloudMessagingClient
{
    private const Scope = 'https://www.googleapis.com/auth/firebase.messaging';

    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $serviceAccount = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/firebase/firebase-service-account.json')]
        private readonly string $serviceAccountPath,
    ) {
    }

    /**
     * @param array<string, string> $data
     *
     * @return array<string, mixed>
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        $serviceAccount = $this->serviceAccount();
        $projectId = (string) ($serviceAccount['project_id'] ?? '');

        if ($projectId === '') {
            throw new \RuntimeException('Projet Firebase introuvable dans la clé serveur.');
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                    ],
                ],
            ],
        ];

        return $this->requestJson(
            sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode($projectId)),
            [
                'Authorization: Bearer ' . $this->accessToken(),
                'Content-Type: application/json',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }

        $serviceAccount = $this->serviceAccount();
        $now = time();
        $assertion = $this->signedJwt([
            'iss' => $serviceAccount['client_email'],
            'scope' => self::Scope,
            'aud' => $serviceAccount['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], (string) $serviceAccount['private_key']);

        $response = $this->requestForm((string) $serviceAccount['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Firebase n’a pas retourné de token d’accès.');
        }

        $this->accessToken = $token;
        $this->accessTokenExpiresAt = $now + (int) ($response['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function signedJwt(array $claims, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $unsigned = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
            . '.'
            . $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));

        if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Impossible de signer la requête Firebase.');
        }

        return $unsigned . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestForm(string $url, array $fields): array
    {
        return $this->requestJson(
            $url,
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query($fields),
        );
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $url, array $headers, string $body): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Impossible d’initialiser la requête Firebase.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
        ]);

        $response = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            throw new \RuntimeException('Erreur réseau Firebase : ' . $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = $decoded['error']['message'] ?? sprintf('Erreur Firebase HTTP %d.', $statusCode);

            throw new \RuntimeException((string) $message);
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceAccount(): array
    {
        if ($this->serviceAccount !== null) {
            return $this->serviceAccount;
        }

        if (!is_file($this->serviceAccountPath)) {
            throw new \RuntimeException('Clé serveur Firebase introuvable.');
        }

        $data = json_decode((string) file_get_contents($this->serviceAccountPath), true);
        if (!is_array($data)) {
            throw new \RuntimeException('Clé serveur Firebase illisible.');
        }

        foreach (['client_email', 'private_key', 'project_id', 'token_uri'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                throw new \RuntimeException(sprintf('Champ Firebase manquant : %s.', $field));
            }
        }

        $this->serviceAccount = $data;

        return $this->serviceAccount;
    }
}
