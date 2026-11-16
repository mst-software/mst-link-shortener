<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminAction;
use App\Enums\TimeRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAuditLogsRequest;
use App\Models\AdminAuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class AuditLogController extends Controller
{
    public function index(FilterAuditLogsRequest $request): View
    {
        $filters = [
            'action' => AdminAction::tryFrom((string) $request->validated('action', '')),
            'admin' => trim((string) $request->validated('admin', '')),
            'q' => trim((string) $request->validated('q', '')),
            'time_range' => TimeRange::tryFrom((string) $request->validated('time_range', '')),
            'from' => $request->validated('from'),
            'to' => $request->validated('to'),
        ];

        $query = AdminAuditLog::query()
            ->when($filters['action'] !== null, fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['admin'] !== '', fn (Builder $query) => $query->whereHas('admin', fn (Builder $admin) => $admin->where('username', 'like', '%'.$filters['admin'].'%')))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($filters): void {
                $inner->where('reason', 'like', '%'.$filters['q'].'%')
                    ->orWhere('ip_address', 'like', '%'.$filters['q'].'%')
                    ->orWhereHas('targetUser', fn (Builder $user) => $user
                        ->where('username', 'like', '%'.$filters['q'].'%')
                        ->orWhere('email', 'like', '%'.$filters['q'].'%'));
            }))
            ->when($filters['time_range'] !== null, fn (Builder $query) => $filters['time_range']->apply($query))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']));

        return view('admin.audit-logs.index', [
            'logs' => (clone $query)
                ->with(['admin:id,username', 'targetUser:id,username'])
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'stats' => [
                'total' => (clone $query)->count(),
                'today' => (clone $query)->where('created_at', '>=', today())->count(),
            ],
            'filters' => $filters,
        ]);
    }
}
