<?php

namespace App\Storage\ValueObject;

/**
 * BalanceStatus
 *
 * Represents the balance state of a distributed storage system.
 *
 * PURPOSE:
 * - Provide a human-readable and machine-usable diagnostic
 * - Separate analysis from action (rebalance)
 *
 * DESIGN:
 * - Immutable Value Object
 * - Used by all storage drivers
 */
final class BalanceStatus
{
    /**
     * @param bool $isBalanced
     *  True if the cluster is considered balanced
     *
     * @param float $deviationPercent
     *  Represents the maximum deviation between nodes (0 → perfect, 100 → worst)
     *
     * @param string $message
     *  Human-readable explanation (for UI / logs)
     */
    public function __construct(
        public readonly bool $isBalanced,
        public readonly float $deviationPercent,
        public readonly string $message
    ) {}
}