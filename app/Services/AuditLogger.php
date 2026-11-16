<?php

namespace App\Services;

use App\Enums\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * Record an action an administrator took, with the values before and after it
     * and the IP address and browser it came from.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(User $admin, AdminAction $action, ?User $targetUser = null, ?string $reason = null, array $oldValues = [], array $newValues = []): AdminAuditLog
    {
        return AdminAuditLog::query()->create([
            'admin_id' => $admin->getKey(),
            'target_user_id' => $targetUser?->getKey(),
            'action' => $action,
            'reason' => $reason === null || $reason === '' ? null : Str::limit($reason, 250, ''),
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent() === null ? null : Str::limit($this->request->userAgent(), 1000, ''),
        ]);
    }
}
