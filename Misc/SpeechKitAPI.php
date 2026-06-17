<?php

namespace Misc;

use DateTime;
use Exception;
use Model\User;
use Monolog\Logger;

class SpeechKitAPI
{
    private static string $baseUrl;
    private static string $folderId;
    private static Logger $logger;
    private static string $cacheFolder;
    private static bool $isInitialized = false;
    private static string $authUrl;
    private static string $authToken;
    public static function initialize(
        Logger $logger,
        string $baseUrl,
        string $folderId,
        string $authUrl,
        string $authToken,
        ?string $cacheFolder = null,
    ): void
    {
        self::$logger = $logger;
        self::$baseUrl = $baseUrl;
        self::$folderId = $folderId;
        self::$authUrl = $authUrl;
        self::$authToken = $authToken;
        self::$cacheFolder = $cacheFolder;
        self::$isInitialized = true;
    }

    public static function isInitialized(): bool
    {
        return self::$isInitialized;
    }

    /**
     * @throws Exception
     */
    private static function getIAMToken()
    {
        $data = json_encode([
            'yandexPassportOauthToken' => self::$authToken,
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$authUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode - $response");
        }
        return json_decode($response, true);
    }

    private static function revokeIAMToken($oldToken): void
    {
        $data = json_encode([
            'iamToken' => $oldToken
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf('%s:revoke', self::$authUrl));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'Authorization: Bearer ' . $oldToken
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    public static function textToSpeech(string $message, User $user, int $cardId): ?string
    {
        if (!file_exists(self::$cacheFolder)) {
            self::$logger->error('SpeechKitAPI - cache folder is mandatory');
            return null;
        }
        if (!$user->isVoiceMessagesEnabled()) {
            self::$logger->error('SpeechKitAPI - user does not have a right to create a voice message: ' . $user->getId());
            return null;
        }
        $tokenData = self::resolveTokenData();
        if ($tokenData === null) {
            return null;
        }
        $fromCache = self::searchInTheCache($user->getId(), $cardId);
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
                sprintf("Authorization: Bearer %s", $tokenData['iam_token']),
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

        return self::storeInTheCache($response, $user->getId(), $cardId);
    }

    private static function resolveTokenData(): ?array
    {
        $tokenData = DB::getToken() ?? ['iam_token' => null, 'iam_token_expires_at' => null];
        $expiresAt = null;
        if (!empty($tokenData['iam_token_expires_at'])) {
            try {
                $expiresAt = new DateTime($tokenData['iam_token_expires_at']);
            } catch (Exception) {
                self::$logger->warning('SpeechKitAPI - invalid expiresAt: ' . $tokenData['iam_token_expires_at']);
            }
        }
        if (empty($tokenData['iam_token']) || $expiresAt === null || (new DateTime()) > $expiresAt) {
            try {
                if (!empty($tokenData['iam_token'])) {
                    self::revokeIAMToken($tokenData['iam_token']);
                }
                $result = self::getIAMToken();
                if (empty($result['iamToken'])) {
                    self::$logger->error('SpeechKitAPI - IAM token not returned by API.');
                    return null;
                }
                $tokenData = DB::refreshToken($result);
                if ($tokenData === null) {
                    self::$logger->error('SpeechKitAPI - impossible to refresh token.');
                    return null;
                }
            } catch (Exception $e) {
                self::$logger->error(sprintf('SpeechKitAPI - %s', $e->getMessage()));
                return null;
            }
        }
        if (empty($tokenData['iam_token'])) {
            self::$logger->error('SpeechKitAPI - IAM token is empty.');
            return null;
        }

        return $tokenData;
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