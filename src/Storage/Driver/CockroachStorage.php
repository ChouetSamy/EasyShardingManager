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
 * This driver is responsible for interacting with a CockroachDB cluster
 * using low-level SQL queries via Doctrine DBAL.
 *
 * DESIGN DECISIONS:
 *
 * 1. No ORM (Doctrine ORM)
 *    - CockroachDB system tables are not business entities
 *    - Direct SQL access is more efficient and explicit
 *
 * 2. Fail-fast connection strategy
 *    - connect() attempts once and throws on failure
 *    - No retry logic inside the driver
 *    - Retry responsibility belongs to higher-level services
 *
 * 3. Observability-first design
 *    - getHealth() NEVER throws
 *    - Always returns a HealthStatus object
 *
 * 4. CockroachDB specifics
 *    - Sharding is automatic (ranges)
 *    - Rebalancing is handled internally by CockroachDB
 *    - This driver observes, not controls, rebalancing
 */
final class CockroachStorage implements StorageInterface
{
    private ?Connection $connection = null;
    private ?string $dsn = null;

    public static function getDriverName(): string
    {
        return 'cockroach';
    }

    /**
     * Configure driver with runtime parameters.
     *
     * @throws \RuntimeException If DSN is missing
     */
    public function configure(array $config): void
    {
        $this->dsn = $config['dsn'] ?? null;

        if ($this->dsn === null) {
            throw new \RuntimeException('Missing DSN for CockroachDB');
        }
    }

    /**
     * Establish database connection.
     *
     * DESIGN:
     * - Single attempt (fail-fast)
     * - No retry logic (handled at higher level)
     *
     * @throws \RuntimeException If connection fails
     */
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

    /**
     * Returns cluster health.
     *
     * DESIGN:
     * - Never throws
     * - Converts failures into HealthStatus
     */
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
     * Retrieve cluster metrics.
     *
     * @throws \RuntimeException
     */
    public function getMetrics(): StorageMetrics
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Not connected');
        }

        try {
            $rangeCount = (int) $this->connection
                ->fetchOne('SELECT count(*) FROM crdb_internal.ranges');

            $nodeDistribution = $this->connection->fetchAllAssociative(
                'SELECT node_id, count(*) as range_count 
                 FROM crdb_internal.ranges 
                 GROUP BY node_id'
            );

            $driverMetrics = new CockroachMetrics(
                rangeCount: $rangeCount,
                nodeCount: \count($nodeDistribution),
                replicationFactor: 3
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
     * Rebalance operation.
     *
     * IMPORTANT:
     * CockroachDB handles rebalancing automatically.
     *
     * This method:
     * - validates strategy
     * - does NOT trigger real rebalance
     * -return unbalanced cluster
     */
    public function rebalance(string $strategy): void
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Not connected');
        }

        if ($strategy !== 'analyze') {
            throw new \RuntimeException(\sprintf(
                'Unsupported strategy "%s" for CockroachDB',
                $strategy
            ));
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT node_id, count(*) as range_count 
         FROM crdb_internal.ranges 
         GROUP BY node_id'
        );

        if (empty($rows)) {
            throw new \RuntimeException('No range data available');
        }

        $counts = array_column($rows, 'range_count');
        $avg = array_sum($counts) / \count($counts);

        $maxDeviation = 0;

        foreach ($counts as $count) {
            $deviation = abs($count - $avg) / $avg;
            $maxDeviation = max($maxDeviation, $deviation);
        }

        // seuil arbitraire (20%)
        if ($maxDeviation > 0.2) {
            throw new \RuntimeException(\sprintf(
                'Cluster is unbalanced (max deviation: %.2f%%)',
                $maxDeviation * 100
            ));
        }

        // sinon OK (cluster équilibré)
    }

    public function analyzeBalance():BalanceStatus
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Not connected');
        }

        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT node_id, count(*) as range_count 
             FROM crdb_internal.ranges 
             GROUP BY node_id'
            );

            if (empty($rows)) {
                return new BalanceStatus(
                    false,
                    0,
                    'Impossible de déterminer l’état du cluster (aucune donnée)'
                );
            }

            $counts = array_column($rows, 'range_count');
            $avg = array_sum($counts) / \count($counts);

            $maxDeviation = 0;

            foreach ($counts as $count) {
                $deviation = abs($count - $avg) / $avg;
                $maxDeviation = max($maxDeviation, $deviation);
            }

            $isBalanced = $maxDeviation < 0.2;

            return new BalanceStatus(
                isBalanced: $isBalanced,
                deviationPercent: $maxDeviation * 100,
                message: $isBalanced
                ? 'Cluster équilibré'
                : sprintf('Cluster déséquilibré (%.2f%%)', $maxDeviation * 100)
            );

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Balance analysis failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}