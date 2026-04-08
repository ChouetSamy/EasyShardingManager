<?php

namespace App\Storage\Driver;

use App\Storage\StorageInterface;
use App\Storage\ValueObject\HealthStatus;
use App\Storage\ValueObject\HealthState;
use App\Storage\ValueObject\StorageMetrics;
use App\Storage\Metrics\MongoMetrics;
use App\Storage\ValueObject\BalanceStatus;
use MongoDB\Client;

/**
 * MongoDB storage driver.
 *
 * RESPONSIBILITIES:
 * - Connect to MongoDB
 * - Retrieve real sharding distribution (chunks)
 * - Expose balancer state
 *
 * DESIGN DECISIONS:
 *
 * 1. Uses MongoDB internal config database
 *    → Only reliable way to inspect sharding
 *
 * 2. Chunk distribution is REQUIRED
 *    → Enables real balance computation
 *
 * 3. No business logic here
 *    → Only data extraction
 */
final class MongoStorage implements StorageInterface
{
    private ?Client $client = null;
    private ?string $dsn = null;

    public static function getDriverName(): string
    {
        return 'mongo';
    }

    public function configure(array $config): void
    {
        $this->dsn = $config['dsn'] ?? null;

        if ($this->dsn === null) {
            throw new \RuntimeException('Missing DSN for MongoDB');
        }
    }

    public function connect(): void
    {
        try {
            $this->client = new Client($this->dsn);
            $this->client->selectDatabase('admin')->command(['ping' => 1]);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'MongoDB connection failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function getHealth(): HealthStatus
    {
        $start = microtime(true);

        try {
            if ($this->client === null) {
                throw new \RuntimeException('Not connected');
            }

            $this->client->selectDatabase('admin')->command(['ping' => 1]);

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
     * Retrieve MongoDB metrics for monitoring and analysis.
     *
     * RETURNS:
     * - chunk count (data distribution size)
     * - shard count
     * - balancer status
     * - chunk distribution (internal use only)
     *
     * ARCHITECTURE DECISION - chunkDistribution field:
     * ================================================
     * 
     * This method includes the full chunk distribution for internal consumption
     * via MetricsTranslator. However, this field is:
     * 
     * 1. NOT PART OF THE DriverMetricsInterface CONTRACT
     *    - Reason: It's MongoDB-specific metadata
     *    - All storage drivers must implement the interface
     *    - CockroachDB and Redis cannot provide equivalent data
     *    - Exposing it violates the Liskov Substitution Principle
     * 
     * 2. INTERNAL USE ONLY (for MetricsTranslator)
     *    - External callers should NOT depend on chunkDistribution
     *    - If you need balance analysis, use analyzeBalance() instead
     *    - analyzeBalance() queries MongoDB directly for fresh data
     * 
     * 3. COMPLEMENTARY TO analyzeBalance()
     *    - getMetrics() = operational metrics (for dashboards)
     *    - analyzeBalance() = diagnostic decision-making (for rebalancing)
     *    - They serve different purposes and should not be mixed
     *
     * @return StorageMetrics
     * @throws \RuntimeException
     */
    public function getMetrics(): StorageMetrics
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        try {
            $configDb = $this->client->selectDatabase('config');

            $chunkCount = $configDb->selectCollection('chunks')->countDocuments();
            $shardCount = $configDb->selectCollection('shards')->countDocuments();

            $settings = $configDb->selectCollection('settings')->findOne([
                '_id' => 'balancer'
            ]);

            $balancerActive = !($settings['stopped'] ?? false);

            /**
             * 🔥 CRITICAL: real distribution per shard
             */
            $pipeline = [
                [
                    '$group' => [
                        '_id' => '$shard',
                        'chunkCount' => ['$sum' => 1]
                    ]
                ]
            ];

            $rawDistribution = iterator_to_array(
                $configDb->selectCollection('chunks')->aggregate($pipeline)
            );

            $chunkDistribution = array_map(fn($item) => [
                'shard' => (string) $item->_id,
                'chunkCount' => (int) $item->chunkCount
            ], $rawDistribution);

            $driverMetrics = new MongoMetrics(
                chunkCount: $chunkCount,
                balancerActive: $balancerActive,
                databaseCount: $shardCount,
                chunkDistribution: $chunkDistribution,
            );

            return new StorageMetrics(
                userCount: 0,
                storageDriver: 'mongo',
                regionCount: $shardCount,
                shardCount: $chunkCount,
                driverMetrics: $driverMetrics
            );

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Failed to fetch MongoDB metrics: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function listShards(): array
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        return iterator_to_array(
            $this->client
                ->selectDatabase('config')
                ->selectCollection('chunks')
                ->find([], ['limit' => 50])
        );
    }

    public function rebalance(string $strategy): void
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        $adminDb = $this->client->selectDatabase('admin');

        match ($strategy) {
            'start' => $adminDb->command(['balancerStart' => 1]),
            'stop' => $adminDb->command(['balancerStop' => 1]),
            default => throw new \RuntimeException("Unsupported strategy: $strategy")
        };
    }

    public function analyzeBalance(): BalanceStatus
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        try {
            /**
             * ARCHITECTURE DECISION: Direct MongoDB Query vs getMetrics()
             * 
             * WHY WE RECALCULATE HERE INSTEAD OF USING getMetrics():
             * 
             * 1. SEPARATION OF CONCERNS
             *    - getMetrics() is designed for operational monitoring/dashboards
             *    - analyzeBalance() is for diagnostic/balancing decisions
             *    - These have different data freshness requirements
             *    
             * 2. AVOID INTERFACE POLLUTION
             *    - getChunkDistribution() is MongoDB-specific
             *    - Adding it to DriverMetricsInterface would require all drivers to support it
             *    - This breaks the Liskov Substitution Principle
             *    - CockroachDB and Redis would need dummy implementations
             *    
             * 3. PERFORMANCE CONSIDERATION
             *    - getMetrics() may cache or aggregate data
             *    - analyzeBalance() needs FRESH distribution data
             *    - Direct query ensures real-time accuracy
             *    
             * 4. INDEPENDENT OPERATION
             *    - analyzeBalance() should work even if getMetrics() fails
             *    - This is critical for troubleshooting (you need balance analysis precisely when things fail)
             * 
             * TRADE-OFF:
             * - We query MongoDB twice (once in getMetrics, once here)
             * - But we maintain clean architecture and driver independence
             * - This is acceptable because analyzeBalance() is called infrequently (admin operations)
             */
            $configDb = $this->client->selectDatabase('config');
            $pipeline = [
                [
                    '$group' => [
                        '_id' => '$shard',
                        'chunkCount' => ['$sum' => 1],
                    ]
                ]
            ];

            $chunks = iterator_to_array(
                $configDb->selectCollection('chunks')->aggregate($pipeline)
            );

            if (empty($chunks)) {
                return new BalanceStatus(false, 0, 'Aucune donnée de distribution');
            }

            $counts = array_map(fn($c) => $c['chunkCount'], $chunks);
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
                ? 'Distribution Mongo équilibrée'
                : \sprintf('Distribution déséquilibrée (%.2f%%)', $maxDeviation * 100)
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