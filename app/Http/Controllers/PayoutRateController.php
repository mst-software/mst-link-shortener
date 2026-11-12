<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PayoutRateController extends Controller
{
    public function index(): View
    {
        return view('payout-rates');
    }
}
