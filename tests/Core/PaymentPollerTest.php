<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\Tests\Core;

use PHPUnit\Framework\TestCase;
use DynamicUpi\UpiGateway\Contracts\UpiProviderInterface;
use DynamicUpi\UpiGateway\Core\PaymentPoller;
use DynamicUpi\UpiGateway\DTOs\OrderPayload;
use DynamicUpi\UpiGateway\DTOs\PaymentState;
use DynamicUpi\UpiGateway\DTOs\PaymentStatus;
use DynamicUpi\UpiGateway\DTOs\QrResult;
use DynamicUpi\UpiGateway\DTOs\RefundResult;

final class PaymentPollerTest extends TestCase
{
    private function makeProvider(array $statuses): UpiProviderInterface
    {
        $queue = $statuses;

        return new class ($queue) implements UpiProviderInterface {
            public function __construct(private array $queue) {}

            public function checkStatus(string $transactionId): PaymentStatus
            {
                return array_shift($this->queue)
                    ?? new PaymentStatus($transactionId, PaymentState::Pending);
            }

            public function generateQr(OrderPayload $order): QrResult
            {
                return new QrResult('txn', 'upi://pay');
            }

            public function refund(string $transactionId, int $amountPaisa): RefundResult
            {
                return new RefundResult('ref', true);
            }

            public function parseWebhook(string $rawBody, array $headers): PaymentStatus
            {
                return new PaymentStatus('txn', PaymentState::Pending);
            }

            public function getName(): string { return 'mock'; }
        };
    }

    public function testReturnsImmediatelyOnFirstTerminalStatus(): void
    {
        $provider = $this->makeProvider([
            new PaymentStatus('txn_1', PaymentState::Success, 5000),
        ]);

        $poller = new PaymentPoller($provider, intervalMs: 0);
        $status = $poller->poll('txn_1');

        $this->assertSame(PaymentState::Success, $status->state);
    }

    public function testPollsUntilTerminal(): void
    {
        $provider = $this->makeProvider([
            new PaymentStatus('txn_2', PaymentState::Pending),
            new PaymentStatus('txn_2', PaymentState::Pending),
            new PaymentStatus('txn_2', PaymentState::Success, 10000),
        ]);

        $poller  = new PaymentPoller($provider, intervalMs: 0);
        $ticks   = 0;
        $status  = $poller->poll('txn_2', function () use (&$ticks) { $ticks++; });

        $this->assertSame(PaymentState::Success, $status->state);
        $this->assertSame(3, $ticks);
    }

    public function testReturnsLastStatusAfterMaxAttempts(): void
    {
        $pending  = new PaymentStatus('txn_3', PaymentState::Pending);
        $provider = $this->makeProvider(array_fill(0, 5, $pending));

        $poller = new PaymentPoller($provider, intervalMs: 0, maxAttempts: 3);
        $status = $poller->poll('txn_3');

        $this->assertSame(PaymentState::Pending, $status->state);
    }

    public function testFailedPaymentIsTerminal(): void
    {
        $provider = $this->makeProvider([
            new PaymentStatus('txn_4', PaymentState::Failed, null, null, 'Insufficient funds'),
        ]);

        $poller = new PaymentPoller($provider, intervalMs: 0);
        $status = $poller->poll('txn_4');

        $this->assertSame(PaymentState::Failed, $status->state);
        $this->assertTrue($status->isTerminal());
    }
}
