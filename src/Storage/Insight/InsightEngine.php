<?php

namespace App\Storage\Insight;

use App\Storage\ValueObject\NormalizedMetrics;

/**
 * InsightEngine
 *
 * Transforms normalized metrics into actionable insights.
 *
 * PURPOSE:
 * - Provide recommendations
 * - Abstract technical complexity
 * - Enable SaaS features
 */
final class InsightEngine
{
    /**
     * @return Insight[]
     */
    public function generate(NormalizedMetrics $metrics): array
    {
        $insights = [];

        // 🔴 Rebalance needed
        if ($metrics->rebalanceNeeded) {
            $insights[] = new Insight(
                level: 'warning',
                message: 'Le cluster est déséquilibré',
                action: 'Lancer un rebalance automatique'
            );
        }

        // 🔥 High pressure
        if ($metrics->clusterPressure > 0.8) {
            $insights[] = new Insight(
                level: 'critical',
                message: 'Cluster sous forte charge',
                action: 'Ajouter des ressources ou scaler'
            );
        }

        // ⚠️ Medium pressure
        if ($metrics->clusterPressure > 0.6) {
            $insights[] = new Insight(
                level: 'warning',
                message: 'Charge élevée détectée',
                action: 'Surveiller le cluster'
            );
        }

        // ✅ Healthy
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