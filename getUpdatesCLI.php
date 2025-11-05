<?php

use Misc\DB;
use Misc\DeepSeekAPI;
use Misc\SpeechKitAPI;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use Symfony\Component\Yaml\Yaml;

$try = 3;
while ($try--) {
    if((int)shell_exec('ps aux | grep -v grep | grep ' . __FILE__ . ' | wc  -l') > 2) {
        sleep(3);
    }
    else {
        break;
    }
}
require __DIR__ . '/vendor/autoload.php';
//@todo надо весь каталог Misc грузить и модельки все, а не по одной
require __DIR__ . '/Misc/DB.php';
require __DIR__ . '/Misc/DeepSeekAPI.php';
require __DIR__ . '/Misc/SpeechKitAPI.php';
require __DIR__ . '/Model/User.php';
require __DIR__ . '/Model/Message.php';
$config = Yaml::parseFile(__DIR__ . '/config.yml');
$seconds = 20;
$pdoLogger = new Logger('pdo_logger');
$pdoLogger->pushHandler(new StreamHandler(__DIR__ . '/pdo_error_log', Logger::DEBUG));
$pdoLogger->pushHandler(new FirePHPHandler());
try {
    DB::initialize($config['database'], 'utf8', PDO::ERRMODE_EXCEPTION, $pdoLogger);
} catch (Exception $e) {
    $pdoLogger->error($e->getMessage());
    die;
}
if(
    !empty($config['misc']['deep_seek_api_key']) &&
    !empty($config['misc']['deep_seek_base_url']) &&
    !empty($config['misc']['depp_seek_assistant_prompt'])
) {
    DeepSeekAPI::initialize(
        $config['misc']['deep_seek_api_key'],
        $config['misc']['deep_seek_base_url'],
        $config['misc']['depp_seek_assistant_prompt'],
        $pdoLogger // @todo наверно свои логи надо вести, а не pdo
    );
}
if(
    !empty($config['misc']['speech_kit']['folder_id']) &&
    !empty($config['misc']['speech_kit']['url'])
) {
    if($iamToken = DB::getToken()) {
        $speechKitLogger = new Logger('speech_kit_logger');
        $speechKitLogger->pushHandler(new StreamHandler(__DIR__ . '/speech_kit_error_log', Logger::DEBUG));
        $speechKitLogger->pushHandler(new FirePHPHandler());
        SpeechKitAPI::initialize(
            $speechKitLogger,
            $iamToken,
            $config['misc']['speech_kit']['url'],
            $config['misc']['speech_kit']['folder_id']
        );
    }
}
if(SpeechKitAPI::isInitialized()) {
    // @todo реализовать в командах для озвучивания
}
while ($seconds--) {
    try {
        $telegram = new Longman\TelegramBot\Telegram($config['bot']['api_key'], $config['bot']['username']);
        $telegram->addCommandsPaths([__DIR__ . '/Commands']);
        $telegram->addCommandsPaths([__DIR__ . '/BaseCommands']);
        $telegram->useGetUpdatesWithoutDatabase();
        $server_response = $telegram->handleGetUpdates();
        if ($server_response->isOk()) {
            $update_count = count($server_response->getResult());
            echo date('Y-m-d H:i:s') . ' - Processed ' . $update_count . ' updates';
        } else {
            echo date('Y-m-d H:i:s') . ' - Failed to fetch updates' . PHP_EOL;
            echo $server_response->printError();
        }
    } catch (Longman\TelegramBot\Exception\TelegramException $e) {
        echo $e->getMessage();
    }
    sleep(3);
}