<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }
}
