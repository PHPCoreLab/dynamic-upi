<?php

declare(strict_types=1);

namespace DynamicUpi\UpiGateway\Adapters\Paytm;

use GuzzleHttp\Client;
use DynamicUpi\UpiGateway\Contracts\UpiProviderInterface;
use DynamicUpi\UpiGateway\DTOs\OrderPayload;
use DynamicUpi\UpiGateway\DTOs\PaymentStatus;
use DynamicUpi\UpiGateway\DTOs\QrResult;
use DynamicUpi\UpiGateway\DTOs\RefundResult;
use DynamicUpi\UpiGateway\Enums\Environment;
use DynamicUpi\UpiGateway\Exceptions\ProviderException;

/**
 * Paytm Dynamic QR adapter.
 *
 * Sandbox: https://pguat.paytm.io/
 * Live:    https://securegw.paytm.in/
 *
 * Paytm requires a JWT signed with the merchant key for every API call.
 * Full implementation coming — stub is in place so the provider can be
 * registered and the interface contract is satisfied.
 *
 * @see https://developer.paytm.com/docs/qr-code/
 */
final class PaytmAdapter implements UpiProviderInterface
{
    private const BASE_URLS = [
        'sandbox' => 'https://pguat.paytm.io/',
        'live'    => 'https://securegw.paytm.in/',
    ];

    private Client $http;
    private string $mid;
    private string $merchantKey;
    private Environment $environment;

    public function __construct(array $config, Environment $environment = Environment::Sandbox)
    {
        $this->environment = $environment;

        if ($environment->isLive()) {
            $this->mid         = $config['live_mid']          ?? $config['mid']          ?? throw new \InvalidArgumentException('paytm live_mid required');
            $this->merchantKey = $config['live_merchant_key'] ?? $config['merchant_key'] ?? throw new \InvalidArgumentException('paytm live_merchant_key required');
        } else {
            $this->mid         = $config['sandbox_mid']          ?? $config['mid']          ?? throw new \InvalidArgumentException('paytm sandbox_mid required');
            $this->merchantKey = $config['sandbox_merchant_key'] ?? $config['merchant_key'] ?? throw new \InvalidArgumentException('paytm sandbox_merchant_key required');
        }

        $this->http = new Client([
            'base_uri' => self::BASE_URLS[$environment->value],
            'timeout'  => 10,
        ]);
    }

    public function generateQr(OrderPayload $order): QrResult
    {
        throw new ProviderException('Paytm generateQr not yet implemented.', $this->getName());
    }

    public function checkStatus(string $transactionId): PaymentStatus
    {
        throw new ProviderException('Paytm checkStatus not yet implemented.', $this->getName());
    }

    public function refund(string $transactionId, int $amountPaisa): RefundResult
    {
        throw new ProviderException('Paytm refund not yet implemented.', $this->getName());
    }

    public function parseWebhook(string $rawBody, array $headers): PaymentStatus
    {
        throw new ProviderException('Paytm parseWebhook not yet implemented.', $this->getName());
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function getName(): string
    {
        return 'paytm';
    }
}
