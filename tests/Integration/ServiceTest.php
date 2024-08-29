<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fullbay\FeatureFlags\Flag;
use Fullbay\FeatureFlags\Service;
use Fullbay\FeatureFlags\TrafficType;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    private function getService(): Service
    {
        return Service::make(
            $_ENV['ff_url'],
            $_ENV['ff_auth_token'],
            TrafficType::ENTITY,
            $_ENV['ff_entity_id'],
            []
        );
    }

    public function testItCanFetchAllFlags(): void
    {
        $service = $this->getService();
        $flags = $service->getFlags();

        $this->assertGreaterThan(0, count($flags));
        $this->assertContainsOnlyInstancesOf(Flag::class, $flags);
    }
}
