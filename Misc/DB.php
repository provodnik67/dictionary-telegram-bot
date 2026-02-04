<?php

namespace Misc;

use DateTime;
use Exception;
use Model\Message;
use Model\User;
use Monolog\Logger;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Misc\Config;

class DB
{
    private const CARDS = 'cards';

    private const USERS = 'dictionary_user';

    private const COMMAND_IN_PROCESS = 'command_in_process';

    private const SETTINGS = 'dictionary_settings';

    protected static array $mysql_credentials = [];

    protected static PDO $pdo;

    private static Logger $logger;

    /**
     * @throws Exception
     */
    public static function initialize(array $credentials, string $encoding = 'utf8', int $errMode = PDO::ERRMODE_WARNING, Logger $logger): PDO
    {
        if (empty($credentials)) {
            throw new Exception('MySQL credentials not provided!');
        }
        if (isset($credentials['unix_socket'])) {
            $dsn = 'mysql:unix_socket=' . $credentials['unix_socket'];
        } else {
            $dsn = 'mysql:host=' . $credentials['host'];
        }
        $dsn .= ';dbname=' . $credentials['database'];

        if (!empty($credentials['port'])) {
            $dsn .= ';port=' . $credentials['port'];
        }
        $pdo = null;
        $options = [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $encoding];
        self::$logger = $logger;
        try {
            $pdo = new PDO($dsn, $credentials['user'], $credentials['password'], $options);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, $errMode);
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }

        self::$pdo = $pdo;
        self::$mysql_credentials = $credentials;

        if (self::isDbConnected()) {
            self::onInitialize();
        }

