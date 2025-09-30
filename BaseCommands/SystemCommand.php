<?php

namespace BaseCommands;

use Locale;
use Longman\TelegramBot\Commands\SystemCommand as BaseCommandSystem;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Misc\DB;
use Monolog\Handler\FirePHPHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;

class SystemCommand extends BaseCommandSystem
{
    private ?Translator $translator;

    private ?Logger $logger;

    public function __construct(Telegram $telegram, ?Update $update = null)
    {
        $config = Yaml::parseFile(__DIR__ . '/../config.yml');

        $this->logger = new Logger('tg_logger');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../tg_error_log', Logger::DEBUG));
        $this->logger->pushHandler(new FirePHPHandler());

        $locale = $config['misc']['locale'] ?? Locale::getDefault();
        $this->translator = new Translator($locale);
        $translationSrc = __DIR__ . sprintf('/../translations/messages.%s.yml', $locale);
        if(file_exists($translationSrc)) {
            $this->translator->addLoader('yaml', new YamlFileLoader());
            $this->translator->addResource('yaml', $translationSrc, $locale);
        }
        else {
            $this->logger->error('Translation file not found: ' . $translationSrc);
        }
        parent::__construct($telegram, $update);
    }

    public function getTranslator(): Translator
    {
        return $this->translator;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function preExecute(): ServerResponse
    {
        $message = $this->getMessage();
        $user = $message->getFrom();
        if ($user) {
            if(DB::isCommandInProcess($user->getId())) {
                return Request::emptyResponse();
            }
            DB::addCommandInProcess($user->getId());
        }
        $executionResult = parent::preExecute();
        if ($user) {
            DB::removeCommandInProcess($user->getId());
        }
        return $executionResult;
    }
}