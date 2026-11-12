<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SupportController extends Controller
{
    public function show(): View
    {
        return view('member.support');
    }
}
