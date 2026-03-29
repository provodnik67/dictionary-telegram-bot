<?php

namespace Commands;

use BaseCommands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Request;
use Misc\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Model\User;

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
        $user = DB::getOrCreateUser($conversation->getUserId());
        if(!$user instanceof User) {
            $this->getLogger()->error(sprintf('Impossible to create user %d.', $conversation->getUserId()));
            return Request::emptyResponse();
        }
        if($user->isBanned()) {
            return $this->replyToChat(
                $this->getTranslator()->trans('Sorry, your account is banned.')
            );
        }
        $statistics = DB::getStatistic($user);
        if ($statistics === []) {
            return $this->replyToChat(
                $this->getTranslator()->trans('No data.')
            );
        }
        $blocks = [];
        foreach ($statistics as $language => $data) {
            $blocks[] = $this->formatLanguageStatisticsBlock($language, $data);
        }

        return $this->replyToChat(implode(PHP_EOL . PHP_EOL, $blocks));
    }

    private function formatLanguageStatisticsBlock(string $language, array $data): string
    {
        return
            $this->getTranslator()->trans('Language is -') . ' ' . $language . PHP_EOL .
            $this->getTranslator()->trans('Words total count -') . ' ' . $data['TOTAL'] . PHP_EOL .
            $this->getTranslator()->trans('Words total shown count -') . ' ' . $data['TOTAL_SHOWN'] . PHP_EOL .
            $this->getTranslator()->trans('Complicated -') . ' ' . $data['COMPLICATED'] . PHP_EOL .
            $this->getTranslator()->trans('Complicated shown -') . ' ' . $data['COMPLICATED_SHOWN'];
    }
}