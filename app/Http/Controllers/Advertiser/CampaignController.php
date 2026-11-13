<?php

namespace App\Http\Controllers\Advertiser;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Concerns\HandlesCampaignImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\StoreCampaignRequest;
use App\Models\Campaign;
use App\Services\Advertiser\AdvertiserBilling;
use App\Services\Advertiser\CampaignReport;
use App\Support\DomainMasker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    use HandlesCampaignImages;

    public function index(Request $request): View
    {
        $campaigns = Campaign::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        return view('advertiser.campaigns.index', ['campaigns' => $campaigns]);
    }

    public function show(Request $request, Campaign $campaign, AdvertiserBilling $billing): View
    {
        $this->authorizeOwner($request, $campaign);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['counted', 'not_counted'])],
        ]);

        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $report = new CampaignReport($campaign);

        return view('advertiser.campaigns.show', [
            'campaign' => $campaign,
            'from' => $from,
            'to' => $to,
            'status' => $filters['status'] ?? null,
            'funnel' => $report->funnel($from, $to),
            'daily' => $report->daily($from, $to),
            'visits' => $report->visits($filters['status'] ?? null)->whereBetween('created_at', [$from, $to])->paginate(25)->withQueryString(),
            'issuedToday' => $campaign->issuedToday(),
            'balanceMicro' => $request->user()->balance_micro,
            'isUnderfunded' => $campaign->is_active
                && $campaign->review_status === ReviewStatus::Approved
                && ! $billing->canAfford($campaign),
        ]);
    }

    public function create(): View
    {
        return view('advertiser.campaigns.form', ['campaign' => new Campaign]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = new Campaign($request->safe()->only(StoreCampaignRequest::FIELDS));
        $campaign->user_id = $request->user()->id;
        $campaign->review_status = ReviewStatus::Pending;
        $campaign->display_domain = DomainMasker::mask($campaign->domain);
        $campaign->widget_key = Campaign::generateWidgetKey();

        $this->applyCampaignImages($request, $campaign);

        $campaign->save();

        return redirect()->route('advertiser.campaigns.edit', $campaign)
            ->with('success', 'Campaign submitted. It will start running once it has been reviewed.');
    }

    public function edit(Request $request, Campaign $campaign): View
    {
        $this->authorizeOwner($request, $campaign);

        return view('advertiser.campaigns.form', ['campaign' => $campaign]);
    }

    public function update(StoreCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeOwner($request, $campaign);

        $campaign->fill($request->safe()->only(StoreCampaignRequest::FIELDS));

        $needsReview = $campaign->isDirty(['keyword', 'domain'])
            || $campaign->review_status === ReviewStatus::Rejected;

        if ($campaign->isDirty('domain')) {
            $campaign->display_domain = DomainMasker::mask($campaign->domain);
        }

        if ($needsReview) {
            $campaign->review_status = ReviewStatus::Pending;
            $campaign->review_note = null;
        }

        $this->applyCampaignImages($request, $campaign);

        $campaign->save();

        return redirect()->route('advertiser.campaigns.edit', $campaign)->with(
            'success',
            $needsReview ? 'Campaign updated and sent back for review.' : 'Campaign updated.',
        );
    }

    public function toggle(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeOwner($request, $campaign);

        if ($campaign->review_status !== ReviewStatus::Approved) {
            return redirect()->route('advertiser.campaigns.index')
                ->with('error', 'Only approved campaigns can be paused or resumed.');
        }

        $campaign->update(['is_active' => ! $campaign->is_active]);

        return redirect()->route('advertiser.campaigns.index')
            ->with('success', $campaign->is_active ? 'Campaign resumed.' : 'Campaign paused.');
    }

    private function authorizeOwner(Request $request, Campaign $campaign): void
    {
        abort_unless($campaign->user_id === $request->user()->id, 404);
    }
}
