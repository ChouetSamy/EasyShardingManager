<?php

namespace App\Tests\Storage;

use App\Storage\StorageFactory;
use App\Storage\Driver\CockroachStorage;
use App\Storage\Exception\InvalidStorageException;
use PHPUnit\Framework\TestCase;

class StorageFactoryTest extends TestCase
{
    public function testCreateCockroachDriver(): void
    {
        $driver = $this->createMock(CockroachStorage::class);
        $driver->method('getDriverName')->willReturn('cockroach');

        $factory = new StorageFactory([$driver]);

        $result = $factory->create([
            'driver' => 'cockroach',
            'dsn' => 'pgsql://user@localhost/db'
        ]);

        $this->assertInstanceOf(CockroachStorage::class, $result);
    }

    public function testUnknownDriverThrows(): void
    {
        $this->expectException(InvalidStorageException::class);

        $factory = new StorageFactory([]);

        $factory->create([
            'driver' => 'unknown'
        ]);
    }
}