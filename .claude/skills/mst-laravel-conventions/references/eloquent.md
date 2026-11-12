# Eloquent: newer and older style

Reference files: `app/Models/BankDeposit.php`, `app/Models/AdminTrustedDevice.php` (newer style); `app/Models/Account.php`, `app/Models/Transaction.php` (older style).

## Newer style (use for new models and methods)

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

protected function casts(): array
{
    return [
        'amount' => 'integer',
        'response' => 'array',
    ];
}

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function getDeclaredValueAttribute(): int
{
    return (int) $this->amount;
}
```

- Use the `casts()` method, not the `$casts` property.
- Every relationship has a return type imported from `Illuminate\Database\Eloquent\Relations\*`.
- Accessors are `get{Name}Attribute(): type` with a return type.
- List each column explicitly in `$fillable`.
- Add a short English PHPDoc to relationships whose meaning is not obvious.

## Older style

Older files use `protected $casts = [...]` and untyped relationships. When adding a single relationship to such a file, match that file, and tell the user the newer style exists in case they want the file modernized separately. Never rewrite unrelated methods as a drive-by change.
