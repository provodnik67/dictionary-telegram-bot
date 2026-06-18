<?php

use Misc\Config;
use Misc\DB;
use Misc\DeepSeekAPI;
use Misc\SpeechKitAPI;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/vendor/autoload.php';
$requirements = [
    __DIR__ . '/Misc/',
    __DIR__ . '/Model/'
];
foreach ($requirements as $dir) {
    if(is_dir($dir)) {
        foreach (new RegexIterator(
                     new RecursiveIteratorIterator(
                         new RecursiveDirectoryIterator($dir)
                     ),
                     '/^.+.php$/'
                 ) as $file) {
            require $file->getPathname();
        }
    }
}
Config::initialize(Yaml::parseFile(__DIR__ . '/config.yml'));
$pdoLogger = new Logger('pdo_logger');
$pdoLogger->pushHandler(new StreamHandler(__DIR__ . '/pdo_error_log', Logger::DEBUG));
$pdoLogger->pushHandler(new FirePHPHandler());
try {
    DB::initialize(Config::get('database'), 'utf8', PDO::ERRMODE_EXCEPTION, $pdoLogger);
} catch (Exception $e) {
    $pdoLogger->error('getUpdatesCLI | DB::initialize: ' . $e->getMessage());
    die;
}
if(
    !empty(Config::get('misc.deep_seek_api_key')) &&
    !empty(Config::get('misc.deep_seek_base_url')) &&
    !empty(Config::get('misc.depp_seek_assistant_prompt'))
) {
    $deepSeekLogger = new Logger('deep_seek_logger');
    $deepSeekLogger->pushHandler(new StreamHandler(__DIR__ . '/deep_seek_log', Logger::DEBUG));
    $deepSeekLogger->pushHandler(new FirePHPHandler());
    DeepSeekAPI::initialize(
        Config::get('misc.deep_seek_api_key'),
        Config::get('misc.deep_seek_base_url'),
        Config::get('misc.depp_seek_assistant_prompt'),
        $deepSeekLogger
    );
}
if(
    !empty(Config::get('misc.speech_kit.folder_id')) &&
    !empty(Config::get('misc.speech_kit.url')) &&
    !empty(Config::get('misc.speech_kit.cache_folder')) &&
    !empty(Config::get('misc.auth_url')) &&
    !empty(Config::get('misc.auth_token'))
) {
    $speechKitLogger = new Logger('speech_kit_logger');
    $speechKitLogger->pushHandler(new StreamHandler(__DIR__ . '/speech_kit_error_log', Logger::DEBUG));
    $speechKitLogger->pushHandler(new FirePHPHandler());
    SpeechKitAPI::initialize(
        $speechKitLogger,
        Config::get('misc.speech_kit.url'),
        Config::get('misc.speech_kit.folder_id'),
        Config::get('misc.auth_url'),
        Config::get('misc.auth_token'),
        Config::get('misc.speech_kit.cache_folder'),
    );
}

$telegram = new Longman\TelegramBot\Telegram(Config::get('bot.api_key'), Config::get('bot.username'));
$telegram->addCommandsPaths([__DIR__ . '/Commands']);
$telegram->addCommandsPaths([__DIR__ . '/BaseCommands']);
$telegram->useGetUpdatesWithoutDatabase();

while (true) {
    try {
        $server_response = $telegram->handleGetUpdates(['timeout' => 30]);
        if ($server_response->isOk()) {
            $update_count = count($server_response->getResult());
            echo date('Y-m-d H:i:s') . ' - Processed ' . $update_count . ' updates' . PHP_EOL;
        } else {
            echo date('Y-m-d H:i:s') . ' - Failed to fetch updates' . PHP_EOL;
            $error = $server_response->printError();
            $pdoLogger->error('getUpdatesCLI | server_response: ' . date('Y-m-d H:i:s') . ' - Failed to fetch updates: ' . $error);
            echo $error;
        }
    } catch (Longman\TelegramBot\Exception\TelegramException $e) {
        $pdoLogger->error('getUpdatesCLI | TelegramException: ' . $e->getMessage());
        echo $e->getMessage();
    }
}