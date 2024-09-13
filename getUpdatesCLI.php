<?php
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
require __DIR__ . '/Misc/DB.php';
$config = \Symfony\Component\Yaml\Yaml::parseFile(__DIR__ . '/config.yml');
$seconds = 20;
try {
    \Misc\DB::initialize($config['database']);
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/error.log', $e->getMessage() . PHP_EOL, FILE_APPEND);
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