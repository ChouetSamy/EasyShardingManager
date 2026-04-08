<?php

namespace App\Storage\Service;

use App\Storage\ValueObject\NormalizedMetrics;
use App\Storage\Metrics\CockroachMetrics;
use App\Storage\Metrics\MongoMetrics;
use App\Storage\Metrics\RedisMetrics;

/**
 * MetricsTranslator
 *
 * ROLE:
 * - Convert driver-specific metrics into normalized metrics
 * - This is the ONLY place aware of driver differences
 *
 * IMPORTANT:
 * - No DB calls here
 * - No business logic (just interpretation)
 */
final class MetricsTranslator
{
    public function translate(object $metrics): NormalizedMetrics
    {
        return match (true) {
            $metrics instanceof CockroachMetrics => $this->fromCockroach($metrics),
            $metrics instanceof MongoMetrics => $this->fromMongo($metrics),
            $metrics instanceof RedisMetrics => $this->fromRedis($metrics),
            default => throw new \RuntimeException('Unsupported metrics type')
        };
    }

    /**
     * =========================
     * CockroachDB
     * =========================
     */
    private function fromCockroach(CockroachMetrics $metrics): NormalizedMetrics
    {
        $score = $this->computeDistribution(
            $metrics->getRangeDistribution(),
            'range_count'
        );

        return new NormalizedMetrics(
            dataDistribution: $score,
            rebalanceNeeded: $score < 0.8,
            clusterPressure: min(1, $metrics->getNodeCount() / 10)
        );
    }

    /**
     * =========================
     * MongoDB
     * =========================
     */
    private function fromMongo(MongoMetrics $metrics): NormalizedMetrics
    {
        $score = $this->computeDistribution(
            $metrics->getChunkDistribution(),
            'chunkCount'
        );

        return new NormalizedMetrics(
            dataDistribution: $score,
            rebalanceNeeded: !$metrics->isBalancerActive() || $score < 0.75,
            clusterPressure: 0.6 // simplifié (à améliorer plus tard)
        );
    }

    /**
     * =========================
     * Redis
     * =========================
     */
    private function fromRedis(RedisMetrics $metrics): NormalizedMetrics
    {
        $pressure = min(1, $metrics->getOpsPerSec() / 10000);

        return new NormalizedMetrics(
            dataDistribution: 1.0, // Redis ≠ data distribution
            rebalanceNeeded: false,
            clusterPressure: $pressure
        );
    }

    /**
     * Generic distribution computation
     *
     * @param array $rows
     * @param string $key
     */
    private function computeDistribution(array $rows, string $key): float
    {
        if (empty($rows)) {
            return 1.0;
        }

        $counts = array_map(fn($row) => $row[$key], $rows);

        $avg = array_sum($counts) / count($counts);

        if ($avg == 0) {
            return 1.0;
        }

        $maxDeviation = 0;

        foreach ($counts as $count) {
            $deviation = abs($count - $avg) / $avg;
            $maxDeviation = max($maxDeviation, $deviation);
        }

        // 1 = parfait, 0 = catastrophe
        return max(0, 1 - $maxDeviation);
    }
}