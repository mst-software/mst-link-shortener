<?php

namespace App\Http\Controllers;

use App\Services\Settings;
use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('privacy', ['supportEmail' => Settings::string('support_email')]);
    }

    public function terms(): View
    {
        return view('terms', ['supportEmail' => Settings::string('support_email')]);
    }
}
