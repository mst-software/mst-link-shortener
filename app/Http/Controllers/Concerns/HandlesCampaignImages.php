<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\MediaPreset;
use App\Exceptions\MediaUploadException;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Models\Campaign;
use App\Services\MediaUploader;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Stores, replaces and removes the three instruction images of a campaign through the MediaUploader,
 * on the disk chosen on the admin Storage settings page.
 */
trait HandlesCampaignImages
{
    /**
     * Apply the uploaded files and "remove_*" flags of the request to the campaign (not saved).
     *
     * @throws ValidationException When an image is rejected or cannot be stored.
     */
    protected function applyCampaignImages(Request $request, Campaign $campaign): void
    {
        $uploader = app(MediaUploader::class);

        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                try {
                    $campaign->{$field} = $uploader->replace($request->file($field), MediaPreset::CampaignImage, $campaign->{$field});
                } catch (MediaUploadException $exception) {
                    throw ValidationException::withMessages([$field => $exception->getMessage()]);
                }
            } elseif ($request->boolean('remove_'.$field)) {
                MediaStorage::delete($campaign->{$field});
                $campaign->{$field} = null;
            }
        }
    }

    protected function deleteCampaignImages(Campaign $campaign): void
    {
        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            MediaStorage::delete($campaign->{$field});
        }
    }
}
