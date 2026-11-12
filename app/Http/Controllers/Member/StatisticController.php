<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class StatisticController extends Controller
{
    public function index(): View
    {
        return view('member.statistic');
    }
}
