<?php

namespace App\Storage;

use App\Storage\ValueObject\BalanceStatus;
use App\Storage\ValueObject\HealthStatus;
use App\Storage\ValueObject\StorageMetrics;

/**
 * Contract for all storage drivers (CockroachDB, MongoDB, Redis).
 *
 * Architecture principles:
 * - Separation of concerns (configuration, actions, observability)
 * - Fail-fast for critical operations
 * - Never fail for observability (health)
 *  * Contract for all storage drivers (CockroachDB, MongoDB, Redis).
 *
 * This interface defines a clear separation between:
 * - Commands (actions that can fail and throw exceptions)
 * - Queries (read operations that must always return a result)
 *
 * Design philosophy: "Safety first" + Observability-first architecture.
 */
interface StorageInterface
{
    /**
     * Configure the storage driver with runtime parameters.
     *
     * Why this exists:
     * - Symfony services are shared (singleton-like)
     * - We need dynamic configuration (DSN, credentials, etc.)
     * - Allows multi-instance / multi-tenant usage
     *
     * Example config:
     * [
     *   'driver' => 'mongo',
     *   'dsn' => 'mongodb://localhost:27017'
     * ]
     */
    public function configure(array $config): void;

    /**
     * Establish connection to the storage system.
     *
     * @throws \RuntimeException If connection fails
     */
    public function connect(): void;

    /**
     * Returns the health status of the storage system.
     *
     * MUST NEVER throw exceptions.
     * Errors must be embedded in the HealthStatus object.
     */
    public function getHealth(): HealthStatus;

    /**
     * Returns storage metrics.
     *
     * @throws \RuntimeException If metrics cannot be retrieved
     */
    public function getMetrics(): StorageMetrics;

    /**
     * List all shards.
     */
    public function listShards(): array;

    /**
     * Rebalance shards.
     *
     * @throws \RuntimeException If rebalance fails
     */
    public function rebalance(string $strategy): void;

    /**
     * Unique driver identifier.
     *
     * Example:
     * - cockroach
     * - mongo
     * - redis
     */
    public static function getDriverName(): string;

    /**
     * Analyze the balance status of the storage system.
     *
     * @return BalanceStatus Always returns a valid object
     */
    public function analyzeBalance(): BalanceStatus;
}
