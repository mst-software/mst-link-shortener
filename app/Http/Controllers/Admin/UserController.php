<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LinkStatus;
use App\Enums\TimeRange;
use App\Enums\TransactionAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterUsersRequest;
use App\Models\AdminAuditLog;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class UserController extends Controller
{
    public function index(FilterUsersRequest $request): View
    {
        $search = trim((string) $request->validated('q', ''));
        $role = UserRole::tryFrom((string) $request->validated('role', ''));
        $status = UserStatus::tryFrom((string) $request->validated('status', ''));
        $verified = $request->validated('verified');
        $joined = TimeRange::tryFrom((string) $request->validated('joined', ''));

        return view('admin.users.index', [
            'users' => User::query()
                ->withCount('links')
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('username', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                }))
                ->when($role !== null, fn (Builder $query) => $query->where('role', $role))
                ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
                ->when($verified === 'yes', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
                ->when($verified === 'no', fn (Builder $query) => $query->whereNull('email_verified_at'))
                ->when($joined !== null, fn (Builder $query) => $joined->apply($query))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'verified' => $verified,
            'joined' => $joined,
        ]);
    }

    public function show(User $user): View
    {
        $user->load('referrer:id,username');

        $links = Link::query()
            ->where('user_id', $user->id)
            ->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(views_count), 0) as views, coalesce(sum(case when status = ? then 1 else 0 end), 0) as banned', [LinkStatus::Banned->value])
            ->first();

        return view('admin.users.show', [
            'user' => $user,
            'linkSummary' => [
                'total' => (int) $links->total,
                'views' => (int) $links->views,
                'banned' => (int) $links->banned,
            ],
            'transactions' => Transaction::query()->where('user_id', $user->id)->latest('id')->limit(20)->get(),
            'withdrawals' => Withdrawal::query()->where('user_id', $user->id)->latest('id')->limit(10)->get(),
            'referralsCount' => $user->referrals()->count(),
            'referralEarningsMicro' => (int) Transaction::query()
                ->where('user_id', $user->id)
                ->where('action', TransactionAction::ReferralCommission)
                ->sum('amount_micro'),
            'recentReferrals' => $user->referrals()->latest('id')->limit(10)->get(['id', 'username', 'created_at']),
            'fraudFlags' => FraudFlag::query()->with('link:id,alias')->where('user_id', $user->id)->latest('id')->limit(10)->get(),
            'auditLogs' => AdminAuditLog::query()->with('admin:id,username')->where('target_user_id', $user->id)->latest('id')->limit(30)->get(),
        ]);
    }
}
