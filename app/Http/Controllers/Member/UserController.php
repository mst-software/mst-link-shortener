<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class UserController extends Controller
{
    public function profile(): View
    {
        return view('member.user.profile');
    }

    public function changePassword(): View
    {
        return view('member.user.change-password');
    }

    public function changeEmail(): View
    {
        return view('member.user.change-email');
    }
}
