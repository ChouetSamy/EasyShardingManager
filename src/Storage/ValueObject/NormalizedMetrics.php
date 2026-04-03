<?php

namespace App\Storage\ValueObject;

/**
 * NormalizedMetrics
 *
 * Provides a unified abstraction layer over heterogeneous storage metrics.
 *
 * PURPOSE:
 * - Allow comparison across different storage systems
 * - Hide technical complexity from the UI
 * - Enable decision-making (rebalance, alerts, etc.)
 *
 * DESIGN:
 * - Values are normalized between 0 and 1 (or simple booleans)
 * - Independent from underlying storage technology
 */
final class NormalizedMetrics
{
    public function __construct(
        public readonly float $dataDistribution,   // close to 0 (bad) → close to 1 (perfect)
        public readonly bool $rebalanceNeeded, // true = cluster needs rebalancing false = cluster is considered healthy
        public readonly float $clusterPressure     // 0 → 1 close to 0 : idle, close to 1 : almost satured
    ) {
    }
}