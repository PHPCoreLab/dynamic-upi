<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\Tests\Adapters;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use DynamicUpi\UpiGateway\Adapters\PhonePe\PhonePeAdapter;
use DynamicUpi\UpiGateway\DTOs\OrderPayload;
use DynamicUpi\UpiGateway\DTOs\PaymentState;
use DynamicUpi\UpiGateway\Exceptions\WebhookVerificationException;

final class PhonePeAdapterTest extends TestCase
{
    private function makeAdapter(array $responses): PhonePeAdapter
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $adapter = new PhonePeAdapter([
            'merchant_id' => 'TESTMERCHANT',
            'salt_key'    => 'test-salt-key',
            'salt_index'  => 1,
        ]);

        $ref = new \ReflectionProperty(PhonePeAdapter::class, 'http');
        $ref->setValue($adapter, new Client(['handler' => $handler]));

        return $adapter;
    }

    public function testGenerateQrReturnsQrString(): void
    {
        $qrData  = 'upi://pay?pa=merchant@upi&am=100.00&tr=ORD-001';
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'success' => true,
                'code'    => 'PAYMENT_INITIATED',
                'data'    => [
                    'instrumentResponse' => [
                        'qrData' => $qrData,
                    ],
                ],
            ])),
        ]);

        $result = $adapter->generateQr(new OrderPayload(orderId: 'ORD-001', amountPaisa: 10000));

        $this->assertSame('ORD-001', $result->transactionId);
        $this->assertSame($qrData, $result->qrString);
    }

    public function testCheckStatusReturnsPending(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'success' => false,
                'code'    => 'PAYMENT_PENDING',
                'data'    => [],
            ])),
        ]);

        $status = $adapter->checkStatus('ORD-001');

        $this->assertSame(PaymentState::Pending, $status->state);
        $this->assertFalse($status->isTerminal());
    }

    public function testCheckStatusReturnsSuccess(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'success' => true,
                'code'    => 'PAYMENT_SUCCESS',
                'data'    => [
                    'amount'               => 10000,
                    'providerReferenceId'  => 'UPI_REF_123',
                ],
            ])),
        ]);

        $status = $adapter->checkStatus('ORD-001');

        $this->assertSame(PaymentState::Success, $status->state);
        $this->assertTrue($status->isTerminal());
        $this->assertSame('UPI_REF_123', $status->providerRef);
    }

    public function testCheckStatusReturnsFailed(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'success' => false,
                'code'    => 'PAYMENT_ERROR',
                'data'    => [],
            ])),
        ]);

        $status = $adapter->checkStatus('ORD-001');

        $this->assertSame(PaymentState::Failed, $status->state);
        $this->assertTrue($status->isTerminal());
    }

    public function testWebhookVerificationFailsOnBadChecksum(): void
    {
        $this->expectException(WebhookVerificationException::class);

        $this->makeAdapter([])->parseWebhook(
            '{"response":"dGVzdA=="}',
            ['X-VERIFY' => 'badhash###1']
        );
    }

    public function testWebhookParsesSuccessEvent(): void
    {
        $saltKey = 'test-salt-key';
        $inner   = base64_encode(json_encode([
            'code' => 'PAYMENT_SUCCESS',
            'data' => [
                'merchantTransactionId' => 'ORD-001',
                'amount'                => 10000,
                'providerReferenceId'   => 'UPI_REF_999',
            ],
        ]));
        $body     = json_encode(['response' => $inner]);
        $checksum = hash('sha256', $body . $saltKey) . '###1';

        $adapter = new PhonePeAdapter([
            'merchant_id' => 'TESTMERCHANT',
            'salt_key'    => $saltKey,
            'salt_index'  => 1,
        ]);

        $status = $adapter->parseWebhook($body, ['X-VERIFY' => $checksum]);

        $this->assertSame(PaymentState::Success, $status->state);
        $this->assertSame('ORD-001', $status->transactionId);
        $this->assertSame('UPI_REF_999', $status->providerRef);
    }
}
