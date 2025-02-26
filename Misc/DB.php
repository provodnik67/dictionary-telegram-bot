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

/**
 * @todo argument user_id in the most of methods, exclude it
 */
class DB
{
    private const CARDS = 'cards';

    private const USERS = 'dictionary_user';

    /**
     * @var array
     */
    protected static $mysql_credentials = [];

    /**
     * @var PDO
     */
    protected static $pdo;

    /**
     * @var Logger
     */
    private static $logger;

    /**
     * @throws Exception
     */
    public static function initialize(array $credentials, $encoding = 'utf8', int $errMode = PDO::ERRMODE_WARNING, Logger $logger): PDO {
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

        return self::$pdo;
    }

    private static function isDbConnected(): bool
    {
        return self::$pdo !== null;
    }

    private static function fillMessages(PDOStatement $statement): array
    {
        $messages = [];
        $keys = ['ru', 'en'];
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

    private static function resetDictionary(int $userId, bool $hard)
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

    public static function getSpecificNumberOfWords(int $userId, int $number, bool $hard = false, ?int $categoryId = null): array
    {
        if (!self::isDbConnected()) {
            return [];
        }

        $statistics = self::getStatistic($userId);
        if(
            ($hard && $statistics['COMPLICATED'] === $statistics['COMPLICATED_SHOWN'])
            || ($statistics['TOTAL'] === $statistics['TOTAL_SHOWN'])
        ) {
            self::resetDictionary($userId, $hard);
        }

        $whereStatement = '';
        if($hard) {
            $whereStatement .= ' AND complicated = :complicated ';
        }
        if($categoryId) {
            $whereStatement .= ' AND category_id = :category_id ';
        }

        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT * FROM %s WHERE user_id = :user_id %s AND shown = :shown ORDER BY RAND() LIMIT :limit', self::CARDS, $whereStatement));
            $stmt->bindValue(':limit', $number, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
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
                $stmt = self::$pdo->prepare(sprintf('SELECT * FROM %s WHERE user_id = :user_id %s AND id NOT IN (%s) ORDER BY RAND() LIMIT :limit', self::CARDS, $whereStatement, implode(',', array_map(function (Message $message) { return $message->getId(); }, $messages))));
                $stmt->bindValue(':limit', ($number - count($messages)), PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
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
            self::resetDictionary($userId, $hard);
            $reset = true;
        }
        if(!$reset && $messages) {
            try {
                $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET shown = true WHERE user_id = :user_id AND id IN (%s)', self::CARDS, implode(',', array_map(function (Message $message) { return $message->getId(); }, $messages))));
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
            } catch (PDOException $e) {
                self::$logger->error($e->getMessage());
            }
        }
        return $messages;
    }

    public static function insertWord(int $userId, string $word, string $translation): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $stmt = self::$pdo->prepare(sprintf('INSERT INTO `%s`(`en`, `ru`, `complicated`, `user_id`, `created_at`) VALUES (:en, :ru, :complicated, :user_id, :created_at)', self::CARDS));
            $stmt->bindValue(':ru', $word);
            $stmt->bindValue(':en', $translation);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':complicated', true, PDO::PARAM_BOOL);
            $stmt->bindValue(':created_at', (new DateTime())->format('Y-m-d H:i:s'));
            return $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        return false;
    }

    public static function simpleSearch(int $userId, string $phrase, string $column, int $limit = 20): array
    {
        $messages = [];
        try {
            $sql = sprintf('SELECT * FROM %s WHERE %s LIKE :phrase AND `user_id` = :user_id LIMIT :limit', self::CARDS, $column);
            $stmt = self::$pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':phrase', $phrase . '%');
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
        $oppositeColumn = $column === 'en' ? 'ru' : 'en';
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

    public static function resetShown(int $userId, int $wordId): void
    {
        if (!self::isDbConnected()) {
            return;
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('UPDATE %s SET `shown` = false WHERE user_id = :user_id AND id = :word_id', self::CARDS));
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            self::$logger->error($e->getMessage());
        }
    }

    public static function getStatistic(int $userId): array
    {
        if (!self::isDbConnected()) {
            return [];
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT
                (SELECT COUNT(*) FROM `%s` WHERE `user_id` = :user_id) AS TOTAL,
                (SELECT COUNT(*) FROM `%s` WHERE `user_id` = :user_id AND `shown` = :shown) AS TOTAL_SHOWN,
                (SELECT COUNT(*) FROM `%s` WHERE `user_id` = :user_id AND `complicated` = :complicated) AS COMPLICATED,
                (SELECT COUNT(*) FROM `%s` WHERE `user_id` = :user_id AND `complicated` = :complicated AND `shown` = :shown) AS COMPLICATED_SHOWN
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

    public static function getOrCreateUser(int $userId): ?User
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
            $newUser = User::factory(['id' => $userId, 'created' => new DateTime(), 'banned' => false]);
            $stmt = self::$pdo->prepare(sprintf('INSERT INTO `%s`(`id`, `created`, `banned`) VALUES (:id, :created, :banned)', self::USERS));
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':created', $newUser->getCreated()->format('Y-m-d H:i:s'));
            $stmt->bindValue(':banned', $newUser->isBanned(), PDO::PARAM_BOOL);
            $stmt->execute();
            return $newUser;
        } catch (PDOException|Exception $e) {
            self::$logger->error($e->getMessage());
        }
        return null;
    }
}