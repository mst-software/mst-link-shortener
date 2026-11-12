<?php

namespace App\Http\Controllers\Member;

use App\Enums\TransactionAction;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreWithdrawalRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Settings;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $minimum = Settings::int('min_withdraw_micro');

        return view('member.withdraws', [
            'balance' => $user->balance_micro,
            'processing' => $user->processing_micro,
            'totalPaid' => (int) Transaction::query()
                ->where('user_id', $user->id)
                ->where('action', TransactionAction::WithdrawPaid)
                ->sum('amount_micro'),
            'minimum' => $minimum,
            'canWithdraw' => $user->balance_micro >= $minimum,
        ]);
    }

    public function store(StoreWithdrawalRequest $request, WalletService $wallet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $micro = $request->amountMicro();

        try {
            // The row and the reservation commit together; the reserve re-checks the balance under a row lock.
            DB::transaction(function () use ($request, $wallet, $user, $micro): void {
                $withdrawal = Withdrawal::query()->create([
                    'user_id' => $user->id,
                    'amount_micro' => $micro,
                    'method' => $user->payout_method,
                    'details' => $user->payout_details,
                    'traffic_source' => $request->validated('traffic_source'),
                ]);

                $wallet->reserveForWithdrawal($user, $micro, (string) $withdrawal->id);
            });
        } catch (InsufficientBalanceException) {
            return back()->withInput()->withErrors(['amount' => 'The amount is more than your available balance.']);
        }

        return redirect()->route('member.withdraws')
            ->with('success', 'Your withdrawal request for '.Money::format($micro, 2).' has been submitted.');
    }
}
