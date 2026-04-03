<?php

namespace App\Storage\Metrics;

/**
 * Metrics specific to Redis clusters.
 */
final class RedisMetrics implements DriverMetricsInterface
{
    public function __construct(
        private readonly int $memoryUsed,
        private readonly int $connectedClients,
        private readonly float $opsPerSec,
        private readonly array $slotDistribution
    ) {
    }

    public function getMemoryUsed(): int
    {
        return $this->memoryUsed;
    }

    public function getConnectedClients(): int
    {
        return $this->connectedClients;
    }

    public function getOpsPerSec(): float
    {
        return $this->opsPerSec;
    }

    public function getSlotDistribution(): array
    {
        return $this->slotDistribution;
    }

    public function toArray(): array
    {
        return [
            'memoryUsed' => $this->memoryUsed,
            'connectedClients' => $this->connectedClients,
            'opsPerSec' => $this->opsPerSec,
            'slotDistribution' => $this->slotDistribution,
        ];
    }
}
