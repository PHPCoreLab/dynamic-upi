<?php

declare(strict_types=1);

namespace PHPCoreLab\UpiGateway\Tests\Adapters;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PHPCoreLab\UpiGateway\Adapters\Razorpay\RazorpayAdapter;
use PHPCoreLab\UpiGateway\DTOs\OrderPayload;
use PHPCoreLab\UpiGateway\DTOs\PaymentState;
use PHPCoreLab\UpiGateway\Exceptions\WebhookVerificationException;

final class RazorpayAdapterTest extends TestCase
{
    private function makeAdapter(array $responses): RazorpayAdapter
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $adapter = new RazorpayAdapter(['key_id' => 'test_key', 'key_secret' => 'test_secret']);

        $ref = new \ReflectionProperty(RazorpayAdapter::class, 'http');
        $ref->setValue($adapter, new Client(['handler' => $handler]));

        return $adapter;
    }

    public function testGenerateQrReturnsQrResult(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'id'        => 'qr_test123',
                'image_url' => 'https://example.com/qr.png',
                'close_by'  => time() + 900,
            ])),
        ]);

        $result = $adapter->generateQr(new OrderPayload(orderId: 'ORD-001', amountPaisa: 10000));

        $this->assertSame('qr_test123', $result->transactionId);
        $this->assertSame('https://example.com/qr.png', $result->qrImageUrl);
    }

    public function testCheckStatusReturnsPendingWhenNoPayments(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode(['items' => []])),
        ]);

        $status = $adapter->checkStatus('qr_test123');

        $this->assertSame(PaymentState::Pending, $status->state);
        $this->assertFalse($status->isTerminal());
    }

    public function testCheckStatusReturnsSuccessOnCaptured(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'items' => [['id' => 'pay_abc', 'status' => 'captured', 'amount' => 10000]],
            ])),
        ]);

        $status = $adapter->checkStatus('qr_test123');

        $this->assertSame(PaymentState::Success, $status->state);
        $this->assertTrue($status->isTerminal());
        $this->assertSame(10000, $status->amountPaisa);
    }

    public function testWebhookVerificationFailsOnBadSignature(): void
    {
        $this->expectException(WebhookVerificationException::class);

        $this->makeAdapter([])->parseWebhook(
            '{"event":"payment.captured","payload":{"payment":{"entity":{}}}}',
            ['X-Razorpay-Signature' => 'invalid_signature']
        );
    }

    public function testWebhookParsesSuccessEvent(): void
    {
        $body   = json_encode([
            'event'   => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => ['id' => 'pay_xyz', 'amount' => 5000, 'acquirer_data' => []],
                ],
            ],
        ]);
        $secret    = 'test_secret';
        $signature = hash_hmac('sha256', $body, $secret);

        $adapter = new RazorpayAdapter(['key_id' => 'test_key', 'key_secret' => $secret]);
        $status  = $adapter->parseWebhook($body, ['X-Razorpay-Signature' => $signature]);

        $this->assertSame(PaymentState::Success, $status->state);
    }
}
