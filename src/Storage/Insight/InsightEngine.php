<?php

namespace App\Storage\Insight;

use App\Storage\ValueObject\NormalizedMetrics;

/**
 * InsightEngine
 *
 * CORE INTELLIGENCE LAYER of EasyShardManager.
 *
 * PURPOSE:
 * - Convert normalized technical metrics into business-level insights
 * - Enable non-expert users to understand system state
 * - Provide actionable recommendations
 *
 * ARCHITECTURE ROLE:
 * DriverMetrics → NormalizedMetrics → InsightEngine → Insights
 *
 * FUTURE EXTENSIONS:
 * - Alerting system
 * - Auto-remediation
 * - SaaS analytics
 */
final class InsightEngine
{
    /**
     * Generate insights from normalized metrics.
     *
     * @param NormalizedMetrics $metrics
     *
     * @return Insight[]
     */
    public function generate(NormalizedMetrics $metrics): array
    {
        $insights = [];

        /**
         * Rebalance detection
         *
         * If true → system detected uneven data distribution
         */
        if ($metrics->rebalanceNeeded) {
            $insights[] = new Insight(
                level: 'warning',
                message: 'Le cluster est déséquilibré',
                action: 'Lancer un rééquilibrage automatique'
            );
        }

        /**
         * High pressure detection
         *
         * > 0.8 → critical
         */
        if ($metrics->clusterPressure > 0.8) {
            $insights[] = new Insight(
                level: 'critical',
                message: 'Cluster sous forte charge',
                action: 'Scaler horizontalement ou optimiser les requêtes'
            );
        }

        /**
         * Medium pressure
         */
        elseif ($metrics->clusterPressure > 0.6) {
            $insights[] = new Insight(
                level: 'warning',
                message: 'Charge élevée détectée',
                action: 'Surveiller les performances'
            );
        }

        /**
         * Healthy system
         */
        if (empty($insights)) {
            $insights[] = new Insight(
                level: 'info',
                message: 'Cluster en bonne santé',
                action: null
            );
        }

        return $insights;
    }
}