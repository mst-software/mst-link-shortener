<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingsPage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class SettingController extends Controller
{
    /**
     * The old single settings page now opens the first settings page.
     */
    public function edit(): RedirectResponse
    {
        $firstPage = collect(SettingsPage::cases())->first(fn (SettingsPage $page): bool => Route::has($page->routeName()));

        return redirect()->route($firstPage->routeName());
    }
}
