<?php

namespace Misc;

use Monolog\Logger;

class DeepSeekAPI
{
    private static string $apiKey;
    private static string $baseUrl;
    private static string $assistantPrompt;
    private static Logger $logger;
    public static function initialize(string $apiKey, string $baseUrl, string $assistantPrompt, Logger $logger): void
    {
        self::$apiKey = $apiKey;
        self::$baseUrl = $baseUrl;
        self::$logger = $logger;
        self::$assistantPrompt = $assistantPrompt;
    }

    public static function request(string $message): ?array
    {
        $url = sprintf('%s/chat/completions', self::$baseUrl);
        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => self::$assistantPrompt],
                ['role' => 'user', 'content' => $message]
            ],
            'stream' => false
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . self::$apiKey
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            self::$logger->error('DeepSeekAPI - cURL error: ' . $error);
            return null;
        }

        if ($httpCode !== 200) {
            self::$logger->error('DeepSeekAPI - API request failed with HTTP code: ' . $httpCode);
            return null;
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::$logger->error('DeepSeekAPI - Invalid JSON response');
            return null;
        }

        return $result;
    }
}