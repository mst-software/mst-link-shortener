<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NoticeRequest;
use App\Models\Notice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NoticeController extends Controller
{
    public function index(): View
    {
        return view('admin.notices.index', [
            'notices' => Notice::query()->latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.notices.form', ['notice' => new Notice]);
    }

    public function store(NoticeRequest $request): RedirectResponse
    {
        Notice::query()->create($this->attributes($request, new Notice));

        return redirect()->route('admin.notices.index')->with('success', 'Notice created.');
    }

    public function edit(Notice $notice): View
    {
        return view('admin.notices.form', ['notice' => $notice]);
    }

    public function update(NoticeRequest $request, Notice $notice): RedirectResponse
    {
        $notice->update($this->attributes($request, $notice));

        return redirect()->route('admin.notices.index')->with('success', 'Notice updated.');
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $notice->delete();

        return redirect()->route('admin.notices.index')->with('success', 'Notice deleted.');
    }

    /**
     * Build the stored attributes, stamping published_at the first time a notice goes live.
     *
     * @return array<string, mixed>
     */
    private function attributes(NoticeRequest $request, Notice $notice): array
    {
        $isPublished = $request->boolean('is_published');

        return [
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($notice->published_at ?? now()) : $notice->published_at,
        ];
    }
}
