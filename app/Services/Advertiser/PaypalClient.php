<?php

namespace App\Services\Advertiser;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Minimal PayPal REST (Orders v2) client: create an order and capture it after the buyer approved.
 */
class PaypalClient
{
    /**
     * Create a USD order for the amount and return its id and the url where the buyer approves it.
     *
     * @return array{id: string, approve_url: string}
     *
     * @throws RuntimeException
     */
    public function createOrder(int $usdMicro, string $returnUrl, string $cancelUrl): array
    {
        try {
            $response = $this->request()
                ->post($this->baseUrl().'/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'amount' => ['currency_code' => 'USD', 'value' => number_format($usdMicro / 1_000_000, 2, '.', '')],
                        'description' => 'LinkCash advertiser wallet top up',
                    ]],
                    'application_context' => [
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                        'user_action' => 'PAY_NOW',
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('PayPal is unreachable.');
        }

        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new RuntimeException('PayPal could not create the order.');
        }

        $approveUrl = collect((array) $response->json('links', []))
            ->first(fn (mixed $link): bool => is_array($link) && in_array($link['rel'] ?? null, ['payer-action', 'approve'], true))['href'] ?? null;

        if (! is_string($approveUrl)) {
            throw new RuntimeException('PayPal did not return an approval link.');
        }

        return ['id' => $response->json('id'), 'approve_url' => $approveUrl];
    }

    /**
     * Capture an approved order and return PayPal's answer. When the capture call fails (for example a
     * timeout after PayPal already took the money, so every retry says "already captured"), the order is
     * read back and returned if it turns out to be completed.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function captureOrder(string $orderId): array
    {
        $url = $this->baseUrl().'/v2/checkout/orders/'.rawurlencode($orderId);

        try {
            $response = $this->request()->withBody('{}', 'application/json')->post($url.'/capture');

            if ($response->successful()) {
                return (array) $response->json();
            }

            $order = $this->request()->get($url);
        } catch (ConnectionException) {
            throw new RuntimeException('PayPal is unreachable.');
        }

        if ($order->successful() && $order->json('status') === 'COMPLETED') {
            return (array) $order->json();
        }

        throw new RuntimeException('PayPal could not capture the order.');
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(20);
    }

    private function accessToken(): string
    {
        try {
            $response = Http::withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.secret'))
                ->asForm()
                ->acceptJson()
                ->timeout(20)
                ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (Throwable) {
            throw new RuntimeException('PayPal is unreachable.');
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException('PayPal rejected the credentials.');
        }

        return $response->json('access_token');
    }

    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
