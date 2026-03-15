<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\Tests\Core;

use PHPUnit\Framework\TestCase;
use DynamicUpi\UpiGateway\Core\GatewayConfig;
use DynamicUpi\UpiGateway\Enums\Environment;

final class GatewayConfigTest extends TestCase
{
    public function testDefaultsToSandbox(): void
    {
        $config = GatewayConfig::fromArray([
            'active_provider' => 'razorpay',
            'providers'       => [],
        ]);

        $this->assertSame(Environment::Sandbox, $config->getEnvironment());
        $this->assertTrue($config->isSandbox());
        $this->assertFalse($config->isLive());
    }

    public function testParsesLiveEnvironment(): void
    {
        $config = GatewayConfig::fromArray([
            'active_provider' => 'razorpay',
            'environment'     => 'live',
            'providers'       => [],
        ]);

        $this->assertSame(Environment::Live, $config->getEnvironment());
        $this->assertTrue($config->isLive());
        $this->assertFalse($config->isSandbox());
    }

    public function testParsesSandboxEnvironment(): void
    {
        $config = GatewayConfig::fromArray([
            'active_provider' => 'phonepe',
            'environment'     => 'sandbox',
            'providers'       => [],
        ]);

        $this->assertSame(Environment::Sandbox, $config->getEnvironment());
    }

    public function testCanSwitchEnvironmentAtRuntime(): void
    {
        $config = GatewayConfig::fromArray([
            'active_provider' => 'razorpay',
            'environment'     => 'sandbox',
            'providers'       => [],
        ]);

        $this->assertTrue($config->isSandbox());
        $config->setEnvironment(Environment::Live);
        $this->assertTrue($config->isLive());
    }

    public function testInvalidEnvironmentThrows(): void
    {
        $this->expectException(\ValueError::class);

        GatewayConfig::fromArray([
            'active_provider' => 'razorpay',
            'environment'     => 'production', // invalid — must be 'sandbox' or 'live'
            'providers'       => [],
        ]);
    }
}
