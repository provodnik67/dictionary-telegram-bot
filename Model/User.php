<?php

namespace Model;

use DateTime;
use Exception;

class User
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
     * @var bool
     */
    private $banned;

    /**
     * @var bool
     */
    private $voiceMessagesEnabled;

    /**
     * @throws Exception
     */
    public static function factory(array $data): User
    {
        if(!isset($data['created'])) {
            throw new Exception('`created` key must exist in the data array');
        }
        if(!isset($data['id'])) {
            throw new Exception('`id` key must exist in the data array');
        }
        if(!isset($data['banned'])) {
            throw new Exception('`banned` key must exist in the data array');
        }
        if(!isset($data['voice_messages_enabled'])) {
            throw new Exception('`voice_messages_enabled` key must exist in the data array');
        }
        $user = new self();
        $user->id = (int) $data['id'];
        $user->created = $data['created'] instanceof DateTime ? $data['created'] : new DateTime($data['created']);
        $user->banned = (bool) $data['banned'];
        $user->voiceMessagesEnabled = (bool) $data['voice_messages_enabled'];
        return $user;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function isBanned(): bool
    {
        return $this->banned;
    }

    public function isVoiceMessagesEnabled(): bool
    {
        return $this->voiceMessagesEnabled;
    }

    public function getId(): int
    {
        return $this->id;
    }
}