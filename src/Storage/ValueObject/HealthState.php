<?php

namespace App\Storage\ValueObject;

/**
 * Represents the health state of a storage system.
 *
 * Why enum instead of string:
 * - Enforces strict typing (prevents invalid values)
 * - Avoids runtime bugs caused by typos (e.g. "UPS", "DOWNN")
 * - Provides better IDE support (auto-completion, refactoring safety)
 * - Makes the domain model explicit and self-documented
 *
 * Why not store this in database:
 * - This is a transient runtime state (not a persisted entity)
 * - Health is computed dynamically (via checks on external systems)
 * - Storing it would introduce inconsistency (stale data)
 *
 * This enum is part of the domain model and represents
 * the real-time status of a storage system.
 */
enum HealthState: string
{
    case UP = 'UP';
    case DOWN = 'DOWN';
    case DEGRADED = 'DEGRADED';
}