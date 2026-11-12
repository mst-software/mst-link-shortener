<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SupportMessageController extends Controller
{
    public function index(): View
    {
        return view('admin.support-messages.index', [
            'messages' => SupportMessage::query()
                ->with('user:id,username')
                ->orderByRaw('resolved_at is not null')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function resolve(SupportMessage $supportMessage): RedirectResponse
    {
        $supportMessage->update(['resolved_at' => $supportMessage->resolved_at ?? now()]);

        return redirect()->route('admin.support-messages.index')->with('success', 'Message marked as resolved.');
    }
}
