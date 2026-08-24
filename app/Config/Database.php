<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use RuntimeException;

final class Database
{
    private const HOST = '192.168.0.53';
    private const NAME = 'monitor_sys';
    private const USER = 'root';
    private const PASS = '';

    public static function connect(): PDO
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('A extensao pdo_mysql nao esta habilitada no PHP.');
        }

        $user = getenv('DB_USER') ?: self::USER;
        $pass = getenv('DB_PASS');

        return new PDO(
            'mysql:host=' . self::HOST . ';dbname=' . self::NAME . ';charset=utf8mb4',
            $user,
            $pass === false ? self::PASS : $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
}
