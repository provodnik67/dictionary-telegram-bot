<?php

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class AddWordCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'addword';

    /**
     * @var string
     */
    protected $description = 'add a word';

    /**
     * @var string
     */
    protected $usage = '/addword';

    /**
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * @var bool
     */
    protected $private_only = true;

    /**
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        return $this->replyToChat(
            'Hello!' . PHP_EOL .
            'Type: !add word_in_english - word_in_russian'
        );
    }
}