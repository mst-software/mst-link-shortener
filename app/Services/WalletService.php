<?php

namespace App\Services;

use App\Enums\TransactionAction;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Transaction;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    /**
     * Add money to the user's available balance.
     */
    public function credit(User $user, int $micro, TransactionAction $action, ?string $referenceId = null, string $description = ''): Transaction
    {
        $this->assertPositive($micro);

        return $this->apply($user, function (User $locked) use ($micro, $action, $referenceId, $description): Transaction {
            $before = $locked->balance_micro;
            $locked->balance_micro = $before + $micro;
            $locked->save();

            return $this->record($locked, 'in', $action, $micro, $before, $locked->balance_micro, $referenceId, $description);
        });
    }

    /**
     * Remove money from the user's available balance.
     *
     * @throws InsufficientBalanceException
     */
    public function debit(User $user, int $micro, TransactionAction $action, ?string $referenceId = null, string $description = ''): Transaction
    {
        $this->assertPositive($micro);

        return $this->apply($user, function (User $locked) use ($micro, $action, $referenceId, $description): Transaction {
            $before = $locked->balance_micro;

            if ($before < $micro) {
                throw new InsufficientBalanceException;
            }

            $locked->balance_micro = $before - $micro;
            $locked->save();

            return $this->record($locked, 'out', $action, $micro, $before, $locked->balance_micro, $referenceId, $description);
        });
    }

    /**
     * Move money from the available balance to the processing balance.
     *
     * @throws InsufficientBalanceException
     */
    public function reserveForWithdrawal(User $user, int $micro, string $referenceId): Transaction
    {
        $this->assertPositive($micro);

        return $this->apply($user, function (User $locked) use ($micro, $referenceId): Transaction {
            $before = $locked->balance_micro;

            if ($before < $micro) {
                throw new InsufficientBalanceException;
            }

            $locked->balance_micro = $before - $micro;
            $locked->processing_micro += $micro;
            $locked->save();

            return $this->record($locked, 'out', TransactionAction::WithdrawRequest, $micro, $before, $locked->balance_micro, $referenceId, 'Withdrawal requested');
        });
    }

    /**
     * Mark processing money as paid out. The available balance does not change.
     *
     * @throws InsufficientBalanceException
     */
    public function settleWithdrawal(User $user, int $micro, string $referenceId): Transaction
    {
        $this->assertPositive($micro);

        return $this->apply($user, function (User $locked) use ($micro, $referenceId): Transaction {
            if ($locked->processing_micro < $micro) {
                throw new InsufficientBalanceException('Insufficient processing balance.');
            }

            $locked->processing_micro -= $micro;
            $locked->save();

            return $this->record($locked, 'out', TransactionAction::WithdrawPaid, $micro, $locked->balance_micro, $locked->balance_micro, $referenceId, 'Withdrawal paid');
        });
    }

    /**
     * Return processing money to the available balance.
     *
     * @throws InsufficientBalanceException
     */
    public function releaseWithdrawal(User $user, int $micro, string $referenceId): Transaction
    {
        $this->assertPositive($micro);

        return $this->apply($user, function (User $locked) use ($micro, $referenceId): Transaction {
            if ($locked->processing_micro < $micro) {
                throw new InsufficientBalanceException('Insufficient processing balance.');
            }

            $before = $locked->balance_micro;
            $locked->processing_micro -= $micro;
            $locked->balance_micro = $before + $micro;
            $locked->save();

            return $this->record($locked, 'in', TransactionAction::WithdrawRejected, $micro, $before, $locked->balance_micro, $referenceId, 'Withdrawal rejected');
        });
    }

    /**
     * Run the callback in a transaction against a freshly locked copy of the user.
     * The caller's model is never trusted; it is refreshed afterwards.
     *
     * @param  Closure(User): Transaction  $callback
     */
    private function apply(User $user, Closure $callback): Transaction
    {
        $transaction = DB::transaction(function () use ($user, $callback): Transaction {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            return $callback($locked);
        });

        $user->refresh();

        return $transaction;
    }

    private function record(User $user, string $type, TransactionAction $action, int $micro, int $before, int $after, ?string $referenceId, string $description): Transaction
    {
        return Transaction::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'action' => $action,
            'amount_micro' => $micro,
            'balance_before_micro' => $before,
            'balance_after_micro' => $after,
            'reference_id' => $referenceId,
            'description' => $description,
        ]);
    }

    private function assertPositive(int $micro): void
    {
        if ($micro <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }
    }
}
