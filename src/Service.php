<?php

namespace Fullbay\FeatureFlags;

use SplitIO\Sdk;
use JsonException;
use Throwable;

class Service
{
    private FlagCollection $flags;

    private bool $flagsFetched = false;

    private bool $throwExceptions = false;

    const FF_ENV_PREFIX = 'FEATURE_FLAG_';

    /**
     * @param  array<string, mixed>  $attributes
     */
    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable  $errorHandler
     */
    public function __construct(
        private readonly Sdk\Client $client,
        private readonly TrafficType $trafficType,
        private readonly mixed $trafficId,
        private readonly array $attributes,
        private $errorHandler = null,
        private readonly float $timeout = 2.0
    ) {
        $this->errorHandler =
            $errorHandler ??
            function (Throwable $e) {
                error_log($e->getMessage());
            };
    }

    private function fetchFlags(): void
    {
        if ($this->flagsFetched) {
            return;
        }

        $this->flagsFetched = true;
        $this->flags = new FlagCollection();

        try {
            $responseData = $this->client->getTreatmentsWithConfigByFlagSets(
                $this->getQueryKeys(), 
                ['backend'], 
                $this->getQueryAttributes());
                
            // DONT REMOVE THIS - Uncomment to log ff response data. 
            error_log("feature flags returned:");
            error_log(json_encode($responseData));

            foreach ($responseData as $flag => $data) {
                $this->flags->put(
                    $flag,
                    new Flag($flag, $data['treatment'], $data['config'] ?? '')
                );
            }
        } catch (Throwable $exception) {
            ($this->errorHandler)($exception);
            if ($this->throwExceptions) {
                throw new FeatureFlagsException(
                    'Internal error',
                    previous: $exception
                );
            }
        }
    }

    public function getFlag(string $key): Flag
    {
        $envKey = self::FF_ENV_PREFIX . $key;
        $envValue = getenv($envKey);

        // this allows env var to override the flag value, for debug/testing
        if ($envValue !== false) {
            return new Flag($key, $envValue);
        }

        $this->fetchFlags();
        return $this->flags->get($key) ?? new Flag($key);
    }

    /**
     * @param  array<string>  $keys
     */
    public function getFlags(array $keys = []): FlagCollection
    {
        $this->fetchFlags();

        if (empty($keys)) {
            return $this->flags;
        }

        $flags = $this->flags->only($keys);
        foreach ($keys as $key) {
            if (!$flags->has($key)) {
                $flags->put($key, new Flag($key));
            }
        }

        return $flags;
    }

    /**
     * @throws JsonException
     */
    private function getQueryAttributes(): array
    {
        return $this->attributes ?? [];
    }

    /**
     * @throws JsonException
     */
    private function getQueryKeys(): string
    {
        return json_encode(
            [
                [
                    'matchingKey' => $this->trafficId,
                    'trafficType' => $this->trafficType->value,
                ],
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param string $url
     * @param string $authToken
     * @param TrafficType $trafficType
     * @param mixed $trafficId
     * @param array $attributes
     * @param callable|null $errorHandler
     * @param float $timeout
     * @return Service
     */
    public static function make(
        string $url,
        string $authToken,
        TrafficType $trafficType,
        mixed $trafficId,
        array $attributes,
        callable $errorHandler = null,
        float $timeout = 2.0
    ): self {
        if (empty($_SERVER['SPLITIO_SDK_API_KEY'])) {
            error_log('WARNING: SPLITIO_SDK_API_KEY is not set. NO FEATURE FLAGS WILL BE USED.');
            return new self(
                new Sdk\Client(),
                $trafficType,
                $trafficId,
                $attributes,
                $errorHandler,
                $timeout
            );
        }
        $splitFactory = Sdk::factory($_SERVER['SPLITIO_SDK_API_KEY'], array(
            'cache' => array('adapter' => 'predis', 
                            'parameters' => [
                                'scheme' => 'tcp', 
                                'host' => $_SERVER['REDIS_HOST'], 
                                'port' => 6379, 
                                'timeout' => 881
                            ], 
                            'options' => [
                                'prefix' => 'ff.' . $_SERVER['ENVIRONMENT'],
                            ]
                        )
        ));
        $client = $splitFactory->client();
        return new self(
            $client,
            $trafficType,
            $trafficId,
            $attributes,
            $errorHandler,
            $timeout
        );
    }

    public function throwOnErrors(): self
    {
        $this->throwExceptions = true;

        return $this;
    }
}
