<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    /**
     * Show the blog listing with the post preview popups.
     */
    public function index(): View
    {
        return view('blog', [
            'categories' => [
                'tips' => 'Tips',
                'guide' => 'Guides',
                'news' => 'Announcements',
            ],
            'posts' => $this->posts(),
        ]);
    }

    /**
     * @return array<int, array{category: string, label: string, date: string, icon: string, title: string, excerpt: string, body: string}>
     */
    private function posts(): array
    {
        return [
            [
                'category' => 'tips',
                'label' => 'Tips',
                'date' => 'November 10, 2026',
                'icon' => 'f5',
                'title' => '5 ways to get more views on your short links',
                'excerpt' => 'Small changes in how you share links can noticeably increase your valid views.',
                'body' => '<p>Share your links where your audience is genuinely paying attention: a group, a channel or a post people read to the end. Traffic from people who really want to see the content is far more likely to pass every step and count as a valid view.</p><p>Place the short link next to a clear call to action, tell visitors what they will get and avoid misleading buttons. Visitors who know what to expect are less likely to leave halfway, which improves your completion rate.</p><p>Check your statistics regularly. See which countries, devices and sources earn the most and focus on those. Never use bots or auto-refresh tools: they are filtered out and can get your account suspended.</p>',
            ],
            [
                'category' => 'news',
                'label' => 'Announcements',
                'date' => 'November 5, 2026',
                'icon' => 'f7',
                'title' => 'Rate review: new tiers for Southeast Asia',
                'excerpt' => 'We reviewed the rates and added more earning options for popular regions.',
                'body' => '<p>Our team reviews the rates regularly and raises them whenever advertisers pay more. This month we updated the rates for several Southeast Asian countries and gave the tiers clearer names in the rate table.</p><p>You can always see the latest rates on the <a href="'.route('payout-rates').'">Publisher Rates</a> page and use the earnings calculator there to estimate before you share your links.</p>',
            ],
            [
                'category' => 'guide',
                'label' => 'Guides',
                'date' => 'October 28, 2026',
                'icon' => 'f3',
                'title' => 'How the 12% referral program works',
                'excerpt' => 'Invite friends and earn a share of their income for life, without reducing theirs.',
                'body' => '<p>When someone signs up with your referral link, you receive 12% of what they earn for as long as their account stays active. The commission is paid on top of their earnings, so they lose nothing.</p><p>Find your referral link in the dashboard and share it with friends. Self-referrals and fake accounts are not allowed; commissions earned that way can be revoked, see the <a href="'.route('terms').'">Terms of Use</a>.</p>',
            ],
            [
                'category' => 'guide',
                'label' => 'Guides',
                'date' => 'October 20, 2026',
                'icon' => 'f9',
                'title' => 'Why some views are not counted',
                'excerpt' => 'A quick explanation of the checks that decide whether a view is valid.',
                'body' => '<p>A view is paid only when the visitor is unique within 24 hours, stays on the page long enough, has JavaScript and cookies enabled, completes every step and reaches the destination page. Visits from bots, VPNs, proxies and datacenter networks are rejected.</p><p>Read the full rules in the <a href="'.route('payout-rates').'#view-policy">View Counting Policy</a>. If you believe a view was rejected by mistake, contact support with the short link and the date it happened.</p>',
            ],
            [
                'category' => 'tips',
                'label' => 'Tips',
                'date' => 'October 12, 2026',
                'icon' => 'f1',
                'title' => 'Where to share your links safely',
                'excerpt' => 'Choose places that keep your account safe and your traffic clean.',
                'body' => '<p>Good places include your own website, communities you belong to, email newsletters and social media posts. Always follow the rules of each platform.</p><p>Do not share links to adult content, gambling or scams, and do not promote links with misleading titles. Every link is scanned and violations can lead to your earnings being cancelled.</p>',
            ],
            [
                'category' => 'news',
                'label' => 'Announcements',
                'date' => 'October 3, 2026',
                'icon' => 'f6',
                'title' => 'Withdrawals: methods and processing time',
                'excerpt' => 'See how and when you get paid after reaching the minimum threshold.',
                'body' => '<p>You can request a withdrawal as soon as your available balance reaches the minimum threshold. We support bank transfer, PayPal and USDT (BEP20).</p><p>Requests are reviewed and processed within 1&ndash;7 days. New earnings stay pending for a short review period, which helps us filter invalid traffic and keeps payouts reliable for everyone.</p>',
            ],
        ];
    }
}
