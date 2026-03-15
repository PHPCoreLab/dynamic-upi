<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\Core;

use DynamicUpi\UpiGateway\Contracts\UpiProviderInterface;
use DynamicUpi\UpiGateway\Exceptions\ProviderNotFoundException;

final class ProviderRegistry
{
    /** @var array<string, UpiProviderInterface> */
    private array $providers = [];

    public function register(string $name, UpiProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function resolve(string $name): UpiProviderInterface
    {
        return $this->providers[$name]
            ?? throw new ProviderNotFoundException("Provider '{$name}' is not registered.");
    }

    /** @return string[] */
    public function registered(): array
    {
        return array_keys($this->providers);
    }
}
