<?php

namespace Fullbay\FeatureFlags;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
        private readonly Client $client,
        private readonly TrafficType $trafficType,
        private readonly mixed $trafficId,
        private readonly array $attributes,
        private $errorHandler = null,
        private readonly float $timeout = 0.5
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
            $response = $this->client->request(
                'GET',
                '/client/get-all-treatments-with-config',
                [
                    'query' => [
                        'keys' => $this->getQueryKeys(),
                        'attributes' => $this->getQueryAttributes(),
                    ],
                    'timeout' => $this->timeout,
                ]
            );

            $decodedResponse = json_decode(
                $response->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            foreach (
                $decodedResponse[$this->trafficType->value]
                as $flag => $data
            ) {
                $this->flags->put(
                    $flag,
                    new Flag($flag, $data['treatment'], $data['config'] ?? '')
                );
            }
        } catch (JsonException $exception) {
            ($this->errorHandler)($exception);
            if ($this->throwExceptions) {
                throw new FeatureFlagsException(
                    'Could not parse flag data',
                    previous: $exception
                );
            }
        } catch (GuzzleException $exception) {
            ($this->errorHandler)($exception);
            if ($this->throwExceptions) {
                throw new FeatureFlagsException(
                    'Network error',
                    previous: $exception
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
    private function getQueryAttributes(): string
    {
        return json_encode($this->attributes ?? [], JSON_THROW_ON_ERROR);
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
        float $timeout = 0.5
    ): self {
        $client = new Client([
            'base_uri' => $url,
            'headers' => [
                'Authorization' => $authToken,
            ],
        ]);

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
