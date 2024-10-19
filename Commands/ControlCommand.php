<?php

namespace Commands;

use BaseCommands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class ControlCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'control';

    /**
     * @var string
     */
    protected $description = 'Controls';

    /**
     * @var string
     */
    protected $usage = '/control';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @var bool
     */
    protected $private_only = true;

    /**
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $this->replyToChat($this->getTranslator()->trans('To add a word to the dictionary type: `!add word_in_english - word_in_russian`. Or you can use the `Add` command from the bot-menu.'));
        $this->replyToChat($this->getTranslator()->trans('To get random words to learn type: `n`. Where n - number of words. Example: 12.'));
        $this->replyToChat($this->getTranslator()->trans('To get random complicated words to learn type: `*n`. Where n - number of words. Example: *12.'));
        $this->replyToChat($this->getTranslator()->trans('To search words in English type: `!en word_in_english`. Or you can use the `search_en` command from the bot-menu.'));
        return $this->replyToChat($this->getTranslator()->trans('To search words in Russian type: `!ru word_in_russian`. Or you can use the `search_ru` command from the bot-menu.'));
    }
}