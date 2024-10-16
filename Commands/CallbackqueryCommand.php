<?php


namespace Commands;

use BaseCommands\SystemCommand;
use Exception;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Misc\DB;

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
        if (preg_match_all('/^toggleComplicated:(\d+)/', $callback_data, $matches, PREG_SET_ORDER)) {
            $cardId = (int)$matches[0][1];
            $toggleResult = DB::toggleComplicated($callback_query->getFrom()->getId(), $cardId);
            if(is_null($toggleResult)){
                return $callback_query->answer([
                    'text'       => $this->getTranslator()->trans('Error'),
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
            }
            $inline_keyboard = new InlineKeyboard(
                [
                    [
                        'text' => !$toggleResult ? $this->getTranslator()->trans('Exclude from complicated') : $this->getTranslator()->trans('Add to complicated'),
                        'callback_data' => sprintf('toggleComplicated:%d', $cardId)
                    ],
                    [
                        'text' => $this->getTranslator()->trans('Reset shown'),
                        'callback_data' => sprintf('resetShown:%d', $cardId)
                    ]
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
        if (preg_match_all('/^resetShown:(\d+)/', $callback_data, $matches, PREG_SET_ORDER)) {
            $cardId = (int)$matches[0][1];
            DB::resetShown($callback_query->getFrom()->getId(), $cardId);
            Request::deleteMessage(
                [
                    'chat_id' => $message->getChat()->getId(),
                    'message_id' => $message->getMessageId()
                ]
            );
        }

        return $callback_query->answer([
                                           'show_alert' => false
                                       ]);
    }
}