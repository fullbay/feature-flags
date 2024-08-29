<?php

declare(strict_types=1);

namespace Tests;

use Fullbay\FeatureFlags\FeatureFlagsException;
use Fullbay\FeatureFlags\Service;
use Fullbay\FeatureFlags\TrafficType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

class ServiceTest extends TestCase
{
    private function getService(mixed $client = null, array $attributes = []): Service
    {
        return $client
            ? new Service($client, TrafficType::ENTITY, 123, $attributes)
            : Service::make('url.com', 'authtoken', TrafficType::ENTITY, 123, $attributes);
    }

    public function testItCanBeCreatedStatically(): void
    {
        $attributes = ['test' => 'data'];
        $service = $this->getService(attributes: $attributes);

        $reflection = new ReflectionClass($service);

        $this->assertEquals(Client::class, $reflection->getProperty('client')->getType());
        $this->assertEquals(
            TrafficType::ENTITY->value,
            $reflection->getProperty('trafficType')->getValue($service)->value
        );
        $this->assertEquals(123, $reflection->getProperty('trafficId')->getValue($service));
        $this->assertEquals($attributes, $reflection->getProperty('attributes')->getValue($service));
    }

    public function testItDoesNotMakeAnApiCallUntilAFlagIsRequested(): void
    {
        $requests = [];
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200)]));
        $handlerStack->push(Middleware::history($requests));

        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);

        $this->assertCount(0, $requests);

        $service->getFlags();

        $this->assertCount(1, $requests);
    }

    public function testItCachesFetchedFlags(): void
    {
        $requests = [];
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200), new Response(200)]));
        $handlerStack->push(Middleware::history($requests));

        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);

        $service->getFlags();
        $service->getFlags();

        $this->assertCount(1, $requests);
    }

    public function testItCanGetASingleFlag(): void
    {
        $requests = [];
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(
                200,
                body: json_encode([
                    TrafficType::ENTITY->value => [
                        'test_flag' => ['treatment' => 'off', 'config' => 'test config'],
                        'another_flag' => ['treatment' => 'off', 'config' => null],
                    ],
                ])
            ),
        ]));
        $handlerStack->push(Middleware::history($requests));
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);

        $flag = $service->getFlag('test_flag');
        $this->assertFalse($flag->getValue());
        $this->assertEquals('test config', $flag->getConfig());
    }

    public function testItCanGetMultipleFlags(): void
    {
        $requests = [];
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(
                200,
                body: json_encode([
                    TrafficType::ENTITY->value => [
                        'test_flag1' => ['treatment' => 'off', 'config' => 'test config'],
                        'test_flag2' => ['treatment' => 'on', 'config' => null],
                        'another_flag' => ['treatment' => 'off', 'config' => null],
                    ],
                ])
            ),
        ]));
        $handlerStack->push(Middleware::history($requests));
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);

        $flags = $service->getFlags(['test_flag1', 'test_flag2']);

        $this->assertArrayHasKey('test_flag1', $flags);
        $this->assertFalse($flags->get('test_flag1')->getValue());
        $this->assertEquals('test config', $flags->get('test_flag1')->getConfig());

        $this->assertArrayHasKey('test_flag2', $flags);
        $this->assertArrayNotHasKey('another_flag', $flags);
    }

    public function testGettingUnknownFlagsReturnsEmptyFlags(): void
    {
        $requests = [];
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200), new Response(200)]));
        $handlerStack->push(Middleware::history($requests));

        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);

        $flag = $service->getFlag('known-bad-flag');
        $this->assertEquals('known-bad-flag', $flag->getKey());
        $this->assertNull($flag->getValue());
        $this->assertEmpty($flag->getConfig());

        $flags = $service->getFlags(['known-bad-flag-1', 'known-bad-flag-2']);
        $this->assertNotEmpty($flags->get('known-bad-flag-1'));
        $this->assertEquals('known-bad-flag-1', $flags['known-bad-flag-1']->getKey());
        $this->assertNull($flags['known-bad-flag-1']->getValue());
        $this->assertEmpty($flags['known-bad-flag-1']->getConfig());
        $this->assertEquals('known-bad-flag-2', $flags['known-bad-flag-2']->getKey());
        $this->assertNull($flags['known-bad-flag-2']->getValue());
        $this->assertEmpty($flags['known-bad-flag-2']->getConfig());
    }

    public function testItCanThrowExceptionsOnError(): void
    {
        $requests = [];
        $handlerStack = HandlerStack::create(new MockHandler([
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ]));
        $handlerStack->push(Middleware::history($requests));

        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getService($client);

        try {
            $service->getFlag('test-flag');
        } catch (Throwable) {
            $this->fail('Unexpected exception thrown');
        }

        $service = $this->getService($client);
        $this->expectException(FeatureFlagsException::class);
        $service->throwOnErrors()->getFlag('test-flag');
    }
}
