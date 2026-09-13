<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;
use Predis\Client as RedisClient;

/*
|--------------------------------------------------------------------------
| Environment helper
|--------------------------------------------------------------------------
*/
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    return $value;
}

/*
|--------------------------------------------------------------------------
| Load environment variables from .env
|--------------------------------------------------------------------------
*/
$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    throw new RuntimeException('.env file not found.');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

    $key = trim($key);
    $value = trim($value);

    if ($key !== '') {
        putenv($key . '=' . $value);
    }
}

/*
|--------------------------------------------------------------------------
| MySQL connection
|--------------------------------------------------------------------------
*/
$mysqlHost = env('MYSQL_HOST', '127.0.0.1');
$mysqlPort = env('MYSQL_PORT', '3306');
$mysqlDatabase = env('MYSQL_DATABASE', 'secure_auth');
$mysqlUsername = env('MYSQL_USERNAME', 'root');
$mysqlPassword = env('MYSQL_PASSWORD', '');

$mysqlDsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $mysqlHost,
    $mysqlPort,
    $mysqlDatabase
);

$pdo = new PDO(
    $mysqlDsn,
    $mysqlUsername,
    $mysqlPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

/*
|--------------------------------------------------------------------------
| MongoDB connection
|--------------------------------------------------------------------------
*/
$mongoUri = env('MONGODB_URI', 'mongodb://127.0.0.1:27017');
$mongoDatabase = env('MONGODB_DATABASE', 'secure_auth');

$mongoClient = new Client($mongoUri);
$mongoDb = $mongoClient->selectDatabase($mongoDatabase);

/*
|--------------------------------------------------------------------------
| Redis / Valkey connection
|--------------------------------------------------------------------------
*/
$redisUrl = env('REDIS_URL', '');

if ($redisUrl === '') {
    throw new RuntimeException('REDIS_URL is not configured.');
}

$redis = new RedisClient($redisUrl);