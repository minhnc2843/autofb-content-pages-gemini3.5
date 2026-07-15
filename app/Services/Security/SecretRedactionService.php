<?php

namespace App\Services\Security;

use App\Models\PendingSecret;
use Carbon\Carbon;

class SecretRedactionService
{
    /**
     * Detect Facebook Page Access tokens in text.
     */
    public function detectFacebookAccessToken(string $text): array
    {
        $secrets = [];

        // Match EAA tokens (typically starts with EAA, length 20+)
        if (preg_match_all('/(EAA[a-zA-Z0-9]{20,})/', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $token = $match[0];
                $offset = $match[1];
                $secrets[] = [
                    'type' => 'facebook_page_access_token',
                    'value' => $token,
                    'start' => $offset,
                    'end' => $offset + strlen($token),
                ];
            }
        }

        // Match assignment style (e.g. access_token=..., FACEBOOK_PAGE_ACCESS_TOKEN: ...)
        // but avoid double matching if the token is already caught by EAA regex.
        $patterns = [
            '/FACEBOOK_PAGE_ACCESS_TOKEN\s*=\s*([a-zA-Z0-9_\-]+)/i',
            '/access_token\s*[:=]\s*([a-zA-Z0-9_\-]+)/i',
            '/token\s*[:=]\s*([a-zA-Z0-9_\-]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $token = $match[0];
                    $offset = $match[1];

                    // Ignore short tokens that are likely normal words
                    if (strlen($token) < 8) {
                        continue;
                    }

                    // Check if already captured
                    $alreadyCaptured = false;
                    foreach ($secrets as $existing) {
                        if ($existing['value'] === $token) {
                            $alreadyCaptured = true;
                            break;
                        }
                    }

                    if (!$alreadyCaptured) {
                        $secrets[] = [
                            'type' => 'facebook_page_access_token',
                            'value' => $token,
                            'start' => $offset,
                            'end' => $offset + strlen($token),
                        ];
                    }
                }
            }
        }

        return $secrets;
    }

    /**
     * Redact secrets from text.
     */
    public function redactSecrets(string $text): string
    {
        $secrets = $this->detectFacebookAccessToken($text);

        // Sort descending by start to avoid shifting indices during string replacement
        usort($secrets, function ($a, $b) {
            return $b['start'] <=> $a['start'];
        });

        foreach ($secrets as $secret) {
            $text = substr_replace(
                $text,
                '[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]',
                $secret['start'],
                $secret['end'] - $secret['start']
            );
        }

        return $text;
    }

    /**
     * Extract secrets from text.
     */
    public function extractSecrets(string $text): array
    {
        return $this->detectFacebookAccessToken($text);
    }

    /**
     * Extract, redact, and save secrets to pending_secrets.
     */
    public function extractAndSaveSecrets(string $text, ?int $chatSessionId = null): array
    {
        $secrets = $this->detectFacebookAccessToken($text);
        $redactedText = $text;

        // Sort descending by start
        usort($secrets, function ($a, $b) {
            return $b['start'] <=> $a['start'];
        });

        $pendingSecretIds = [];

        foreach ($secrets as $secret) {
            $redactedText = substr_replace(
                $redactedText,
                '[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]',
                $secret['start'],
                $secret['end'] - $secret['start']
            );

            // Save encrypted token to pending_secrets
            $pending = PendingSecret::create([
                'user_session_id' => session()->getId(),
                'ai_chat_session_id' => $chatSessionId,
                'secret_type' => $secret['type'],
                'encrypted_value' => $secret['value'],
                'redacted_label' => '[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]',
                'expires_at' => Carbon::now()->addMinutes(30),
            ]);

            $pendingSecretIds[] = $pending->id;
        }

        return [
            'redacted_text' => $redactedText,
            'pending_secret_ids' => $pendingSecretIds,
            'secrets' => $secrets,
        ];
    }
}
