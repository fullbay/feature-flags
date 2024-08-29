<?php

namespace Fullbay\FeatureFlags;

use Throwable;

readonly class Flag
{
    public function __construct(
        private string $key,
        private mixed $value = null,
        private string $config = ''
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return match (true) {
            in_array($this->value, ['on', 'On', 'ON']) => true,
            in_array($this->value, ['off', 'Off', 'OFF']) => false,
            isset($this->value) => $this->value,
            default => null,
        };
    }

    /**
     * @return null|array<array-key, mixed>|string
     */
    public function getConfig()
    {
        try {
            return json_decode($this->config, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->config ?: null;
        }
    }
}
