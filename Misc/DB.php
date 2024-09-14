<?php

namespace Misc;

use Exception;
use PDO;
use PDOException;

/**
 * @todo argument user_id in the most of methods, exclude it
 */
class DB
{
    private const CARDS = 'cards';

    /**
     * @var array
     */
    protected static $mysql_credentials = [];

    /**
     * @var PDO
     */
    protected static $pdo;

    /**
     * @throws Exception
     */
    public static function initialize(array $credentials, $encoding = 'utf8'): PDO {
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
        try {
            $pdo = new PDO($dsn, $credentials['user'], $credentials['password'], $options);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/../pdo_error_log', $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        self::$pdo = $pdo;
        self::$mysql_credentials = $credentials;

        return self::$pdo;
    }

    private static function isDbConnected(): bool
    {
        return self::$pdo !== null;
    }

    public static function getPdo(): ?PDO
    {
        return self::$pdo;
    }

    public static function getSpecificNumberOfWords(int $userId, int $number, bool $hard = false): array
    {
        if (!self::isDbConnected()) {
            return [];
        }
        $messages = [];
        try {
            $sql = sprintf('SELECT * FROM %s WHERE user_id = :user_id AND complicated = :complicated ORDER BY RAND() LIMIT :limit', self::CARDS);
            $stmt = self::$pdo->prepare($sql);
            $stmt->bindValue(':limit', $number, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':complicated', $hard ? 1 : 0, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/../pdo_error_log', $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
        $keys = ['ru', 'en'];
        while ($row = $stmt->fetch()) {
            $key = rand(0, 1);
            $messages[] = (object) [
                'id' => $row['id'],
                'text' => sprintf('%s%s --> <span class="tg-spoiler">%s</span>', ($row['complicated'] ? '** ' : ''), $row[$keys[$key]], $row[$keys[abs($key - 1)]]),
                'complicated' => (bool) $row['complicated']
            ];
        }
        return $messages;
    }

    public static function insertWord(int $userId, string $word, string $translation): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $stmt = self::$pdo->prepare(sprintf('INSERT INTO `%s`(`en`, `ru`, `user_id`) VALUES (:en, :ru, :user_id)', self::CARDS));
            $stmt->bindValue(':ru', $word);
            $stmt->bindValue(':en', $translation);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/../pdo_error_log', $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
        return false;
    }

    public static function simpleSearch(int $userId, string $phrase, string $column, int $limit = 20): array
    {
        $messages = [];
        try {
            $sql = sprintf('SELECT * FROM %s WHERE %s LIKE :phrase AND user_id = :user_id LIMIT :limit', self::CARDS, $column);
            $stmt = self::$pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':phrase', $phrase . '%');
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/../pdo_error_log', $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
        $oppositeColumn = $column === 'en' ? 'ru' : 'en';
        while ($row = $stmt->fetch()) {
            $messages[] = (object) [
                'id' => $row['id'],
                'text' => sprintf('%s%s --> <span class="tg-spoiler">%s</span>', ($row['complicated'] ? '** ' : ''), $row[$column], $row[$oppositeColumn]),
                'complicated' => (bool) $row['complicated']
            ];
        }
        return $messages;
    }

    public static function toggleComplicated(int $wordId): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }
        $sql = 'UPDATE cards SET complicated = !complicated WHERE id = :word_id';
        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->bindValue(':word_id', $wordId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/../pdo_error_log', $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
        return false;
    }

    public static function getStatistic(int $userId): array
    {
        if (!self::isDbConnected()) {
            return [];
        }
        try {
            $stmt = self::$pdo->prepare(sprintf('SELECT
                (SELECT COUNT(*) FROM `%s` WHERE user_id = :user_id) AS TOTAL,
                (SELECT COUNT(*) FROM `%s` WHERE user_id = :user_id AND `complicated` = :complicated) AS COMPLICATED
            ', self::CARDS, self::CARDS));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':complicated', 1, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/../pdo_error_log', $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
        return [];
    }
}