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
        private readonly int $replicationFactor
    ) {
    }

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

    public function toArray(): array
    {
        return [
            'rangeCount' => $this->rangeCount,
            'nodeCount' => $this->nodeCount,
            'replicationFactor' => $this->replicationFactor,
        ];
    }
}