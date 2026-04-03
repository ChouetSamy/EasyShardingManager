<?php

namespace App\Storage\ValueObject;

/**
 * Immutable Value Object representing the health status of a storage.
 */
final class HealthStatus
{
    public function __construct(
        private readonly HealthState $status,
        private readonly float $latencyMs,
        private readonly ?string $error,
        private readonly \DateTimeImmutable $checkedAt
    ) {
    }

    public function getStatus(): HealthState
    {
        return $this->status;
    }

    public function getLatencyMs(): float
    {
        return $this->latencyMs;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCheckedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }

    /**
     * Helper to quickly know if the storage is considered healthy.
     */
    public function isHealthy(): bool
    {
        return $this->status === HealthState::UP;
    }

    /**
     * Helper for degraded state.
     */
    public function isDegraded(): bool
    {
        return $this->status === HealthState::DEGRADED;
    }

    /**
     * Helper for failure state.
     */
    public function isDown(): bool
    {
        return $this->status === HealthState::DOWN;
    }
}