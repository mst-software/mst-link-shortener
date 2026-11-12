# Wallet ledger pattern

A user's balance is real money. Every change must be atomic, race-safe and recorded as a `Transaction` row.

```php
DB::transaction(function () use ($userId, $amount) {
    // 1. Lock the user row: re-fetch it, never reuse a model loaded earlier
    $user = User::where('id', $userId)->lockForUpdate()->first();

    // 2. Validate against the freshly locked balance
    if ($user->balance < $amount) {
        throw ValidationException::withMessages([...]);
    }

    $balanceBefore = $user->balance;
    $user->balance -= $amount; // += when crediting
    $user->save();

    // 3. Write the ledger entry in the SAME transaction
    Transaction::create([
        'user_id' => $user->id,
        'amount' => $amount,
        'type' => 'out',          // 'in' for credits, 'out' for debits
        'action' => '...',        // must have a label in Transaction::getActionLabelAttribute()
        'balance_before' => $balanceBefore,
        'balance_after' => $user->balance,
        'description' => '...',
        'reference_id' => '...',  // the entity that caused the transaction
    ]);
});
```

## Rules

- Always wrap in `DB::transaction()`.
- Always `lockForUpdate()` the `User` row before reading or changing `balance`.
- Never change `balance` without creating a `Transaction` row in the same database transaction.
- Adding a new `action` means adding its label to `Transaction::getActionLabelAttribute()`.
- `reference_id` points at the entity that caused the transaction so the ledger entry is traceable.
- Events from outside (callbacks, webhooks, polling) must check for an existing record by a unique external reference **before** crediting, so a retry cannot credit twice.
- When reusing a shared service from a new entry point (for example a webhook), check whether the service locks the user row itself; if not, lock it yourself or tell the user.

Canonical example in the reference project: `app/Http/Controllers/AccountController.php::buy`.
