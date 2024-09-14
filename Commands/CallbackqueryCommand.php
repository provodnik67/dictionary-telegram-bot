<?php


namespace Commands;

use BaseCommands\SystemCommand;
use Exception;
use Longman\TelegramBot\Entities\ServerResponse;
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
        if (preg_match_all('/^toggleComplicated:(\d+)/', $callback_data, $matches, PREG_SET_ORDER)) {
            $toggleResult = DB::toggleComplicated((int)$matches[0][1]);
            return $callback_query->answer([
                                               'text'       => $toggleResult ? $this->getTranslator()->trans('Done') : $this->getTranslator()->trans('Error'),
                                               'show_alert' => true,
                                               'cache_time' => 0,
                                           ]);
        }
        return $callback_query->answer([
                                           'show_alert' => false
                                       ]);
    }
}