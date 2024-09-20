<?php

namespace Commands;

use BaseCommands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Misc\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class StatusCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'status';

    /**
     * @var string
     */
    protected $description = 'Status';

    /**
     * @var string
     */
    protected $usage = '/status';

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
        $statistics = DB::getStatistic($conversation->getUserId());
        if(!$statistics) {
            return $this->replyToChat(
                $this->getTranslator()->trans('No data.')
            );
        }
        return $this->replyToChat(
            $this->getTranslator()->trans('Words total count -') . ' ' . $statistics['TOTAL'] . PHP_EOL .
            $this->getTranslator()->trans('Words total shown count -') . ' ' . $statistics['TOTAL_SHOWN'] . PHP_EOL .
            $this->getTranslator()->trans('Complicated -') . ' ' . $statistics['COMPLICATED'] . PHP_EOL .
            $this->getTranslator()->trans('Complicated shown -') . ' ' . $statistics['COMPLICATED_SHOWN']
        );
    }
}