        return self::$pdo;
    }

    private static function isDbConnected(): bool
    {
        return self::$pdo !== null;
    }

    private static function fillMessages(PDOStatement $statement): array
    {
        $messages = [];
        $keys = ['ru', 'translation'];
        while ($row = $statement->fetch()) {
            try {
                $message = Message::factory($row);
                $key = rand(0, 1);
                $message->setText(sprintf('%s%s --> <span class="tg-spoiler">%s</span>', ($row['complicated'] ? '** ' : ''), $row[$keys[$key]], $row[$keys[abs($key - 1)]]));
                $messages[] = $message;
            } catch (Exception $e) {
                self::$logger->error($e->getMessage());
            }
        }
        return $messages;
    }

    private static function onInitialize(): void
    {
        $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        try {
            $stmt = self::$pdo->prepare(sprintf('DELETE FROM `%s` WHERE created < :five_minutes_ago', self::COMMAND_IN_PROCESS));
            $stmt->bindParam(':five_minutes_ago', $fiveMinutesAgo);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }

    private static function resetDictionary(int $userId, bool $hard): void
    {
        try {
            $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET shown = false WHERE user_id = :user_id %s', self::CARDS, ($hard ? 'AND complicated = :complicated' : '')));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            if($hard) {
                $stmt->bindValue(':complicated', true, PDO::PARAM_BOOL);
            }
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }

    public static function getSpecificNumberOfWords(User $user, int $number, bool $hard = false, ?int $categoryId = null): array
    {
        if (!self::isDbConnected()) {
            return [];
        }

        $statistics = self::getStatistic($user->getId());
        if(
            ($hard && $statistics['COMPLICATED'] === $statistics['COMPLICATED_SHOWN'])
            || ($statistics['TOTAL'] === $statistics['TOTAL_SHOWN'])
        ) {
            self::resetDictionary($user->getId(), $hard);
        }

        $whereStatement = '';
        if($hard) {
            $whereStatement .= ' AND `complicated` = :complicated ';
        }
        if($categoryId) {
            $whereStatement .= ' AND `category_id` = :category_id ';
        }

        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT * FROM %s WHERE `user_id` = :user_id %s AND `shown` = :shown AND `deleted` = false AND `language` = :lang ORDER BY RAND() LIMIT :limit', self::CARDS, $whereStatement));
            $stmt->bindValue(':limit', $number, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $user->getId(), PDO::PARAM_INT);
            $stmt->bindValue(':lang', $user->getLanguage());
            if($hard) {
                $stmt->bindValue(':complicated', true, PDO::PARAM_BOOL);
            }
            if($categoryId) {
                $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':shown', false, PDO::PARAM_BOOL);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        $messages = self::fillMessages($stmt);
        $reset = false;
        if(
            $messages
            && $number > count($messages)
            && ($hard ? $statistics['COMPLICATED'] : $statistics['TOTAL']) >= $number
        ) {
            try {
                $stmt = self::$pdo->prepare(sprintf('SELECT * FROM %s WHERE `user_id` = :user_id %s AND `id` NOT IN (%s) AND `deleted` = false AND `language` = :lang ORDER BY RAND() LIMIT :limit', self::CARDS, $whereStatement, implode(',', array_map(function (Message $message) { return $message->getId(); }, $messages))));
                $stmt->bindValue(':limit', ($number - count($messages)), PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $user->getId(), PDO::PARAM_INT);
                $stmt->bindValue(':lang', $user->getLanguage());
                if($hard) {
                    $stmt->bindValue(':complicated', true, PDO::PARAM_BOOL);
                }
                if($categoryId) {
                    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                }
                $stmt->execute();
            } catch (PDOException $e) {
                self::$logger->error($e->getMessage());
            }
            $messages = array_merge($messages, self::fillMessages($stmt));
            self::resetDictionary($user->getId(), $hard);
            $reset = true;
        }
        if(!$reset && $messages) {
            try {
                $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET shown = true WHERE user_id = :user_id AND id IN (%s)', self::CARDS, implode(',', array_map(function (Message $message) { return $message->getId(); }, $messages))));
                $stmt->bindValue(':user_id', $user->getId(), PDO::PARAM_INT);
                $stmt->execute();
            } catch (PDOException $e) {
                self::$logger->error($e->getMessage());
            }
        }
        return $messages;
    }

    public static function insertWord(User $user, string $word, string $translation): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $stmt = self::$pdo->prepare(sprintf('INSERT INTO `%s`(`translation`, `ru`, `complicated`, `user_id`, `created_at`, `language`) VALUES (:translation, :ru, :complicated, :user_id, :created_at, :language)', self::CARDS));
            $stmt->bindValue(':ru', $word);
            $stmt->bindValue(':translation', $translation);
            $stmt->bindValue(':user_id', $user->getId(), PDO::PARAM_INT);
            $stmt->bindValue(':complicated', true, PDO::PARAM_BOOL);
            $stmt->bindValue(':created_at', (new DateTime())->format('Y-m-d H:i:s'));
            $stmt->bindValue(':language', $user->getLanguage());
            return $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        return false;
    }

    public static function recoverWord(int $userId, int $wordId): void
    {
        if (!self::isDbConnected()) {
            throw new RuntimeException("Database connection failed");
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET `deleted` = false, `deleted_at` = null, `shown` = false WHERE id = :word_id AND user_id = :user_id', self::CARDS));
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }

    public static function deleteWordForever(int $userId, int $wordId): void
    {
        if (!self::isDbConnected()) {
            throw new RuntimeException("Database connection failed");
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('DELETE FROM `%s` WHERE id = :word_id AND user_id = :user_id', self::CARDS));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }

    public static function removeWord(int $userId, int $wordId): void
    {
        if (!self::isDbConnected()) {
            throw new RuntimeException("Database connection failed");
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET `deleted` = true, `deleted_at` = :deleted_at WHERE id = :word_id AND user_id = :user_id', self::CARDS));
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':deleted_at', (new DateTime())->format('Y-m-d H:i:s'));
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        $recycleBinLimit = Config::get('recycle_bin_limit') ?? 30;
        $query = sprintf('DELETE FROM %s WHERE user_id = :user_id AND deleted = true AND
             id NOT IN (
                SELECT id FROM (
                    SELECT id FROM %s WHERE user_id = :user_id AND deleted = true
                    ORDER BY deleted_at DESC
                    LIMIT %d
                ) AS subquery
            )', self::CARDS, self::CARDS, $recycleBinLimit);
        try {
            $stmt = self::$pdo->prepare($query);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }

    public static function getDeleted(int $userId): array
    {
        $messages = [];
        try {
            $sql = sprintf('SELECT * FROM %s WHERE `user_id` = :user_id AND deleted = true ORDER BY deleted_at DESC', self::CARDS);
            $stmt = self::$pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        while ($row = $stmt->fetch()) {
            try {
                $message = Message::factory($row);
                $message->setText(sprintf('%s --> %s', $row['en'], $row['ru']));
                $messages[] = $message;
            } catch (Exception $e) {
                self::$logger->error($e->getMessage());
            }
        }
        return $messages;
    }

    public static function simpleSearch(User $user, string $phrase, string $column, int $limit = 20): array
    {
        $messages = [];
        try {
            $sql = sprintf('SELECT * FROM %s WHERE `%s` LIKE :phrase AND `user_id` = :user_id AND `deleted` = false AND `language` = :lang LIMIT :limit', self::CARDS, $column);
            $stmt = self::$pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':phrase', $phrase . '%');
            $stmt->bindValue(':user_id', $user->getId(), PDO::PARAM_INT);
            $stmt->bindValue(':lang', $user->getLanguage());
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        $oppositeColumn = $column === 'translation' ? 'ru' : 'translation';
        while ($row = $stmt->fetch()) {
            try {
                $message = Message::factory($row);
                $message->setText(sprintf('%s%s --> <span class="tg-spoiler">%s</span>', ($row['complicated'] ? '** ' : ''), $row[$column], $row[$oppositeColumn]));
                $messages[] = $message;
            } catch (Exception $e) {
                self::$logger->error($e->getMessage());
            }
        }
        return $messages;
    }

    public static function toggleComplicated(int $userId, int $wordId): ?bool
    {
        if (!self::isDbConnected()) {
            return null;
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT * FROM `%s` WHERE user_id = :user_id AND id = :word_id', self::CARDS));
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch();
            if(!$data) {
                return null;
            }
            $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET `complicated` = !complicated, `shown` = false WHERE user_id = :user_id AND id = :word_id', self::CARDS));
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return (bool)$data['complicated'];
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        return null;
    }

    /**
     * @throws Exception
     */
    private static function loadSingleWord(int $wordId): ?Message
    {
        $stmt = self::$pdo->prepare(sprintf('SELECT * FROM `%s` WHERE id = :card_id', self::CARDS));
        $stmt->bindValue(':card_id', $wordId, PDO::PARAM_INT);
        $stmt->execute();
        if($data = $stmt->fetch()) {
            return Message::factory($data);
        }
        return null;
    }

    public static function resetShown(int $userId, int $wordId): ?Message
    {
        if (!self::isDbConnected()) {
            return null;
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET `shown` = false WHERE user_id = :user_id AND id = :word_id', self::CARDS));
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return self::loadSingleWord($wordId);
        } catch (PDOException|Exception $e) {
            self::$logger->error($e->getMessage());
        }
        return null;
    }

    public static function getStatistic(int $userId): array
    {
        if (!self::isDbConnected()) {
            return [];
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT
                (SELECT COUNT(*) FROM `%s` WHERE `user_id` = :user_id) AS TOTAL,
                (SELECT COUNT(*) FROM `%s` WHERE `user_id` = :user_id AND `shown` = :shown AND deleted = false) AS TOTAL_SHOWN,
                (SELECT COUNT(*) FROM `%s` WHERE `user_id` = :user_id AND `complicated` = :complicated AND deleted = false) AS COMPLICATED,
                (SELECT COUNT(*) FROM `%s` WHERE `user_id` = :user_id AND `complicated` = :complicated AND `shown` = :shown AND deleted = false) AS COMPLICATED_SHOWN
            ', self::CARDS, self::CARDS, self::CARDS, self::CARDS));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':complicated', true, PDO::PARAM_BOOL);
            $stmt->bindValue(':shown', true, PDO::PARAM_BOOL);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        return [];
    }

    public static function getOrCreateUser(int $userId, bool $forceGet = false): ?User
    {
        if (!self::isDbConnected()) {
            return null;
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT * FROM `%s` WHERE id = :user_id', self::USERS));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            if($data = $stmt->fetch()) {
                return User::factory($data);
            }
            if($forceGet) {
                return null;
            }
            $newUser = User::factory(['id' => $userId, 'created' => new DateTime(), 'banned' => false, 'voice_messages_enabled' => false]);
            $stmt = self::$pdo->prepare(sprintf('INSERT INTO `%s`(`id`, `created`, `banned`, `voice_messages_enabled`) VALUES (:id, :created, :banned, :voice_messages_enabled)', self::USERS));
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':created', $newUser->getCreated()->format('Y-m-d H:i:s'));
            $stmt->bindValue(':banned', $newUser->isBanned(), PDO::PARAM_BOOL);
            $stmt->bindValue(':voice_messages_enabled', $newUser->isVoiceMessagesEnabled(), PDO::PARAM_BOOL);
            $stmt->execute();
            return $newUser;
        } catch (PDOException|Exception $e) {
            self::$logger->error($e->getMessage());
        }
        return null;
    }

    public static function getWord(int $wordId): ?string
    {
        if (!self::isDbConnected()) {
            return null;
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT `en` FROM `%s` WHERE id = :word_id', self::CARDS));
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            $stmt->execute();
            if($data = $stmt->fetch()) {
                return $data['en'];
            }
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        return null;
    }

    public static function isCommandInProcess(int $userId): bool
    {
        if (!self::isDbConnected()) {
            throw new RuntimeException("Database connection failed");
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT `id` from `%s` where user_id = :user_id', self::COMMAND_IN_PROCESS));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return count($stmt->fetchAll()) > 0;
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        return true;
    }

    public static function addCommandInProcess(int $userId): void
    {
        if (!self::isDbConnected()) {
            throw new RuntimeException("Database connection failed");
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('INSERT INTO `%s`(`created`, `user_id`) VALUES (:created, :user_id)', self::COMMAND_IN_PROCESS));
            $stmt->bindValue(':created', (new DateTime())->format('Y-m-d H:i:s'));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }

    public static function removeCommandInProcess(int $userId): void
    {
        if (!self::isDbConnected()) {
            throw new RuntimeException("Database connection failed");
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('DELETE FROM `%s` WHERE user_id = :user_id', self::COMMAND_IN_PROCESS));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }

    public static function getToken(): ?string
    {
        if (!self::isDbConnected()) {
            return null;
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT iam_token FROM `%s`', self::SETTINGS));
            $stmt->execute();
            if($data = $stmt->fetch()) {
                return $data['iam_token'];
            }
            return null;
        } catch (PDOException|Exception $e) {
            self::$logger->error($e->getMessage());
        }
        return null;
    }

    public static function refreshToken(string $token): void
    {
        try {
            $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET iam_token = :token', self::SETTINGS));
            $stmt->bindValue(':token', $token);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }
}