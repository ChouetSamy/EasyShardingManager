<?php

namespace App\Seed;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class SeedCockroachScript
{
    public function run(string $dsn, int $userCount = 100000): void
    {
        $connection = DriverManager::getConnection([
            'host' => 'cockroachdb',
            'port' => 26257,
            'dbname' => 'defaultdb',
            'user' => 'root',
            'driver' => 'pdo_pgsql',
        ]);

        $this->createTables($connection);

        echo "Seeding $userCount users...\n";

        $batchSize = 1000;

        $regions = ['eu-west', 'us-east', 'asia', 'africa'];

        for ($i = 0; $i < $userCount; $i += $batchSize) {

            $values = [];

            for ($j = 0; $j < $batchSize && ($i + $j) < $userCount; $j++) {

                $id = $i + $j + 1;

                $region = $regions[array_rand($regions)];

                $email = "user{$id}@test.com";

                // format ISO compatible Cockroach (IMPORTANT)
                $createdAt = date('Y-m-d H:i:s');

                $credits = random_int(0, 10000);

                $values[] = sprintf(
                    "('%s', '%s', '%s', %d)",
                    addslashes($email),
                    $region,
                    $createdAt,
                    $credits
                );
            }

            $sql = "
                INSERT INTO users (
                    email,
                    region,
                    created_at,
                    credits
                )
                VALUES " . implode(',', $values);

            $connection->executeStatement($sql);

            echo "Inserted: " . min($i + $batchSize, $userCount) . "\n";
        }

        echo "Cockroach seed complete.\n";
    }

    private function createTables(Connection $connection): void
    {
        $connection->executeStatement("
            CREATE TABLE IF NOT EXISTS users (
                region STRING NOT NULL,
                id UUID DEFAULT gen_random_uuid(),
                email STRING UNIQUE NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT now(),
                credits INT8 DEFAULT 0,
                PRIMARY KEY (region, id)
            );
        ");

    }
}