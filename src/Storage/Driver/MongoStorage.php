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
 * Responsibilities:
 * - Connect to MongoDB cluster
 * - Inspect sharding state (config DB)
 * - Retrieve chunks distribution
 * - Monitor balancer activity
 *
 * DESIGN DECISIONS:
 *
 * 1. Native MongoDB driver (mongodb/mongodb)
 *    - Required for accessing admin/config databases
 *    - Provides direct access to cluster internals
 *
 * 2. Observability-first approach
 *    - getHealth() NEVER throws
 *    - Always returns a HealthStatus
 *
 * 3. Real sharding control (unlike Cockroach)
 *    - Mongo exposes:
 *        - chunks (data distribution)
 *        - balancer status
 *        - shard topology
 *
 * 4. Fail-fast connection
 *    - No retry logic here (handled at higher level)
 */
final class MongoStorage implements StorageInterface
{
    private ?Client $client = null;
    private ?string $dsn = null;

    public static function getDriverName(): string
    {
        return 'mongo';
    }

    /**
     * Configure the MongoDB driver.
     *
     * @throws \RuntimeException If DSN is missing
     */
    public function configure(array $config): void
    {
        $this->dsn = $config['dsn'] ?? null;

        if ($this->dsn === null) {
            throw new \RuntimeException('Missing DSN for MongoDB');
        }
    }

    /**
     * Establish MongoDB connection.
     *
     * @throws \RuntimeException
     */
    public function connect(): void
    {
        try {
            $this->client = new Client($this->dsn);

            // Ping test
            $this->client->selectDatabase('admin')->command(['ping' => 1]);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'MongoDB connection failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Returns cluster health.
     *
     * NEVER throws — converts errors into HealthStatus.
     */
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
     * Retrieve MongoDB metrics.
     *
     * Includes:
     * - chunk distribution
     * - shard count
     * - balancer status
     *
     * @throws \RuntimeException
     */
    public function getMetrics(): StorageMetrics
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        try {
            $configDb = $this->client->selectDatabase('config');

            // Total chunks (shards of data)
            $chunkCount = $configDb->selectCollection('chunks')->countDocuments();

            // Shard count
            $shardCount = $configDb->selectCollection('shards')->countDocuments();

            // Balancer status
            $settings = $configDb->selectCollection('settings')->findOne([
                '_id' => 'balancer'
            ]);

            $balancerActive = $settings['stopped'] ?? false ? false : true;

            // Distribution per shard
            $pipeline = [
                [
                    '$group' => [
                        '_id' => '$shard',
                        'chunkCount' => ['$sum' => 1]
                    ]
                ]
            ];

            $distribution = iterator_to_array(
                $configDb->selectCollection('chunks')->aggregate($pipeline)
            );

            $driverMetrics = new MongoMetrics(
                chunkCount: $chunkCount,
                balancerActive: $balancerActive,
                databaseCount: $shardCount
            );

            return new StorageMetrics(
                userCount: 0,
                storageDriver: 'mongo',
                regionCount: $shardCount,
                shardCount: $chunkCount,
                driverMetrics: $driverMetrics,
                customMetrics: null
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Failed to fetch MongoDB metrics: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * List chunks (data shards).
     */
    public function listShards(): array
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        $cursor = $this->client
            ->selectDatabase('config')
            ->selectCollection('chunks')
            ->find([], ['limit' => 50]);

        return iterator_to_array($cursor);
    }

    /**
     * Trigger or control MongoDB balancer.
     *
     * Supported strategies:
     * - "start"
     * - "stop"
     *
     * @throws \RuntimeException
     */
    public function rebalance(string $strategy): void
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        try {
            $adminDb = $this->client->selectDatabase('admin');

            if ($strategy === 'start') {
                $adminDb->command(['balancerStart' => 1]);
            } elseif ($strategy === 'stop') {
                $adminDb->command(['balancerStop' => 1]);
            } else {
                throw new \RuntimeException(
                    \sprintf('Unsupported strategy "%s" for MongoDB', $strategy)
                );
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'MongoDB rebalance failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function analyzeBalance(): BalanceStatus
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        $configDb = $this->client->selectDatabase('config');

        $pipeline = [
            [
                '$group' => [
                    '_id' => '$shard',
                    'chunkCount' => ['$sum' => 1]
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
    }
}