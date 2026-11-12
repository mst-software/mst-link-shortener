<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreSupportMessageRequest;
use App\Models\SupportMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SupportController extends Controller
{
    public function show(): View
    {
        return view('member.support');
    }

    public function store(StoreSupportMessageRequest $request): RedirectResponse
    {
        SupportMessage::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('member.form.support')
            ->with('success', 'Your message has been sent. We will get back to you by email.');
    }
}
