<?php

namespace App\Storage\Insight;

/**
 * Insight
 *
 * Represents a human-readable recommendation.
 */
final class Insight
{
    public function __construct(
        public readonly string $level,      // info | warning | critical
        public readonly string $message,
        public readonly ?string $action = null
    ) {}
}