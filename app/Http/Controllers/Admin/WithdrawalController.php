<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawalStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectWithdrawalRequest;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    public function index(Request $request): View
    {
        $status = WithdrawalStatus::tryFrom((string) $request->query('status'));

        return view('admin.withdrawals.index', [
            'withdrawals' => Withdrawal::query()
                ->with('user:id,username')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'statuses' => WithdrawalStatus::cases(),
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal, WalletService $wallet): RedirectResponse
    {
        return $this->process($request, $withdrawal, WithdrawalStatus::Paid, null, $wallet);
    }

    public function reject(RejectWithdrawalRequest $request, Withdrawal $withdrawal, WalletService $wallet): RedirectResponse
    {
        return $this->process($request, $withdrawal, WithdrawalStatus::Rejected, $request->validated('admin_note'), $wallet);
    }

    /**
     * Settle or release a pending withdrawal. The row is locked and its status re-checked,
     * so a repeated or concurrent click cannot move the money twice.
     */
    private function process(Request $request, Withdrawal $withdrawal, WithdrawalStatus $target, ?string $note, WalletService $wallet): RedirectResponse
    {
        try {
            $processed = DB::transaction(function () use ($request, $withdrawal, $target, $note, $wallet): ?Withdrawal {
                $locked = Withdrawal::query()->whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();

                if (! $locked->isPending()) {
                    return null;
                }

                $user = User::query()->findOrFail($locked->user_id);

                if ($target === WithdrawalStatus::Paid) {
                    $wallet->settleWithdrawal($user, $locked->amount_micro, (string) $locked->id);
                } else {
                    $wallet->releaseWithdrawal($user, $locked->amount_micro, (string) $locked->id);
                }

                $locked->update([
                    'status' => $target,
                    'admin_note' => $note,
                    'processed_at' => now(),
                    'processed_by' => $request->user()->id,
                ]);

                return $locked;
            });
        } catch (InsufficientBalanceException) {
            return redirect()->route('admin.withdrawals.index')
                ->with('error', 'The member has no matching processing balance, so nothing was changed.');
        }

        if ($processed === null) {
            return redirect()->route('admin.withdrawals.index')
                ->with('error', 'This withdrawal has already been processed.');
        }

        $outcome = $target === WithdrawalStatus::Paid ? 'marked as paid' : 'rejected and the money returned';

        return redirect()->route('admin.withdrawals.index')
            ->with('success', 'Withdrawal of '.Money::format($processed->amount_micro, 2).' '.$outcome.'.');
    }
}
