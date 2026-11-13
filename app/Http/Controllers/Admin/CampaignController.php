<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Enums\ViewStatus;
use App\Http\Controllers\Concerns\HandlesCampaignImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewCampaignRequest;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\FraudFlag;
use App\Models\View as PageView;
use App\Services\Advertiser\PriceGuard;
use App\Support\DomainMasker;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    use HandlesCampaignImages;

    public function index(Request $request): View
    {
        $pendingOnly = $request->query('review') === 'pending';

        $campaigns = Campaign::query()
            ->with('owner:id,username')
            ->when($pendingOnly, fn ($query) => $query->where('review_status', ReviewStatus::Pending))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $ids = $campaigns->getCollection()->modelKeys();

        // Three grouped queries for the whole page instead of three queries per campaign.
        $issued = CodeAttempt::query()
            ->whereIn('campaign_id', $ids)
            ->whereBetween('issued_at', [now('UTC')->startOfDay(), now('UTC')->endOfDay()])
            ->selectRaw('campaign_id, count(*) as total')
            ->groupBy('campaign_id')
            ->pluck('total', 'campaign_id');

        $validViews = PageView::query()
            ->join('visit_sessions', 'visit_sessions.id', '=', 'views.visit_session_id')
            ->whereIn('views.status', [ViewStatus::Pending, ViewStatus::Released])
            ->whereIn('visit_sessions.campaign_id', $ids)
            ->selectRaw('visit_sessions.campaign_id as campaign_id, count(*) as total')
            ->groupBy('visit_sessions.campaign_id')
            ->pluck('total', 'campaign_id');

        $reports = FraudFlag::query()
            ->where('reason', 'visitor_report')
            ->whereIn('details->campaign_id', $ids)
            ->get(['id', 'details'])
            ->countBy(fn (FraudFlag $flag): int => (int) ($flag->details['campaign_id'] ?? 0));

        $stats = [];

        foreach ($ids as $id) {
            $stats[$id] = [
                'issued_today' => (int) ($issued[$id] ?? 0),
                'valid_views' => (int) ($validViews[$id] ?? 0),
                'reports' => (int) ($reports[$id] ?? 0),
            ];
        }

        return view('admin.campaigns.index', ['campaigns' => $campaigns, 'stats' => $stats, 'pendingOnly' => $pendingOnly]);
    }

    public function create(): View
    {
        return view('admin.campaigns.form', ['campaign' => new Campaign]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = new Campaign($this->attributes($request));
        $campaign->widget_key = Campaign::generateWidgetKey();

        $this->applyCampaignImages($request, $campaign);

        $campaign->save();

        return redirect()->route('admin.campaigns.edit', $campaign)->with('success', 'Campaign created.');
    }

    public function edit(Campaign $campaign): View
    {
        return view('admin.campaigns.form', ['campaign' => $campaign]);
    }

    public function update(StoreCampaignRequest $request, Campaign $campaign, PriceGuard $priceGuard): RedirectResponse
    {
        $campaign->fill($this->attributes($request));

        $this->applyCampaignImages($request, $campaign);

        $campaign->save();

        return redirect()->route('admin.campaigns.edit', $campaign)
            ->with('success', 'Campaign updated.')
            ->with('warning', $campaign->price_micro === null ? null : $priceGuard->warningFor($campaign->price_micro));
    }

    public function toggle(Campaign $campaign): RedirectResponse
    {
        $campaign->update(['is_active' => ! $campaign->is_active]);

        return redirect()->route('admin.campaigns.index')
            ->with('success', $campaign->is_active ? 'Campaign switched on.' : 'Campaign switched off.');
    }

    public function review(ReviewCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $status = ReviewStatus::from($request->string('review_status')->toString());

        $campaign->update([
            'review_status' => $status,
            'review_note' => $status === ReviewStatus::Rejected ? $request->string('review_note')->toString() : null,
        ]);

        return redirect()->route('admin.campaigns.index', ['review' => 'pending'])
            ->with('success', $status === ReviewStatus::Approved ? 'Campaign approved.' : 'Campaign rejected.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->deleteCampaignImages($campaign);

        $campaign->delete();

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign deleted.');
    }

    /**
     * Form values without the uploaded images, with a suggested display domain when none was given.
     *
     * @return array<string, mixed>
     */
    private function attributes(StoreCampaignRequest $request): array
    {
        $data = $request->safe()->only([
            'name', 'keyword', 'domain', 'weight', 'daily_limit', 'starts_at', 'ends_at',
            'stage1_seconds', 'stage2_seconds', 'notes',
        ]);

        if ($request->has('price_usd')) {
            $data['price_micro'] = $request->filled('price_usd') ? Money::toMicro($request->string('price_usd')->toString()) : null;
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['display_domain'] = $request->filled('display_domain')
            ? $request->string('display_domain')->toString()
            : DomainMasker::mask($request->string('domain')->toString());

        return $data;
    }
}
