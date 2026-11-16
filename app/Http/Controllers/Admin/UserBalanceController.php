<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustUserBalanceRequest;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;

class UserBalanceController extends Controller
{
    public function store(AdjustUserBalanceRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $transaction = $accounts->adjustBalance($request->user(), $user, $request->isCredit(), $request->amountMicro(), $request->validated('reason'));

        return redirect()->route('admin.users.show', $user)->with('success', sprintf(
            'Balance %s by %s. New balance: %s.',
            $request->isCredit() ? 'credited' : 'debited',
            Money::format($transaction->amount_micro, 6),
            Money::format($transaction->balance_after_micro, 6),
        ));
    }
}
