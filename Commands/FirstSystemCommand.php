<?php

namespace Commands;

use Locale;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Telegram;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;

/**
 * @todo в Telegram -> getCommandsList() итератор первым грузит SearchCommand, нужно что бы класс FirstSystemCommand был первым + переименовать его
 */
class FirstSystemCommand extends SystemCommand
{
    private $translator;

    public function __construct(Telegram $telegram, ?Update $update = null)
    {
        $config = Yaml::parseFile(__DIR__ . '/../config.yml');
        $locale = $config['misc']['locale'] ?? Locale::getDefault();
        $this->translator = new Translator($locale);
        $this->translator->addLoader('yaml', new YamlFileLoader());
        $this->translator->addResource('yaml', __DIR__ . sprintf('/../translations/messages.%s.yml', $locale), $locale);
        parent::__construct($telegram, $update);
    }

    public function getTranslator(): Translator
    {
        return $this->translator;
    }
}