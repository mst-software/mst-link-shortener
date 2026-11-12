<?php

namespace App\Http\Controllers\Member;

use App\Enums\TransactionAction;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    public function index(Request $request): View
    {
        $member = $request->user();

        $referrals = $member->referrals()->orderBy('created_at')->get(['id', 'username', 'created_at']);

        // Commission transactions use the referred user's id as reference_id.
        $commissions = Transaction::query()
            ->where('user_id', $member->id)
            ->where('action', TransactionAction::ReferralCommission)
            ->whereIn('reference_id', $referrals->pluck('id')->map(fn (int $id): string => (string) $id))
            ->selectRaw('reference_id, SUM(amount_micro) as total_micro')
            ->groupBy('reference_id')
            ->pluck('total_micro', 'reference_id');

        $rows = $referrals->map(fn ($referral): array => [
            'username' => Str::substr($referral->username, 0, 3).'***',
            'joined_at' => $referral->created_at,
            'commission_micro' => (int) ($commissions[(string) $referral->id] ?? 0),
        ]);

        return view('member.referrals', [
            'referralLink' => url('/ref/'.$member->referral_code),
            'commissionPercent' => Settings::get('referral_percent'),
            'rows' => $rows,
        ]);
    }
}
