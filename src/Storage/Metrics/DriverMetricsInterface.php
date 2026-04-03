<?php

namespace App\Storage\Metrics;

/**
 * Contract for all driver-specific metrics.
 */
interface DriverMetricsInterface
{
    /**
     * Convert metrics to array for API output.
     */
    public function toArray(): array;
}