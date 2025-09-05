<?php

namespace Misc;

use Monolog\Logger;
use TypeError;

class DeepSeekAPI
{
    private static string $apiKey;
    private static string $baseUrl;
    private static array $assistantPrompt;
    private static Logger $logger;
    public static function initialize(string $apiKey, string $baseUrl, $assistantPrompt, Logger $logger): void
    {
        if(!is_string($assistantPrompt) && !is_array($assistantPrompt)) {
            $error = 'Expected a string or an array in the $assistantPrompt, got ' . gettype($assistantPrompt);
            self::$logger->error($error);
            throw new TypeError($error);
        }
        self::$apiKey = $apiKey;
        self::$baseUrl = $baseUrl;
        self::$logger = $logger;
        self::$assistantPrompt = is_string($assistantPrompt) ? [$assistantPrompt] : $assistantPrompt;
    }

    private static function getAssistantPromptMessage(): string
    {
        $count = count(self::$assistantPrompt);
        if($count === 1) {
            return self::$assistantPrompt[0];
        }
        return self::$assistantPrompt[rand(0, $count - 1)];
    }

    public static function request(string $message): ?array
    {
        $url = sprintf('%s/chat/completions', self::$baseUrl);
        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => self::getAssistantPromptMessage()],
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