<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Contracts\View\View;

class NoticeController extends Controller
{
    public function index(): View
    {
        return view('member.notify', [
            'notices' => Notice::query()->published()->paginate(10),
        ]);
    }
}
