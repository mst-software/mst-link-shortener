<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeUserRoleRequest;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;

class UserRoleController extends Controller
{
    public function update(ChangeUserRoleRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->changeRole($request->user(), $user, $request->role(), $request->validated('reason'));

        return redirect()->route('admin.users.show', $user)->with('success', 'Role changed to '.$request->role()->label().'.');
    }
}
