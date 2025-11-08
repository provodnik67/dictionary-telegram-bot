<?php
use Misc\DB;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use Symfony\Component\Yaml\Yaml;
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/Misc/DB.php';
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
function getIAMToken($oauthToken, $url)
{
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
/**
 * @throws Exception
 */
function revokeIAMToken($oldToken, $url): void
{
    $data = json_encode([
        'iamToken' => $oldToken
    ]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, sprintf('%s:revoke', $url));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data),
        'Authorization: Bearer ' . $oldToken
    ]);
    curl_exec($ch);
    curl_close($ch);
}
try {
    if($oldToken = DB::getToken()) {
        revokeIAMToken($oldToken, $config['misc']['auth_url']);
    }
    $result = getIAMToken($config['misc']['auth_token'], $config['misc']['auth_url']);
    if(!empty($result['iamToken'])) {
        DB::refreshToken($result['iamToken']);
    }
} catch (Exception $e) {
    $tokenLogger->error($e->getMessage());
}