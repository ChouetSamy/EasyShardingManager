<?php

namespace App\Storage\Metrics;

/**
 * Metrics specific to MongoDB clusters.
 */
final class MongoMetrics implements DriverMetricsInterface
{
    public function __construct(
        private readonly int $chunkCount,
        private readonly bool $balancerActive,
        private readonly int $databaseCount
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

    public function toArray(): array
    {
        return [
            'chunkCount' => $this->chunkCount,
            'balancerActive' => $this->balancerActive,
            'databaseCount' => $this->databaseCount,
        ];
    }
}