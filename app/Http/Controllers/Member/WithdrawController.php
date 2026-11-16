<?php

namespace App\Http\Controllers\Member;

use App\Enums\TransactionAction;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreWithdrawalRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalRequested;
use App\Services\Settings;
use App\Services\TwoFactorAuthentication;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'needsTwoFactorCode' => $user->hasTwoFactorEnabled(),
        ]);
    }

    public function store(StoreWithdrawalRequest $request, WalletService $wallet, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $micro = $request->amountMicro();

        try {
            // The row and the reservation commit together; the reserve re-checks the balance under a row lock.
            // The two-factor code is checked last, so it is only used up when the withdrawal is created.
            $withdrawal = DB::transaction(function () use ($request, $wallet, $twoFactor, $user, $micro): Withdrawal {
                $withdrawal = Withdrawal::query()->create([
                    'user_id' => $user->id,
                    'amount_micro' => $micro,
                    'method' => $user->payout_method,
                    'details' => $user->payout_details,
                    'traffic_source' => $request->validated('traffic_source'),
                ]);

                $wallet->reserveForWithdrawal($user, $micro, (string) $withdrawal->id);

                if ($user->hasTwoFactorEnabled() && ! $twoFactor->verify($user, (string) $request->validated('code'))) {
                    throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
                }

                return $withdrawal;
            });
        } catch (InsufficientBalanceException) {
            return back()->withInput()->withErrors(['amount' => 'The amount is more than your available balance.']);
        }

        // Sent after the commit; a mail failure is reported but must not hide a saved request.
        rescue(fn () => $user->notify(new WithdrawalRequested($withdrawal)));

        return redirect()->route('member.withdraws')
            ->with('success', 'Your withdrawal request for '.Money::format($micro, 2).' has been submitted.');
    }
}
