<?php

namespace App\Tests\Storage;

use App\Storage\ValueObject\HealthStatus;
use App\Storage\ValueObject\HealthState;
use PHPUnit\Framework\TestCase;

class HealthStatusTest extends TestCase
{
    public function testHealthStatusCreation(): void
    {
        $status = new HealthStatus(
            HealthState::UP,
            12.5,
            null,
            new \DateTimeImmutable()
        );

        $this->assertEquals('UP', $status->getStatus()->value);
        $this->assertEquals(12.5, $status->getLatencyMs());
        $this->assertNull($status->getError());
    }
}