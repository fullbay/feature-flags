<?php

declare(strict_types=1);

namespace Tests;

use Fullbay\FeatureFlags\Flag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FlagTest extends TestCase
{
    public static function onProvider(): array
    {
        return [['on'], ['On'], ['ON']];
    }

    #[DataProvider('onProvider')]
    public function testGetValueReturnsTrueForOn($value): void
    {
        $flag = new Flag('test', $value);
        $this->assertTrue($flag->getValue());
    }

    public static function offProvider(): array
    {
        return [['off'], ['Off'], ['OFF']];
    }

    #[DataProvider('offProvider')]
    public function testGetValueReturnsFalseForOff($value): void
    {
        $flag = new Flag('test', $value);
        $this->assertFalse($flag->getValue());
    }

    public function testGetValueReturnsValueIfSetAndNotOnOrOff(): void
    {
        $flag = new Flag('test-flag', 'foo');

        $this->assertEquals('foo', $flag->getValue());
    }

    public function testEmptyValueReturnsFalse(): void
    {
        $flag = new Flag('test');

        $this->assertNull($flag->getValue());
    }

    public function testEmptyConfigReturnsNull(): void
    {
        $flag = new Flag('test');

        $this->assertNull($flag->getConfig());
    }

    public function testConfigReturnsArrayIfJsonDecodable(): void
    {
        $flag = new Flag('test', config: 'not-json-decodable');
        $this->assertEquals('not-json-decodable', $flag->getConfig());

        $array = ['test' => 'value'];
        $arrayString = json_encode($array);

        $flag = new Flag('test', config: $arrayString);
        $flagConfig = $flag->getConfig();

        $this->assertIsArray($flagConfig);
        $this->assertSame($array, $flagConfig);
    }
}
