<?php

namespace App\Services\Advertiser;

use App\Services\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads the incoming bank transactions of the receiving account from the SePay API.
 * Any failure is logged and treated as "nothing new", so a cron run never crashes.
 */
class SepayClient
{
    /**
     * @return list<array{id: string, amount: int, content: string, raw: array<string, mixed>}>
     */
    public function recentIncoming(): array
    {
        $token = config('services.sepay.api_token');

        if (! is_string($token) || $token === '') {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get(rtrim((string) config('services.sepay.base_url'), '/').'/userapi/transactions/list', [
                    'account_number' => (string) Settings::get('sepay_account_number', ''),
                    'limit' => 100,
                ]);
        } catch (Throwable $exception) {
            Log::warning('SePay request failed: '.$exception->getMessage());

            return [];
        }

        if (! $response->successful()) {
            Log::warning('SePay answered with HTTP '.$response->status());

            return [];
        }

        $incoming = [];

        foreach ((array) $response->json('transactions', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $amount = (int) round((float) ($row['amount_in'] ?? 0));

            if ($amount <= 0) {
                continue;
            }

            $incoming[] = [
                'id' => (string) ($row['id'] ?? ''),
                'amount' => $amount,
                'content' => (string) ($row['transaction_content'] ?? ''),
                'raw' => $row,
            ];
        }

        return $incoming;
    }
}
