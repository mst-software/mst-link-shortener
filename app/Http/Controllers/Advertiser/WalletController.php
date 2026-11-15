<?php

namespace App\Http\Controllers\Advertiser;

use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Http\Controllers\Controller;
use App\Models\TopUp;
use App\Models\Transaction;
use App\Services\Advertiser\TopUpService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request, TopUpService $topUps): View
    {
        $user = $request->user();

        $pendingTransfer = TopUp::query()
            ->where('user_id', $user->id)
            ->where('provider', TopUpProvider::Sepay)
            ->where('status', TopUpStatus::Pending)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return view('advertiser.wallet', [
            'pendingTransfer' => $pendingTransfer,
            'topUps' => TopUp::query()->where('user_id', $user->id)->latest('id')->paginate(10, ['*'], 'topups_page'),
            'transactions' => Transaction::query()->where('user_id', $user->id)->latest('id')->paginate(10, ['*'], 'transactions_page'),
            'minimumMicro' => Settings::int('advertiser_min_topup_micro'),
            'minimumVnd' => $topUps->minimumVnd(),
            'vndPerUsd' => max(1, Settings::int('vnd_per_usd')),
            'bank' => [
                'name' => (string) Settings::get('sepay_bank_name', ''),
                'account_number' => (string) Settings::get('sepay_account_number', ''),
                'account_name' => (string) Settings::get('sepay_account_name', ''),
            ],
        ]);
    }
}
