<?php

namespace Commands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Misc\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class GenericmessageCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'genericmessage';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var string
     */
    protected $version = '1.1.0';

    private const MAX_WORDS_NUMBER = 20;

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

        if (is_numeric($message->getText())) {
            $number = (int)$message->getText();
            $messages = DB::getSpecificNumberOfWords($conversation->getUserId(), min($number, self::MAX_WORDS_NUMBER));
            if (!$messages) {
                $this->replyToChat('Your dictionary is empty. Use the command !add.');
            }
            $this->sendWordsToTheChat($conversation->getChatId(), $messages);
        }

        if (preg_match_all('/^!(\d+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
            if (is_numeric($matches[0][1])) {
                $number = (int)$matches[0][1];
                $messages = DB::getSpecificNumberOfWords($conversation->getUserId(), min($number, self::MAX_WORDS_NUMBER), true);
                if (!$messages) {
                    $this->replyToChat('Your complicated dictionary is empty.');
                }
                $this->sendWordsToTheChat($conversation->getChatId(), $messages);
            }
        }

        if (preg_match_all('/^!add (.+) - (.+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
            $en = $matches[0][1];
            $ru = $matches[0][2];
            DB::insertWord($conversation->getUserId(), $ru, $en);
            $this->replyToChat('Word was successfully added.');
        }

        if (preg_match_all('/^!ru (.+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
            $search = $matches[0][1];
            $messages = DB::simpleSearch($conversation->getUserId(), $search, 'ru');
            $this->sendWordsToTheChat($conversation->getChatId(), $messages);
        }

        if (preg_match_all('/^!en (.+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
            $search = $matches[0][1];
            $messages = DB::simpleSearch($conversation->getUserId(), $search, 'en');
            $this->sendWordsToTheChat($conversation->getChatId(), $messages);
        }

        return Request::emptyResponse();
    }

    /**
     * @throws TelegramException
     * @todo write exception handling
     */
    private function sendWordsToTheChat(int $chatId, array $messages): void
    {
        if (count($messages) === self::MAX_WORDS_NUMBER) {
            Request::sendMessage([
                                     'chat_id' => $chatId,
                                     'text' => sprintf('Max words count through one output - %d', self::MAX_WORDS_NUMBER),
                                 ]);
        }
        foreach ($messages as $message) {
            $inline_keyboard = new InlineKeyboard([['text' => $message->complicated ? 'Exclude from complicated' : 'Add to complicated', 'callback_data' => sprintf('toggleComplicated:%d', $message->id)],]);
            Request::sendMessage([
                                     'chat_id' => $chatId,
                                     'parse_mode' => 'HTML',
                                     'text' => $message->text,
                                     'reply_markup' => $inline_keyboard
                                 ]);
        }
    }
}