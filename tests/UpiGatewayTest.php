<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\Tests;

use PHPUnit\Framework\TestCase;
use DynamicUpi\UpiGateway\Contracts\UpiProviderInterface;
use DynamicUpi\UpiGateway\Core\GatewayConfig;
use DynamicUpi\UpiGateway\DTOs\OrderPayload;
use DynamicUpi\UpiGateway\DTOs\PaymentState;
use DynamicUpi\UpiGateway\DTOs\PaymentStatus;
use DynamicUpi\UpiGateway\DTOs\QrResult;
use DynamicUpi\UpiGateway\DTOs\RefundResult;
use DynamicUpi\UpiGateway\Exceptions\ProviderNotFoundException;
use DynamicUpi\UpiGateway\UpiGateway;

final class UpiGatewayTest extends TestCase
{
    private function makeConfig(string $active = 'mock_a'): GatewayConfig
    {
        // No built-in provider credentials — we register mocks manually
        return GatewayConfig::fromArray([
            'active_provider' => $active,
            'providers'       => [
                'razorpay' => ['key_id' => 'x', 'key_secret' => 'x'],
                'phonepe'  => ['merchant_id' => 'x', 'salt_key' => 'x'],
                'paytm'    => ['mid' => 'x', 'merchant_key' => 'x'],
            ],
        ]);
    }

    private function mockProvider(string $name, PaymentState $state = PaymentState::Success): UpiProviderInterface
    {
        return new class ($name, $state) implements UpiProviderInterface {
            public function __construct(
                private readonly string $providerName,
                private readonly PaymentState $state,
            ) {}

            public function generateQr(OrderPayload $order): QrResult
            {
                return new QrResult('txn_' . $this->providerName, 'upi://pay?ref=' . $this->providerName);
            }

            public function checkStatus(string $transactionId): PaymentStatus
            {
                return new PaymentStatus($transactionId, $this->state, 5000);
            }

            public function refund(string $transactionId, int $amountPaisa): RefundResult
            {
                return new RefundResult('ref_' . $this->providerName, true);
            }

            public function parseWebhook(string $rawBody, array $headers): PaymentStatus
            {
                return new PaymentStatus('txn', $this->state);
            }

            public function getName(): string { return $this->providerName; }
        };
    }

    public function testCreateQrUsesActiveProvider(): void
    {
        $gateway = new UpiGateway($this->makeConfig());
        $gateway->registerProvider('mock_a', $this->mockProvider('mock_a'));

        $qr = $gateway->createQr(new OrderPayload(orderId: 'ORD-001', amountPaisa: 5000));

        $this->assertSame('txn_mock_a', $qr->transactionId);
        $this->assertStringContainsString('mock_a', $qr->qrString);
    }

    public function testSwitchProviderChangesActiveAdapter(): void
    {
        $gateway = new UpiGateway($this->makeConfig('mock_a'));
        $gateway->registerProvider('mock_a', $this->mockProvider('mock_a'));
        $gateway->registerProvider('mock_b', $this->mockProvider('mock_b'));

        $gateway->switchProvider('mock_b');

        $qr = $gateway->createQr(new OrderPayload(orderId: 'ORD-002', amountPaisa: 1000));
        $this->assertSame('txn_mock_b', $qr->transactionId);
    }

    public function testSwitchToUnregisteredProviderThrows(): void
    {
        $this->expectException(ProviderNotFoundException::class);

        $gateway = new UpiGateway($this->makeConfig());
        $gateway->switchProvider('nonexistent');
    }

    public function testAvailableProvidersIncludesBuiltIns(): void
    {
        $gateway = new UpiGateway($this->makeConfig());
        $gateway->registerProvider('mock_a', $this->mockProvider('mock_a'));

        $providers = $gateway->availableProviders();

        $this->assertContains('razorpay', $providers);
        $this->assertContains('phonepe', $providers);
        $this->assertContains('paytm', $providers);
        $this->assertContains('mock_a', $providers);
    }

    public function testCheckStatusDelegatesToActiveProvider(): void
    {
        $gateway = new UpiGateway($this->makeConfig());
        $gateway->registerProvider('mock_a', $this->mockProvider('mock_a', PaymentState::Success));

        $status = $gateway->checkStatus('txn_123');

        $this->assertSame(PaymentState::Success, $status->state);
        $this->assertTrue($status->isTerminal());
    }

    public function testRefundDelegatesToActiveProvider(): void
    {
        $gateway = new UpiGateway($this->makeConfig());
        $gateway->registerProvider('mock_a', $this->mockProvider('mock_a'));

        $result = $gateway->refund('txn_123', 5000);

        $this->assertTrue($result->success);
        $this->assertSame('ref_mock_a', $result->refundId);
    }
}
