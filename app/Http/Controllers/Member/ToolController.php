<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ToolController extends Controller
{
    public function quick(): View
    {
        return view('member.tools.quick');
    }

    public function massShrinker(): View
    {
        return view('member.tools.mass-shrinker');
    }

    public function fullPageScript(): View
    {
        return view('member.tools.full');
    }

    public function api(): View
    {
        return view('member.tools.api');
    }
}
