<?php

namespace App\View\Components\Site;

use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The optional notice an admin writes on the General settings page, shown above public pages.
 */
class NoticeBanner extends Component
{
    public string $message;

    public function __construct()
    {
        $this->message = Settings::string('maintenance_message');
    }

    public function shouldRender(): bool
    {
        return $this->message !== '';
    }

    public function render(): View
    {
        return view('components.site.notice-banner');
    }
}
