<?php

namespace Commands;

use BaseCommands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Request;
use Misc\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Model\Message;

class RecycleCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'recycle';

    /**
     * @var string
     */
    protected $description = 'Recycle bin';

    /**
     * @var string
     */
    protected $usage = '/recycle';

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
        $conversation = new Conversation(
            $message->getFrom()->getId(),
            $message->getChat()->getId()
        );
        $messages = DB::getDeleted($conversation->getUserId());
        if(count($messages) === 0) {
            return $this->replyToChat(
                $this->getTranslator()->trans('No data.')
            );
        }
        $this->sendWordsToTheChat($conversation->getChatId(), $messages);
        return Request::emptyResponse();
    }

    private function sendWordsToTheChat(int $chatId, array $messages): void
    {
        /** @var Message $message */
        foreach ($messages as $message) {
            $inline_keyboard = new InlineKeyboard(
                [
                    [
                        'text' => $this->getTranslator()->trans('Recover'),
                        'callback_data' => sprintf('recover:%d', $message->getId())
                    ],
                    [
                        'text' => $this->getTranslator()->trans('Permanent delete'),
                        'callback_data' => sprintf('deleteForever:%d', $message->getId())
                    ],
                ]
            );
            try {
                Request::sendMessage([
                    'chat_id' => $chatId,
                    'parse_mode' => 'HTML',
                    'text' => $message->getText(),
                    'reply_markup' => $inline_keyboard
                ]);
            }
            catch (TelegramException $e) {
                $this->getLogger()->error($e->getMessage());
            }
        }
    }
}