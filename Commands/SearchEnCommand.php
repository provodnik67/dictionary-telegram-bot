<?php

namespace Commands;

use BaseCommands\SystemCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class SearchEnCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'search_en';

    /**
     * @var string
     */
    protected $description = 'Search in english';

    /**
     * @var string
     */
    protected $usage = '/search_en';

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
        $message = $this->getMessage();
        $forceReply = Keyboard::forceReply(
            [
                'message_id' => $message->getMessageId(),
                'input_field_placeholder' => $this->getTranslator()->trans('Type the word or part of the word in English')
            ]
        );
        Request::sendMessage([
            'chat_id' => $message->getChat()->getId(),
            'text' => '!en',
            'reply_markup' => $forceReply,
            'allow_sending_without_reply' => false
        ]);
        return Request::emptyResponse();
    }
}