<?php

namespace App\Storage\Driver;

use App\Storage\StorageInterface;
use App\Storage\ValueObject\HealthStatus;
use App\Storage\ValueObject\HealthState;
use App\Storage\ValueObject\StorageMetrics;
use App\Storage\ValueObject\BalanceStatus;
use App\Storage\Metrics\RedisMetrics;
use Predis\Client;

/**
 * Redis storage driver.
 *
 * Responsibilities:
 * - Connect to Redis cluster
 * - Inspect cluster slots distribution
 * - Monitor usage (memory, ops)
 *
 * DESIGN DECISIONS:
 *
 * 1. Redis is NOT a sharded database
 *    - Uses slot-based partitioning
 *    - Primarily used for caching / queues
 *
 * 2. Observability-focused
 *    - No real "rebalance" like Mongo
 *    - Mostly monitoring
 *
 * 3. Fail-fast connection
 *    - No retry logic
 */
final class RedisStorage implements StorageInterface
{
    private ?Client $client = null;
    private ?string $dsn = null;

    public static function getDriverName(): string
    {
        return 'redis';
    }

    public function configure(array $config): void
    {
        $this->dsn = $config['dsn'] ?? null;

        if ($this->dsn === null) {
            throw new \RuntimeException('Missing DSN for Redis');
        }
    }

    public function connect(): void
    {
        try {
            $this->client = new Client($this->dsn);

            $this->client->ping();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Redis connection failed: ' . $e->getMessage(),
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

            $this->client->ping();

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
    public function getMetrics(): StorageMetrics
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected');
        }

        try {
            $info = $this->client->info();

            $memoryUsed = $info['Memory']['used_memory'] ?? 0;
            $connectedClients = $info['Clients']['connected_clients'] ?? 0;
            $opsPerSec = $info['Stats']['instantaneous_ops_per_sec'] ?? 0;

            // Redis standalone ne supporte pas CLUSTER SLOTS
            // On détecte le mode cluster avant d'appeler la commande
            $clusterEnabled = false;
            $slots = [];

            try {
                $clusterInfo = $this->client->executeRaw(['CLUSTER', 'INFO']);
                if (is_string($clusterInfo) && str_contains($clusterInfo, 'cluster_enabled:1')) {
                    $clusterEnabled = true;
                    $slots = $this->client->executeRaw(['CLUSTER', 'SLOTS']);
                    if (!is_array($slots)) {
                        $slots = [];
                    }
                }
            } catch (\Throwable) {
                // Mode standalone — pas de cluster, on ignore
            }

            $driverMetrics = new RedisMetrics(
                memoryUsed: (int) $memoryUsed,
                connectedClients: (int) $connectedClients,
                opsPerSec: (int) $opsPerSec,
                slotDistribution: $slots
            );

            return new StorageMetrics(
                userCount: (int) $connectedClients,
                storageDriver: 'redis',
                regionCount: 1,
                shardCount: $clusterEnabled ? count($slots) : 1,
                driverMetrics: $driverMetrics
            );

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Failed to fetch Redis metrics: ' . $e->getMessage(),
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

        try {
            $result = $this->client->executeRaw(['CLUSTER', 'SLOTS']);
            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return []; // standalone Redis — pas de slots
        }
    }

    public function rebalance(string $strategy): void
    {
        throw new \RuntimeException(
            'Redis rebalancing is not supported via this driver'
        );
    }

    public function analyzeBalance(): BalanceStatus
    {
        return new BalanceStatus(
            true,
            0,
            'Redis n’utilise pas de distribution de données comparable'
        );
    }
}