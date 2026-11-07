<?php
declare(strict_types=1);

define('DB_HOST', getenv('HOTEL_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('HOTEL_DB_NAME') ?: 'aling_mameng_hotel');
define('DB_USER', getenv('HOTEL_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('HOTEL_DB_PASSWORD') ?: '');
const APP_TIMEZONE = 'Asia/Manila';

date_default_timezone_set(APP_TIMEZONE);

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $connection = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET time_zone = '+08:00'");

    return $connection;
}
