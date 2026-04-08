<?php

namespace App\Storage\Metrics;

/**
 * Metrics specific to CockroachDB clusters.
 */
final class CockroachMetrics implements DriverMetricsInterface
{
    public function __construct(
        private readonly int $rangeCount,
        private readonly int $nodeCount,
        private readonly int $replicationFactor,
        private readonly array $rangeDistribution,
    ) {}

    public function getRangeCount(): int
    {
        return $this->rangeCount;
    }

    public function getNodeCount(): int
    {
        return $this->nodeCount;
    }

    public function getReplicationFactor(): int
    {
        return $this->replicationFactor;
    }

    public function getRangeDistribution(): array
    {
        return $this->rangeDistribution;
    }

    public function toArray(): array
    {
        return [
            'rangeCount' => $this->rangeCount,
            'nodeCount' => $this->nodeCount,
            'replicationFactor' => $this->replicationFactor,
            'rangeDistribution' => $this->rangeDistribution,
        ];
    }
}