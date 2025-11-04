<?php
use Misc\DB;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use Symfony\Component\Yaml\Yaml;
require __DIR__ . '/vendor/autoload.php';
$config = Yaml::parseFile(__DIR__ . '/config.yml');
if(empty($config['misc']['auth_token']) || empty($config['misc']['auth_url'])) {
    die;
}
$pdoLogger = new Logger('pdo_logger');
$pdoLogger->pushHandler(new StreamHandler(__DIR__ . '/pdo_error_log', Logger::DEBUG));
$pdoLogger->pushHandler(new FirePHPHandler());
try {
    DB::initialize($config['database'], 'utf8', PDO::ERRMODE_EXCEPTION, $pdoLogger);
} catch (Exception $e) {
    $pdoLogger->error($e->getMessage());
}
$tokenLogger = new Logger('token_logger');
$tokenLogger->pushHandler(new StreamHandler(__DIR__ . '/token_error_log', Logger::DEBUG));
$tokenLogger->pushHandler(new FirePHPHandler());
/**
 * @throws Exception
 */
function getIAMToken($oauthToken, $url) {
    $data = json_encode([
        'yandexPassportOauthToken' => $oauthToken
    ]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: $httpCode - $response");
    }
    return json_decode($response, true);
}
try {
    // @todo записать в базу IAM токен, использовать для конкретного пользователя, либо завести таблицу настроек
    $result = getIAMToken($config['misc']['auth_token'], $config['misc']['auth_url']);
} catch (Exception $e) {
    $tokenLogger->error($e->getMessage());
}