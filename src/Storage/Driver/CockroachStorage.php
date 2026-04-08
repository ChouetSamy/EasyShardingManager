<?php

namespace App\Storage\Driver;

use App\Storage\StorageInterface;
use App\Storage\ValueObject\HealthStatus;
use App\Storage\ValueObject\HealthState;
use App\Storage\ValueObject\StorageMetrics;
use App\Storage\Metrics\CockroachMetrics;
use App\Storage\ValueObject\BalanceStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * CockroachDB storage driver.
 *
 * RESPONSIBILITY:
 * - ONLY fetch data from CockroachDB
 * - NO business logic
 * - NO normalization
 *
 * ARCHITECTURE ROLE:
 * Cockroach → raw metrics → MetricsTranslator → InsightEngine
 */
final class CockroachStorage implements StorageInterface
{
    private ?Connection $connection = null;
    private ?string $dsn = null;

    public static function getDriverName(): string
    {
        return 'cockroach';
    }

    public function configure(array $config): void
    {
        $this->dsn = $config['dsn'] ?? null;

        if ($this->dsn === null) {
            throw new \RuntimeException('Missing DSN for CockroachDB');
        }
    }

    public function connect(): void
    {
        try {
            $this->connection = DriverManager::getConnection([
                'url' => $this->dsn,
            ]);

            $this->connection->executeQuery('SELECT 1');

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'CockroachDB connection failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function getHealth(): HealthStatus
    {
        $start = microtime(true);

        try {
            if ($this->connection === null) {
                throw new \RuntimeException('Not connected');
            }

            $this->connection->executeQuery('SELECT 1');

            return new HealthStatus(
                HealthState::UP,
                (microtime(true) - $start) * 1000,
                null,
                new \DateTimeImmutable()
            );

        } catch (\Throwable $e) {
            return new HealthStatus(
                HealthState::DOWN,
                0,
                $e->getMessage(),
                new \DateTimeImmutable()
            );
        }
    }

    /**
     * 🔥 ONLY DATA COLLECTION
     */
    public function getMetrics(): StorageMetrics
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Not connected');
        }

        try {
            $rangeCount = (int) $this->connection
                ->fetchOne('SELECT count(*) FROM crdb_internal.ranges');

            /**
             * 🔥 CRITICAL: raw distribution
             */
            $distribution = $this->connection->fetchAllAssociative(
                'SELECT node_id, count(*) as range_count 
                 FROM crdb_internal.ranges 
                 GROUP BY node_id'
            );

            $driverMetrics = new CockroachMetrics(
                rangeCount: $rangeCount,
                nodeCount: count($distribution),
                replicationFactor: 3,
                rangeDistribution: $distribution // 🔥 utilisé par Translator
            );

            return new StorageMetrics(
                userCount: 0,
                storageDriver: 'cockroach',
                regionCount: 1,
                shardCount: $rangeCount,
                driverMetrics: $driverMetrics
            );

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Failed to fetch Cockroach metrics: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function listShards(): array
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Not connected');
        }

        return $this->connection->fetchAllAssociative(
            'SELECT range_id, start_key, end_key 
             FROM crdb_internal.ranges 
             LIMIT 50'
        );
    }

    /**
     * IMPORTANT:
     * Cockroach does NOT allow manual rebalance.
     */
    public function rebalance(string $strategy): void
    {
        throw new \RuntimeException(
            'CockroachDB handles rebalancing automatically'
        );
    }

    /**
     * OPTIONAL (UI helper)
     *
     * 👉 KEEP or DELETE depending on your strategy
     *
     * This duplicates logic from MetricsTranslator.
     * Ideally → remove it later.
     */
    public function analyzeBalance(): BalanceStatus
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Not connected');
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT node_id, count(*) as range_count 
             FROM crdb_internal.ranges 
             GROUP BY node_id'
        );

        if (empty($rows)) {
            return new BalanceStatus(false, 0, 'No data');
        }

        $counts = array_column($rows, 'range_count');
        $avg = array_sum($counts) / count($counts);

        $maxDeviation = 0;

        foreach ($counts as $count) {
            $deviation = abs($count - $avg) / $avg;
            $maxDeviation = max($maxDeviation, $deviation);
        }

        return new BalanceStatus(
            $maxDeviation < 0.2,
            $maxDeviation * 100,
            $maxDeviation < 0.2
                ? 'Cluster équilibré'
                : sprintf('Cluster déséquilibré (%.2f%%)', $maxDeviation * 100)
        );
    }
}