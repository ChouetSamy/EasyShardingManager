<?php

namespace App\Storage\Insight;

/**
 * Insight
 *
 * Represents a high-level, human-readable diagnostic produced by the system.
 *
 * PURPOSE:
 * - Translate technical metrics into actionable insights
 * - Help users (CTO, SRE, devs) make decisions quickly
 * - Serve as a foundation for future SaaS features (alerts, automation, recommendations)
 *
 * DESIGN:
 * - Immutable Value Object
 * - UI-friendly (ready to display)
 * - Technology-agnostic (works for Mongo, Cockroach, Redis, etc.)
 *
 * LEVELS:
 * - info     → everything is normal
 * - warning  → attention needed
 * - critical → immediate action required
 */
final class Insight
{
    /**
     * @param string $level
     *  Severity level of the insight:
     *  - "info"
     *  - "warning"
     *  - "critical"
     *
     * @param string $message
     *  Human-readable explanation of the situation
     *
     * @param string|null $action
     *  Suggested action to resolve the issue (optional)
     */
    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly ?string $action = null
    ) {}
}