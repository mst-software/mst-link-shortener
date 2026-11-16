<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BanUserRequest;
use App\Http\Requests\Admin\UserAccountActionRequest;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;

class UserStatusController extends Controller
{
    public function ban(BanUserRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->ban($request->user(), $user, $request->validated('reason'), $request->boolean('ban_links'));

        return redirect()->route('admin.users.show', $user)->with('success', 'User banned.');
    }

    public function unban(UserAccountActionRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->unban($request->user(), $user, $request->validated('reason'));

        return redirect()->route('admin.users.show', $user)->with('success', 'User unbanned.');
    }
}
