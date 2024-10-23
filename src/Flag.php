<?php

namespace Fullbay\FeatureFlags;

use Throwable;

readonly class Flag
{

    const FF_ENV_PREFIX = 'FEATURE_FLAG_';

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
        $envKey = self::FF_ENV_PREFIX . $this->key;
        $envValue = getenv($envKey);
        $value = $envValue !== false ? $envValue : $this->value;

        return match (true) {
            in_array($value, ['on', 'On', 'ON']) => true,
            in_array($value, ['off', 'Off', 'OFF']) => false,
            isset($value) => $value,
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
