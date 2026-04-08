<?php

namespace App\Storage\ValueObject;

/**
 * BalanceStatus
 *
 * Represents the balance state of a cluster.
 *
 * DESIGN:
 * - Immutable (readonly)
 * - Used for admin/debug endpoints
 */
final class BalanceStatus
{
    public function __construct(
        private readonly bool $isBalanced,
        private readonly float $deviationPercent,
        private readonly string $message
    ) {}

    public function isBalanced(): bool
    {
        return $this->isBalanced;
    }

    public function getDeviationPercent(): float
    {
        return $this->deviationPercent;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return [
            'isBalanced' => $this->isBalanced,
            'deviationPercent' => $this->deviationPercent,
            'message' => $this->message,
        ];
    }
}