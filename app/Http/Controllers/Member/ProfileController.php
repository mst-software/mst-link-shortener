<?php

namespace App\Http\Controllers\Member;

use App\Enums\PayoutMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\UpdateProfileRequest;
use App\Models\User;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('member.user.profile', [
            'countries' => config('countries'),
            'payoutMethods' => PayoutMethod::selectableFor($user->payout_method),
            'minimumWithdrawal' => Money::format(Settings::int('min_withdraw_micro'), 0),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            ...$request->safe()->only([
                'fullname',
                'phone_number',
                'address',
                'district',
                'province',
                'country',
                'traffic_source',
                'default_level',
                'payout_method',
            ]),
            'payout_details' => $request->payoutDetails(),
        ]);

        return redirect()->route('member.user.profile')->with('success', 'Saved.');
    }
}
