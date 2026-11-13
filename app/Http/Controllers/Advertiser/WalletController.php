<?php

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class WalletController extends Controller
{
    public function index(): View
    {
        return view('advertiser.wallet');
    }
}
