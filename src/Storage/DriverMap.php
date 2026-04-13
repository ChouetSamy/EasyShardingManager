<?php

namespace App\Storage;

class DriverMap
{
    public const MAP = [
        'cockroach' => 'pdo_pgsql',
        'mongo' => 'mongodb',
        'redis' => 'redis',
    ];

    public static function resolve(string $driver): ?string
    {
        return self::MAP[$driver] ?? null;
    }
}