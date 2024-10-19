<?php

namespace Commands;

use BaseCommands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Misc\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Model\Message;

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
    protected $version = '1.0.0';

    private const MAX_WORDS_NUMBER = 20;

    /**
     * @todo склеить методы текстового выполнения команд и выполнения через forceReply
     * @todo возможно уже создать каталог subcommand и выносить функционал туда: имплементировать собственные классы или экстендить вендорные команды
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $conversation = new Conversation(
            $message->getFrom()->getId(),
            $message->getChat()->getId()
        );
        $user = DB::getOrCreateUser($conversation->getUserId());
        if($user && $user->isBanned()) {
            return $this->replyToChat($this->getTranslator()->trans('Sorry, your account is banned.'));
        }

        // start add-command

        if($message->getReplyToMessage() && $message->getReplyToMessage()->getText() === '!add') {
            if (preg_match_all('/(.+) - (.+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
                $en = $matches[0][1];
                $ru = $matches[0][2];
                DB::insertWord($conversation->getUserId(), $ru, $en);
                return $this->replyToChat($this->getTranslator()->trans('Word was successfully added.'));
            }
            return Request::emptyResponse();
        }

        if (preg_match_all('/^!add (.+) - (.+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
            $en = $matches[0][1];
            $ru = $matches[0][2];
            DB::insertWord($conversation->getUserId(), $ru, $en);
            return $this->replyToChat($this->getTranslator()->trans('Word was successfully added.'));
        }

        // end add-command

        // start output specific number of words

        if (is_numeric($message->getText())) {
            $number = (int)$message->getText();
            $messages = DB::getSpecificNumberOfWords($conversation->getUserId(), min($number, self::MAX_WORDS_NUMBER));
            if (!$messages) {
                return $this->replyToChat($this->getTranslator()->trans('Your dictionary is empty. Use the command !add.'));
            }
            $this->sendWordsToTheChat($conversation->getChatId(), $messages);
            return Request::emptyResponse();
        }

        // end output specific number of words

        // start complicated-search

        if (preg_match_all('/^\*(\d+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
            if (is_numeric($matches[0][1])) {
                $number = (int)$matches[0][1];
                $messages = DB::getSpecificNumberOfWords($conversation->getUserId(), min($number, self::MAX_WORDS_NUMBER), true);
                if (!$messages) {
                    return $this->replyToChat($this->getTranslator()->trans('Your complicated dictionary is empty.'));
                }
                $this->sendWordsToTheChat($conversation->getChatId(), $messages);
            }
            return Request::emptyResponse();
        }

        // end complicated-search

        // start ru-search

        if($message->getReplyToMessage() && $message->getReplyToMessage()->getText() === '!ru') {
            $search = $message->getText();
            $messages = DB::simpleSearch($conversation->getUserId(), $search, 'ru');
            $this->sendWordsToTheChat($conversation->getChatId(), $messages, false);
            return Request::emptyResponse();
        }

        if (preg_match_all('/^!ru (.+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
            $search = $matches[0][1];
            $messages = DB::simpleSearch($conversation->getUserId(), $search, 'ru');
            $this->sendWordsToTheChat($conversation->getChatId(), $messages, false);
            return Request::emptyResponse();
        }

        // end ru-search

        // start en-search

        if($message->getReplyToMessage() && $message->getReplyToMessage()->getText() === '!en') {
            $search = $message->getText();
            $messages = DB::simpleSearch($conversation->getUserId(), $search, 'en');
            $this->sendWordsToTheChat($conversation->getChatId(), $messages, false);
            return Request::emptyResponse();
        }

        if (preg_match_all('/^!en (.+)/', $message->getText(), $matches, PREG_SET_ORDER)) {
            $search = $matches[0][1];
            $messages = DB::simpleSearch($conversation->getUserId(), $search, 'en');
            $this->sendWordsToTheChat($conversation->getChatId(), $messages, false);
            return Request::emptyResponse();
        }

        // end en-search

        return Request::emptyResponse();
    }

    private function sendWordsToTheChat(int $chatId, array $messages, bool $resetShown = true): void
    {
        if (count($messages) === self::MAX_WORDS_NUMBER) {
            try {
                Request::sendMessage([
                                         'chat_id' => $chatId,
                                         'text' => sprintf($this->getTranslator()->trans('Max words count through one output - %d'), self::MAX_WORDS_NUMBER),
                                     ]);
            }
            catch (TelegramException $e) {
                $this->getLogger()->error($e->getMessage());
            }
        }
        /** @var Message $message */
        foreach ($messages as $message) {
            $inline_keyboard = new InlineKeyboard(
                [
                    [
                        'text' => $message->isComplicated() ? $this->getTranslator()->trans('Exclude from complicated') : $this->getTranslator()->trans('Add to complicated'),
                        'callback_data' => sprintf('toggleComplicated:%d', $message->getId())
                    ],
                    $resetShown ? [
                        'text' => $this->getTranslator()->trans('Reset shown'),
                        'callback_data' => sprintf('resetShown:%d', $message->getId())
                    ] : []
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