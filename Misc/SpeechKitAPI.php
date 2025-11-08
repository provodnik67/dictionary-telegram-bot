<?php

namespace Misc;

use Model\User;
use Monolog\Logger;

class SpeechKitAPI
{
    private static string $token;
    private static string $baseUrl;
    private static string $folderId;
    private static Logger $logger;
    private static string $cacheFolder;
    private static bool $isInitialized = false;
    public static function initialize(
        Logger $logger,
        string $token,
        string $baseUrl,
        string $folderId,
        ?string $cacheFolder = null
    ): void
    {
        self::$logger = $logger;
        self::$token = $token;
        self::$baseUrl = $baseUrl;
        self::$folderId = $folderId;
        self::$cacheFolder = $cacheFolder;
        self::$isInitialized = true;
    }

    public static function isInitialized(): bool
    {
        return self::$isInitialized;
    }

    // @todo проверить и отрефакторить
    public static function textToSpeech(string $message, int $userId, int $cardId): ?string
    {
        if(!file_exists(self::$cacheFolder)) {
            self::$logger->error('SpeechKitAPI - cache folder is mandatory');
            return null;
        }
        $user = DB::getOrCreateUser($userId, true);
        if(!$user instanceof User) {
            self::$logger->error('SpeechKitAPI - user does not exist: ' . $userId);
            return null;
        }
        if(!$user->isVoiceMessagesEnabled()) {
            self::$logger->error('SpeechKitAPI - user does not have a right to create a voice message: ' . $userId);
            return null;
        }
        $fromCache = self::searchInTheCache($userId, $cardId);
        if(!is_null($fromCache)) {
            return $fromCache;
        }
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
            return null;
        }
        if ($httpCode !== 200) {
            self::$logger->error('SpeechKitAPI - API request failed with HTTP code: ' . $httpCode);
            return null;
        }

        return self::storeInTheCache($response, $userId, $cardId);
    }

    private static function storeInTheCache($voiceMessage, int $userId, int $cardId): ?string
    {
        if(!file_exists(self::$cacheFolder)) {
            return null;
        }
        $userFolder = sprintf('%s/%d', self::$cacheFolder, $userId);
        $fileFolder = sprintf('%s/%d', $userFolder, $cardId);
        $path = sprintf('%s/speech.ogg', $fileFolder);

        if(!file_exists($userFolder)) {
            if (!mkdir($userFolder)) {
                self::$logger->error(sprintf('SpeechKitAPI - impossible to create a folder: %s', $userFolder));
                return null;
            }
        }
        if(!file_exists($fileFolder)) {
            if (!mkdir($fileFolder)) {
                self::$logger->error(sprintf('SpeechKitAPI - impossible to create a folder: %s', $fileFolder));
                return null;
            }
        }
        if(!file_exists($path)) {
            if(file_put_contents($path, $voiceMessage)) {
                return $path;
            }
        }
        return null;
    }

    private static function searchInTheCache(int $userId, int $cardId): ?string
    {
        $path = sprintf('%s/%d/%d/speech.ogg', self::$cacheFolder, $userId, $cardId);
        if(is_readable($path)) {
            return $path;
        }
        return null;
    }

    // todo
    public function clearCache(?string $folder = null): void
    {

    }
}