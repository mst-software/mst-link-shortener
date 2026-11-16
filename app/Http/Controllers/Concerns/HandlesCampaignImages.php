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
use Throwable;

/**
 * Stores, replaces and removes the three instruction images of a campaign through the MediaUploader,
 * on the disk chosen on the admin Storage settings page.
 */
trait HandlesCampaignImages
{
    /**
     * Apply the uploaded files and "remove_*" flags of the request and save the campaign.
     *
     * All new images are stored first; when one fails, the ones already stored are deleted and the
     * campaign and its old images stay as they were. Replaced and removed images are only deleted after
     * the campaign is saved, so the database never points at a deleted file.
     *
     * @throws ValidationException When an image is rejected or cannot be stored.
     */
    protected function saveCampaignWithImages(Request $request, Campaign $campaign): void
    {
        $uploader = app(MediaUploader::class);
        $newReferences = [];
        $oldReferences = [];

        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                try {
                    $newReferences[$field] = $uploader->store($request->file($field), MediaPreset::CampaignImage);
                } catch (MediaUploadException $exception) {
                    $this->deleteStoredImages($newReferences);

                    throw ValidationException::withMessages([$field => $exception->getMessage()]);
                }
            }
        }

        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            if (array_key_exists($field, $newReferences)) {
                $oldReferences[] = $campaign->{$field};
                $campaign->{$field} = $newReferences[$field];
            } elseif ($request->boolean('remove_'.$field)) {
                $oldReferences[] = $campaign->{$field};
                $campaign->{$field} = null;
            }
        }

        try {
            $campaign->save();
        } catch (Throwable $exception) {
            $this->deleteStoredImages($newReferences);

            throw $exception;
        }

        $this->deleteStoredImages($oldReferences);
    }

    protected function deleteCampaignImages(Campaign $campaign): void
    {
        foreach (StoreCampaignRequest::IMAGE_FIELDS as $field) {
            MediaStorage::delete($campaign->{$field});
        }
    }

    /**
     * @param  array<array-key, string|null>  $references
     */
    private function deleteStoredImages(array $references): void
    {
        foreach ($references as $reference) {
            MediaStorage::delete($reference);
        }
    }
}
