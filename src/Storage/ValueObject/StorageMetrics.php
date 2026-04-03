<?php

namespace App\Storage\ValueObject;

use App\Storage\Metrics\DriverMetricsInterface;
use App\Storage\ValueObject\CustomMetric;

/**
 * Immutable Value Object representing storage metrics.
 */
final class StorageMetrics
{
    public function __construct(
        private readonly int $userCount,
        private readonly string $storageDriver,
        private readonly int $regionCount,
        private readonly int $shardCount,
        private readonly DriverMetricsInterface $driverMetrics,
        private readonly ?CustomMetric $customMetrics = null
    ) {
    }

    public function getUserCount(): int
    {
        return $this->userCount;
    }

    public function getStorageDriver(): string
    {
        return $this->storageDriver;
    }

    public function getRegionCount(): int
    {
        return $this->regionCount;
    }

    public function getShardCount(): int
    {
        return $this->shardCount;
    }

    public function getDriverMetrics(): DriverMetricsInterface
    {
        return $this->driverMetrics;
    }

    public function getCustomMetrics(): ?CustomMetric
    {
        return $this->customMetrics;
    }

    public function toArray(): array
    {
        return [
            'userCount' => $this->userCount,
            'storageDriver' => $this->storageDriver,
            'regionCount' => $this->regionCount,
            'shardCount' => $this->shardCount,
            'driverMetrics' => $this->driverMetrics->toArray(),
            'customMetrics' => $this->customMetrics?->toArray(),
        ];
    }
}