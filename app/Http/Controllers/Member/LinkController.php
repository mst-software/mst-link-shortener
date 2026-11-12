<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class LinkController extends Controller
{
    public function index(): View
    {
        return view('member.links.index');
    }

    public function hidden(): View
    {
        return view('member.links.hidden');
    }
}
