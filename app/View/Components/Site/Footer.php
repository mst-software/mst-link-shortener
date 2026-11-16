<?php

namespace App\View\Components\Site;

use App\Enums\SocialNetwork;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Footer extends Component
{
    /**
     * Filled social links, keyed by network label.
     *
     * @var array<string, string>
     */
    public array $socialLinks;

    public string $supportEmail;

    public function __construct()
    {
        $this->socialLinks = SocialNetwork::filledLinks();
        $this->supportEmail = Settings::string('support_email');
    }

    public function render(): View
    {
        return view('components.site.footer');
    }
}
