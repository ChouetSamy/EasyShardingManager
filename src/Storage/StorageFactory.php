<?php

/**
 * Factory responsible for resolving and configuring storage drivers.
 *
 * Design choices:
 *
 * - Uses Symfony tagged services (extensible architecture)
 * - Avoids switch/case (open/closed principle)
 * - Supports dynamic configuration via configure()
 * - Uses Symfony dependency injection with tagged services
 *   instead of a switch/case.
 *
 * Workflow:
 * 1. Find matching driver by name
 * 2. Inject runtime configuration
 * 3. Return ready-to-use driver
 * 
 *  * Why:
 * - Allows easy extension (plugin-like architecture)
 * - New drivers can be added without modifying this class
 * - Reduces coupling between components
 *
 * - Drivers are identified by a unique "driver name"
 *
 * Example:
 * - cockroach
 * - mongo
 * - redis
 *
 * - Each driver must implement:
 *   - StorageInterface
 *   - static getDriverName(): string
 *
 * This factory acts as an entry point for all storage systems.
 */



namespace App\Storage;

use App\Storage\Exception\InvalidStorageException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Factory responsible for resolving and configuring storage drivers.
 *
 * Modern Symfony approach:
 * - Uses #[TaggedIterator] instead of YAML configuration
 * - Reduces config complexity
 * - Keeps dependency injection close to the code
 */
final class StorageFactory
{
    /**
     * @param iterable<StorageInterface> $drivers
     */
    public function __construct(
        #[TaggedIterator('storage.driver')]
        private iterable $drivers
    ) {
    }

    /**
     * Create and configure a storage driver.
     *
     * @throws InvalidStorageException
     */
    public function create(array $config): StorageInterface
    {
        $requestedDriver = $config['driver'] ?? null;

        if ($requestedDriver === null) {
            throw new InvalidStorageException('Driver not specified');
        }

        foreach ($this->drivers as $driver) {
            if ($driver::getDriverName() === $requestedDriver) {
                $cloned = clone $driver;//on clone l'objet pour évité de le modifié
                $cloned->configure($config);
                return $cloned;
            }
        }

        throw new InvalidStorageException(
            \sprintf('Unsupported storage driver "%s"', $requestedDriver)
        );
    }
}

 /**
 *
 * ## Why we clone the driver
 *
 * Symfony services are shared by default (singleton pattern).
 * This means all calls to StorageFactory share the same driver instance.
 *
 * Without cloning, this scenario causes a silent bug:
 *
 *   $factory->create(['driver' => 'cockroach', 'dsn' => 'db1']); // configure db1
 *   $factory->create(['driver' => 'cockroach', 'dsn' => 'db2']); // overwrites db1 !
 *
 * The first driver instance is now silently pointing to db2.
 * No exception is thrown. Data goes to the wrong database.
 *
 * ## The temporary fix
 *
 * We clone the driver before configuring it.
 * Each call to create() returns an independent instance,
 * isolated from the shared Symfony service.
 *
 *   $cloned = clone $driver;   // independent copy
 *   $cloned->configure($config);
 *   return $cloned;            // safe to use
 *
 * ## Trade-off
 *
 * Cloning means a new object is allocated on each create() call.
 * For a dashboard/orchestration tool with low call frequency,
 * this cost is negligible compared to the safety guarantee.
 *
 * ## Future consideration
 *
 * If drivers become stateless (no mutable properties after configure),
 * cloning can be removed. Until then, keep it.
 *
 * @throws InvalidStorageException If driver is unknown or not specified
 */