<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\Adapters\PhonePe;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use DynamicUpi\UpiGateway\Contracts\UpiProviderInterface;
use DynamicUpi\UpiGateway\DTOs\OrderPayload;
use DynamicUpi\UpiGateway\DTOs\PaymentState;
use DynamicUpi\UpiGateway\DTOs\PaymentStatus;
use DynamicUpi\UpiGateway\DTOs\QrResult;
use DynamicUpi\UpiGateway\DTOs\RefundResult;
use DynamicUpi\UpiGateway\Enums\Environment;
use DynamicUpi\UpiGateway\Exceptions\ProviderException;
use DynamicUpi\UpiGateway\Exceptions\WebhookVerificationException;

final class PhonePeAdapter implements UpiProviderInterface
{
    /**
     * PhonePe has completely separate base URLs for sandbox and live.
     *
     * Sandbox: https://api-preprod.phonepe.com/apis/pg-sandbox/
     * Live:    https://api.phonepe.com/apis/hermes/
     *
     * @see https://developer.phonepe.com/v1/docs/uat-sandbox-testing
     */
    private const BASE_URLS = [
        'sandbox' => 'https://api-preprod.phonepe.com/apis/pg-sandbox/',
        'live'    => 'https://api.phonepe.com/apis/hermes/',
    ];

    private Client $http;
    private string $merchantId;
    private string $saltKey;
    private int    $saltIndex;
    private Environment $environment;

    public function __construct(array $config, Environment $environment = Environment::Sandbox)
    {
        $this->environment = $environment;

        if ($environment->isLive()) {
            $this->merchantId = $config['live_merchant_id'] ?? $config['merchant_id'] ?? throw new \InvalidArgumentException('phonepe live_merchant_id required');
            $this->saltKey    = $config['live_salt_key']    ?? $config['salt_key']    ?? throw new \InvalidArgumentException('phonepe live_salt_key required');
            $this->saltIndex  = (int) ($config['live_salt_index'] ?? $config['salt_index'] ?? 1);
        } else {
            $this->merchantId = $config['sandbox_merchant_id'] ?? $config['merchant_id'] ?? throw new \InvalidArgumentException('phonepe sandbox_merchant_id required');
            $this->saltKey    = $config['sandbox_salt_key']    ?? $config['salt_key']    ?? throw new \InvalidArgumentException('phonepe sandbox_salt_key required');
            $this->saltIndex  = (int) ($config['sandbox_salt_index'] ?? $config['salt_index'] ?? 1);
        }

        $this->http = new Client([
            'base_uri' => self::BASE_URLS[$environment->value],
            'timeout'  => 10,
        ]);
    }

    public function generateQr(OrderPayload $order): QrResult
    {
        $payload = [
            'merchantId'            => $this->merchantId,
            'merchantTransactionId' => $order->orderId,
            'amount'                => $order->amountPaisa,
            'paymentInstrument'     => ['type' => 'UPI_QR'],
        ];

        $encoded  = base64_encode(json_encode($payload));
        $checksum = hash('sha256', $encoded . '/pg/v1/pay' . $this->saltKey) . '###' . $this->saltIndex;

        try {
            $response = $this->http->post('pg/v1/pay', [
                'json'    => ['request' => $encoded],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-VERIFY'     => $checksum,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            $qr   = $data['data']['instrumentResponse']['qrData'] ?? '';

            return new QrResult(
                transactionId: $order->orderId,
                qrString:      $qr,
                raw:           $data,
            );
        } catch (GuzzleException $e) {
            throw new ProviderException($e->getMessage(), $this->getName(), previous: $e);
        }
    }

    public function checkStatus(string $transactionId): PaymentStatus
    {
        $path     = "/pg/v1/status/{$this->merchantId}/{$transactionId}";
        $checksum = hash('sha256', $path . $this->saltKey) . '###' . $this->saltIndex;

        try {
            $response = $this->http->get("pg/v1/status/{$this->merchantId}/{$transactionId}", [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'X-VERIFY'      => $checksum,
                    'X-MERCHANT-ID' => $this->merchantId,
                ],
            ]);

            $data  = json_decode((string) $response->getBody(), true);
            $code  = $data['code'] ?? '';
            $state = match ($code) {
                'PAYMENT_SUCCESS'       => PaymentState::Success,
                'PAYMENT_ERROR',
                'TRANSACTION_NOT_FOUND' => PaymentState::Failed,
                default                 => PaymentState::Pending,
            };

            return new PaymentStatus(
                transactionId: $transactionId,
                state:         $state,
                amountPaisa:   $data['data']['amount'] ?? null,
                providerRef:   $data['data']['providerReferenceId'] ?? null,
                raw:           $data,
            );
        } catch (GuzzleException $e) {
            throw new ProviderException($e->getMessage(), $this->getName(), previous: $e);
        }
    }

    public function refund(string $transactionId, int $amountPaisa): RefundResult
    {
        $payload = [
            'merchantId'            => $this->merchantId,
            'merchantTransactionId' => 'REFUND_' . $transactionId . '_' . time(),
            'originalTransactionId' => $transactionId,
            'amount'                => $amountPaisa,
            'callbackUrl'           => '',
        ];

        $encoded  = base64_encode(json_encode($payload));
        $checksum = hash('sha256', $encoded . '/pg/v1/refund' . $this->saltKey) . '###' . $this->saltIndex;

        try {
            $response = $this->http->post('pg/v1/refund', [
                'json'    => ['request' => $encoded],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-VERIFY'     => $checksum,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return new RefundResult(
                refundId: $data['data']['merchantTransactionId'] ?? 'unknown',
                success:  (bool) ($data['success'] ?? false),
                message:  $data['message'] ?? null,
                raw:      $data,
            );
        } catch (GuzzleException $e) {
            throw new ProviderException($e->getMessage(), $this->getName(), previous: $e);
        }
    }

    public function parseWebhook(string $rawBody, array $headers): PaymentStatus
    {
        $xVerify    = $headers['X-VERIFY'] ?? '';
        [$received] = explode('###', $xVerify . '###');
        $expected   = hash('sha256', $rawBody . $this->saltKey);

        if (!hash_equals($expected, $received)) {
            throw new WebhookVerificationException('PhonePe webhook checksum mismatch.');
        }

        $outer   = json_decode($rawBody, true);
        $decoded = json_decode(base64_decode($outer['response'] ?? ''), true);
        $code    = $decoded['code'] ?? '';

        $state = match ($code) {
            'PAYMENT_SUCCESS' => PaymentState::Success,
            'PAYMENT_ERROR'   => PaymentState::Failed,
            default           => PaymentState::Pending,
        };

        return new PaymentStatus(
            transactionId: $decoded['data']['merchantTransactionId'] ?? '',
            state:         $state,
            amountPaisa:   $decoded['data']['amount'] ?? null,
            providerRef:   $decoded['data']['providerReferenceId'] ?? null,
            raw:           $decoded,
        );
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function getName(): string
    {
        return 'phonepe';
    }
}
