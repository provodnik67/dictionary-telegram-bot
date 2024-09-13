<?php

namespace Longman\TelegramBot\Commands\SystemCommands;

use BaseCommands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class SearchCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'search';

    /**
     * @var string
     */
    protected $description = 'search';

    /**
     * @var string
     */
    protected $usage = '/search';

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
            $this->getTranslator()->trans('Hello!') . PHP_EOL .
            $this->getTranslator()->trans('Type: !en word_in_english') . PHP_EOL .
            $this->getTranslator()->trans('Type: !ru word_in_russian')
        );
    }
}