<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ViewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Models\Campaign;
use App\Models\FraudFlag;
use App\Models\View as PageView;
use App\Models\VisitSession;
use App\Support\DomainMasker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = Campaign::query()->latest('id')->paginate(20);

        $stats = [];

        foreach ($campaigns as $campaign) {
            $stats[$campaign->getKey()] = [
                'issued_today' => $campaign->issuedToday(),
                'valid_views' => PageView::query()
                    ->whereIn('status', [ViewStatus::Pending, ViewStatus::Released])
                    ->whereIn('visit_session_id', VisitSession::query()->where('campaign_id', $campaign->getKey())->select('id'))
                    ->count(),
                'reports' => FraudFlag::query()
                    ->where('reason', 'visitor_report')
                    ->where('details->campaign_id', $campaign->getKey())
                    ->count(),
            ];
        }

        return view('admin.campaigns.index', ['campaigns' => $campaigns, 'stats' => $stats]);
    }

    public function create(): View
    {
        return view('admin.campaigns.form', ['campaign' => new Campaign]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = new Campaign($this->attributes($request));
        $campaign->widget_key = $this->generateWidgetKey();

        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                $campaign->{$field} = $request->file($field)->store('campaigns', 'public');
            }
        }

        $campaign->save();

        return redirect()->route('admin.campaigns.edit', $campaign)->with('success', 'Campaign created.');
    }

    public function edit(Campaign $campaign): View
    {
        return view('admin.campaigns.form', ['campaign' => $campaign]);
    }

    public function update(StoreCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->fill($this->attributes($request));

        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                $this->deleteImage($campaign->{$field});
                $campaign->{$field} = $request->file($field)->store('campaigns', 'public');
            } elseif ($request->boolean('remove_'.$field)) {
                $this->deleteImage($campaign->{$field});
                $campaign->{$field} = null;
            }
        }

        $campaign->save();

        return redirect()->route('admin.campaigns.edit', $campaign)->with('success', 'Campaign updated.');
    }

    public function toggle(Campaign $campaign): RedirectResponse
    {
        $campaign->update(['is_active' => ! $campaign->is_active]);

        return redirect()->route('admin.campaigns.index')
            ->with('success', $campaign->is_active ? 'Campaign switched on.' : 'Campaign switched off.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            $this->deleteImage($campaign->{$field});
        }

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

        $data['is_active'] = $request->boolean('is_active');
        $data['display_domain'] = $request->filled('display_domain')
            ? $request->string('display_domain')->toString()
            : DomainMasker::mask($request->string('domain')->toString());

        return $data;
    }

    private function generateWidgetKey(): string
    {
        do {
            $key = Str::lower(Str::random(24));
        } while (Campaign::query()->where('widget_key', $key)->exists());

        return $key;
    }

    private function deleteImage(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
