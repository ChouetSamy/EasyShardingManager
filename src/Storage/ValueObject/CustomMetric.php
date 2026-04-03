<?php

namespace App\Storage\ValueObject;

/**
 * Immutable Value Object for custom metrics.
 */
final class CustomMetric
{
    public function __construct(
        private readonly array $data
    ) {}

    public function toArray(): array
    {
        return $this->data;
    }
}