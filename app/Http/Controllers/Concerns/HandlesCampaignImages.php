<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Stores, replaces and removes the three instruction images of a campaign.
 */
trait HandlesCampaignImages
{
    /**
     * Apply the uploaded files and "remove_*" flags of the request to the campaign (not saved).
     */
    protected function applyCampaignImages(Request $request, Campaign $campaign): void
    {
        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                $this->deleteCampaignImage($campaign->{$field});
                $campaign->{$field} = $request->file($field)->store('campaigns', 'public');
            } elseif ($request->boolean('remove_'.$field)) {
                $this->deleteCampaignImage($campaign->{$field});
                $campaign->{$field} = null;
            }
        }
    }

    protected function deleteCampaignImages(Campaign $campaign): void
    {
        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            $this->deleteCampaignImage($campaign->{$field});
        }
    }

    private function deleteCampaignImage(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
