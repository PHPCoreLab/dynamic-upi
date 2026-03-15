<?php

declare(strict_types=1);

namespace PHPCoreLab\UpiGateway\Core;

use PHPCoreLab\UpiGateway\Enums\Environment;

final class GatewayConfig
{
    /**
     * @param string                      $activeProvider  Key matching a registered provider name
     * @param Environment                 $environment     sandbox | live — applies to ALL providers
     * @param array<string, array<mixed>> $providers       Keyed by provider name; values are credentials
     */
    public function __construct(
        private string $activeProvider,
        private Environment $environment = Environment::Sandbox,
        private readonly array $providers = [],
    ) {}

    public function getActiveProvider(): string
    {
        return $this->activeProvider;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function isSandbox(): bool
    {
        return $this->environment->isSandbox();
    }

    public function isLive(): bool
    {
        return $this->environment->isLive();
    }

    /**
     * Hot-swap the active provider at runtime (e.g. from an admin panel action).
     * The change is in-process only; persist it to your config store separately.
     */
    public function setActiveProvider(string $name): void
    {
        $this->activeProvider = $name;
    }

    /**
     * Switch environment at runtime.
     * WARNING: never switch to live in a test context.
     */
    public function setEnvironment(Environment $environment): void
    {
        $this->environment = $environment;
    }

    /** @return array<mixed> */
    public function getProviderConfig(string $name): array
    {
        return $this->providers[$name] ?? [];
    }

    public static function fromArray(array $config): self
    {
        return new self(
            activeProvider: $config['active_provider'],
            environment:    Environment::from($config['environment'] ?? 'sandbox'),
            providers:      $config['providers'] ?? [],
        );
    }
}
