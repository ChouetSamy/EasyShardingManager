<?php

namespace App\Storage\Metrics;

/**
 * Metrics specific to MongoDB clusters.
 *
 * ARCHITECTURE NOTE:
 * ====================
 *
 * This class holds MongoDB-specific metrics for two purposes:
 *
 * 1. OPERATIONAL MONITORING (public fields)
 *    - chunkCount: total number of chunks
 *    - balancerActive: whether MongoDB's balancer is running
 *    - databaseCount: number of databases/shards
 *    These are exposed through toArray() for dashboards
 *
 * 2. INTERNAL TRANSLATION (chunkDistribution)
 *    - chunkDistribution: detailed per-shard chunk breakdown
 *    - This is MongoDB-specific and NOT part of DriverMetricsInterface
 *    - Only accessed by MetricsTranslator for normalization
 *    - External code should NOT depend on this field
 *
 * WHY NOT IN THE INTERFACE?
 * - DriverMetricsInterface must be implementable by ALL drivers
 * - CockroachDB has range_distribution, Redis has slot distribution
 * - No common abstraction that makes sense across all databases
 * - Including it would force every driver to implement driver-specific methods
 *
 * CONSEQUENCE FOR CONSUMERS:
 * - If you need balance analysis: call analyzeBalance() directly
 * - It queries MongoDB fresh and returns a clean BalanceStatus object
 * - Don't try to parse chunkDistribution from getMetrics()
 */
final class MongoMetrics implements DriverMetricsInterface
{
    public function __construct(
        private readonly int $chunkCount,
        private readonly bool $balancerActive,
        private readonly int $databaseCount,
        private readonly array $chunkDistribution,
    ) {
    }

    public function getChunkCount(): int
    {
        return $this->chunkCount;
    }

    public function isBalancerActive(): bool
    {
        return $this->balancerActive;
    }

    public function getDatabaseCount(): int
    {
        return $this->databaseCount;
    }

    /**
     * Get per-shard chunk distribution.
     *
     * ⚠️  INTERNAL USE ONLY
     *
     * This method is intended for internal use by MetricsTranslator only.
     * External code should NOT depend on this.
     *
     * If you need balance analysis, use StorageInterface::analyzeBalance() instead.
     *
     * @return array Array of ['shard' => string, 'chunkCount' => int]
     * @internal
     */
    public function getChunkDistribution(): array
    {
        return $this->chunkDistribution;
    }

    public function toArray(): array
    {
        return [
            'chunkCount' => $this->chunkCount,
            'balancerActive' => $this->balancerActive,
            'databaseCount' => $this->databaseCount,
            'chunkDistribution'=> $this->chunkDistribution,
        ];
    }


}