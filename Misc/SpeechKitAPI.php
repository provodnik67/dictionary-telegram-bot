<?php

namespace Misc;

use Monolog\Logger;

class SpeechKitAPI
{
    private static string $token;
    private static string $baseUrl;
    private static string $folderId;
    private static Logger $logger;
    private static bool $isInitialized = false;
    public static function initialize(Logger $logger, string $token, string $baseUrl, string $folderId): void
    {
        self::$logger = $logger;
        self::$token = $token;
        self::$baseUrl = $baseUrl;
        self::$folderId = $folderId;
        self::$isInitialized = true;
    }

    public static function isInitialized(): bool
    {
        return self::$isInitialized;
    }

    // @todo видимо отдавать как строку и отправлять как аудио-сообщение
    public static function textToSpeech(string $message): void
    {
        $url = sprintf('%s:synthesize', self::$baseUrl);
        $data = [
            'text' => $message,
            'lang' => 'en-EN',
            'voice' => 'john',
            'folderId' => self::$folderId,
        ];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                sprintf("Authorization: Bearer %s", self::$token),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            self::$logger->error('SpeechKitAPI - cURL error: ' . $error);
            return;
        }
        if ($httpCode !== 200) {
            self::$logger->error('SpeechKitAPI - API request failed with HTTP code: ' . $httpCode);
            return;
        }
        file_put_contents('speech.ogg', $response);
    }
}