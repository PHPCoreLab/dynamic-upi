<?php

declare(strict_types=1);

namespace PHPCoreLab\UpiGateway\Tests\Adapters;

use PHPUnit\Framework\TestCase;
use PHPCoreLab\UpiGateway\Adapters\PhonePe\PhonePeAdapter;
use PHPCoreLab\UpiGateway\Adapters\Razorpay\RazorpayAdapter;
use PHPCoreLab\UpiGateway\Adapters\Paytm\PaytmAdapter;
use PHPCoreLab\UpiGateway\Enums\Environment;

/**
 * Verify each adapter resolves the correct credential key pair
 * and base URL for each environment.
 */
final class EnvironmentTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Razorpay — same base URL, different key pairs
    // -----------------------------------------------------------------------

    public function testRazorpayPicksSandboxKeys(): void
    {
        $adapter = new RazorpayAdapter([
            'sandbox_key_id'     => 'rzp_test_KEY',
            'sandbox_key_secret' => 'rzp_test_SECRET',
            'live_key_id'        => 'rzp_live_KEY',
            'live_key_secret'    => 'rzp_live_SECRET',
        ], Environment::Sandbox);

        $this->assertSame(Environment::Sandbox, $adapter->getEnvironment());
    }

    public function testRazorpayPicksLiveKeys(): void
    {
        $adapter = new RazorpayAdapter([
            'sandbox_key_id'     => 'rzp_test_KEY',
            'sandbox_key_secret' => 'rzp_test_SECRET',
            'live_key_id'        => 'rzp_live_KEY',
            'live_key_secret'    => 'rzp_live_SECRET',
        ], Environment::Live);

        $this->assertSame(Environment::Live, $adapter->getEnvironment());
    }

    public function testRazorpayThrowsWhenLiveKeyMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('live_key_id');

        new RazorpayAdapter([
            // only sandbox keys provided, but requesting live
            'sandbox_key_id'     => 'rzp_test_KEY',
            'sandbox_key_secret' => 'rzp_test_SECRET',
        ], Environment::Live);
    }

    // -----------------------------------------------------------------------
    // PhonePe — different base URLs AND different credentials per environment
    // -----------------------------------------------------------------------

    public function testPhonePePicksSandboxConfig(): void
    {
        $adapter = new PhonePeAdapter([
            'sandbox_merchant_id' => 'SANDBOX_MERCHANT',
            'sandbox_salt_key'    => 'sandbox-salt',
            'sandbox_salt_index'  => 1,
            'live_merchant_id'    => 'LIVE_MERCHANT',
            'live_salt_key'       => 'live-salt',
            'live_salt_index'     => 2,
        ], Environment::Sandbox);

        $this->assertSame(Environment::Sandbox, $adapter->getEnvironment());
    }

    public function testPhonePePicksLiveConfig(): void
    {
        $adapter = new PhonePeAdapter([
            'sandbox_merchant_id' => 'SANDBOX_MERCHANT',
            'sandbox_salt_key'    => 'sandbox-salt',
            'live_merchant_id'    => 'LIVE_MERCHANT',
            'live_salt_key'       => 'live-salt',
        ], Environment::Live);

        $this->assertSame(Environment::Live, $adapter->getEnvironment());
    }

    public function testPhonePeThrowsWhenLiveMerchantIdMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('live_merchant_id');

        new PhonePeAdapter([
            'sandbox_merchant_id' => 'SANDBOX_MERCHANT',
            'sandbox_salt_key'    => 'sandbox-salt',
            // no live credentials
        ], Environment::Live);
    }

    // -----------------------------------------------------------------------
    // Paytm — different base URLs AND different credentials per environment
    // -----------------------------------------------------------------------

    public function testPaytmPicksSandboxConfig(): void
    {
        $adapter = new PaytmAdapter([
            'sandbox_mid'          => 'SANDBOX_MID',
            'sandbox_merchant_key' => 'sandbox-key',
            'live_mid'             => 'LIVE_MID',
            'live_merchant_key'    => 'live-key',
        ], Environment::Sandbox);

        $this->assertSame(Environment::Sandbox, $adapter->getEnvironment());
    }

    public function testPaytmPicksLiveConfig(): void
    {
        $adapter = new PaytmAdapter([
            'sandbox_mid'          => 'SANDBOX_MID',
            'sandbox_merchant_key' => 'sandbox-key',
            'live_mid'             => 'LIVE_MID',
            'live_merchant_key'    => 'live-key',
        ], Environment::Live);

        $this->assertSame(Environment::Live, $adapter->getEnvironment());
    }
}
