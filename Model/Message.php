<?php

namespace Model;

use DateTime;
use Exception;
use Misc\DB;

class Message
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var DateTime
     */
    private $created;

    /**
     * @var string
     */
    private $en;

    /**
     * @var string
     */
    private $ru;

    /**
     * @var bool
     */
    private $complicated;

    /**
     * @var int
     */
    private $userId;

    /**
     * @var User|null
     */
    private $user = null;

    /**
     * @var string
     */
    private $text;

    /**
     * @throws Exception
     */
    public static function factory(array $data): self
    {
        $required = [
            'id',
            'en',
            'ru',
            'complicated',
            'user_id',
        ];
        foreach ($required as $field) {
            if(!isset($data[$field])) {
                throw new Exception(sprintf('`%S` key must exist in the data array', $field));
            }
        }
        $message = new self();
        $message->id = (int) $data['id'];
        $message->created = !empty($data['created']) ? new DateTime($data['created']) : null;
        $message->en = $data['en'];
        $message->ru = $data['ru'];
        $message->complicated = (bool) $data['complicated'];
        $message->userId = (int) $data['user_id'];
        return $message;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getEn(): string
    {
        return $this->en;
    }

    public function getRu(): string
    {
        return $this->ru;
    }

    public function isComplicated(): bool
    {
        return $this->complicated;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUser(): ?User
    {
        if(!$this->user instanceof User) {
            $user = $this->userId ? DB::getOrCreateUser($this->userId) : null;
            $this->user = $user;
            return $user;
        }
        return $this->user;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }
}