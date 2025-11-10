<?php


namespace Commands;

use BaseCommands\SystemCommand;
use Exception;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Misc\DB;
use Misc\DeepSeekAPI;
use Misc\SpeechKitAPI;
use Model\Message;
use Model\User;

class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Handle the callback query';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @throws Exception
     */
    public function execute(): ServerResponse
    {
        $callback_query = $this->getCallbackQuery();
        $callback_data  = $callback_query->getData();
        $message = $callback_query->getMessage();
        $user = DB::getOrCreateUser($callback_query->getFrom()->getId(), true);
        if (
            preg_match_all('/^toggleComplicated:(\d+)/', $callback_data, $matches, PREG_SET_ORDER) &&
            $user instanceof User
        ) {
            $cardId = (int)$matches[0][1];
            $toggleResult = DB::toggleComplicated($user->getId(), $cardId);
            if(is_null($toggleResult)) {
                return $callback_query->answer([
                    'text'       => $this->getTranslator()->trans('Error'),
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
            }
            $inline_keyboard = new InlineKeyboard(
                [
                    [
                        'text' => $this->getTranslator()->trans('Context'),
                        'callback_data' => sprintf('context:%d', $cardId)
                    ],
                    [
                        'text' => !$toggleResult ? $this->getTranslator()->trans('Exclude from complicated') : $this->getTranslator()->trans('Add to complicated'),
                        'callback_data' => sprintf('toggleComplicated:%d', $cardId)
                    ]
                ],
                [
                    $user->isVoiceMessagesEnabled() ? [
                        'text' => $this->getTranslator()->trans('Play an audio'),
                        'callback_data' => sprintf('playAudio:%d', $cardId)
                    ] : []
                ]
            );
            Request::editMessageReplyMarkup(
                [
                    'chat_id' => $message->getChat()->getId(),
                    'message_id' => $message->getMessageId(),
                    'reply_markup' => $inline_keyboard
                ]
            );
            return $callback_query->answer([
                                               'text'       => $this->getTranslator()->trans($toggleResult ? 'Word is removed from complicated.' : 'Word is added to complicated.'),
                                               'show_alert' => true,
                                               'cache_time' => 0,
                                           ]);
        }
        if (
            preg_match_all('/^resetShown:(\d+)/', $callback_data, $matches, PREG_SET_ORDER) &&
            $user instanceof User
        ) {
            $cardId = (int)$matches[0][1];
            $card = DB::resetShown($user->getId(), $cardId);
            if($card instanceof Message) {
                $inline_keyboard = new InlineKeyboard(
                    [
                        [
                            'text' => $card->isComplicated() ? $this->getTranslator()->trans('Exclude from complicated') : $this->getTranslator()->trans('Add to complicated'),
                            'callback_data' => sprintf('toggleComplicated:%d', $card->getId())
                        ]
                    ],
                    [
                        [
                            'text' => $this->getTranslator()->trans('Context'),
                            'callback_data' => sprintf('context:%d', $card->getId())
                        ],
                        $user->isVoiceMessagesEnabled() ? [
                            'text' => $this->getTranslator()->trans('Play an audio'),
                            'callback_data' => sprintf('playAudio:%d', $card->getId())
                        ] : []
                    ]
                );
                Request::editMessageReplyMarkup(
                    [
                        'chat_id' => $message->getChat()->getId(),
                        'message_id' => $message->getMessageId(),
                        'reply_markup' => $inline_keyboard
                    ]
                );
            }
        }
        if (preg_match_all('/^context:(\d+)/', $callback_data, $matches, PREG_SET_ORDER)) {
            $cardId = (int)$matches[0][1];
            $enWord = DB::getWord($cardId);
            if(is_null($enWord) || !DeepSeekAPI::isInitialized()) {
                $contextMessage = 'something went wrong';
            }
            else {
                $response = DeepSeekAPI::request(sprintf('Give me five short sentences with the word "%s". The list has to be with numbers.', $enWord));
                if(!empty($response['choices'][0]['message']['content'])) {
                    $contextMessage = $response['choices'][0]['message']['content'];
                }
                else {
                    $contextMessage = 'Something went wrong';
                }
            }
            try {
                Request::sendMessage([
                    'chat_id' => $message->getChat()->getId(),
                    'text' => $contextMessage,
                ]);
            }
            catch (TelegramException $e) {
                $this->getLogger()->error($e->getMessage());
            }
        }
        if (
            preg_match_all('/^playAudio:(\d+)/', $callback_data, $matches, PREG_SET_ORDER) &&
            $user instanceof User
        ) {
            $cardId = (int)$matches[0][1];
            $enWord = DB::getWord($cardId);
            $callback_query = $this->getCallbackQuery();
            if(is_null($enWord) || !SpeechKitAPI::isInitialized()) {
                return $callback_query->answer([
                    'show_alert' => false
                ]);
            }
            $oggPath = SpeechKitAPI::textToSpeech($enWord, $user, $cardId);
            if(is_null($oggPath)) {
                return $callback_query->answer([
                    'show_alert' => false
                ]);
            }
            Request::sendVoice(
                [
                    'chat_id' => $message->getChat()->getId(),
                    'voice' => $oggPath
                ]
            );
        }

        return $callback_query->answer([
                                           'show_alert' => false
                                       ]);
    }
}