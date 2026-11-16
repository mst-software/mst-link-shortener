<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\BrandAsset;
use App\Exceptions\MediaUploadException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateAppearanceSettingsRequest;
use App\Services\Branding;
use App\Services\MediaUploader;
use App\Services\Settings;
use App\Support\BrandPalette;
use App\Support\MediaStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Throwable;

class AppearanceSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.appearance', [
            'assets' => BrandAsset::cases(),
            'brandColor' => Branding::color(),
            'hasCustomColor' => Branding::customColor() !== null,
            'defaultColor' => Branding::DEFAULT_COLOR,
            'palette' => BrandPalette::shades(Branding::color()),
            'textColor' => BrandPalette::textColor(Branding::color()),        ]);
    }

    /**
     * Save the colour and the uploaded images. Each new image is stored and saved in the settings before
     * the file it replaces is deleted, so the settings never point at a deleted file; a rejected image
     * keeps the current one.
     */
    public function update(UpdateAppearanceSettingsRequest $request, MediaUploader $uploader): RedirectResponse
    {
        Branding::setColor($request->validated('brand_color'));

        $errors = [];

        foreach (BrandAsset::cases() as $asset) {
            $file = $request->file($asset->value);

            if ($file === null) {
                continue;
            }

            try {
                $reference = $uploader->store($file, $asset->preset());
            } catch (MediaUploadException $exception) {
                $errors[$asset->value] = $exception->getMessage();

                continue;
            }

            $oldReference = Settings::string($asset->settingKey());

            try {
                Settings::set($asset->settingKey(), $reference);
            } catch (Throwable $exception) {
                MediaStorage::delete($reference);

                throw $exception;
            }

            MediaStorage::delete($oldReference);
        }

        if ($errors !== []) {
            return redirect()->route('admin.settings.appearance.edit')
                ->withErrors($errors)
                ->with('error', 'Some images were not saved. The other changes were saved.');
        }

        return redirect()->route('admin.settings.appearance.edit')->with('success', 'Appearance settings saved.');
    }

    /**
     * Remove an uploaded image so the bundled default is shown again.
     */
    public function resetImage(BrandAsset $asset): RedirectResponse
    {
        MediaStorage::delete(Settings::string($asset->settingKey()));
        Settings::forget($asset->settingKey());

        return redirect()->route('admin.settings.appearance.edit')
            ->with('success', $asset->defaultImage() === null ? $asset->label().' removed.' : $asset->label().' reset to the default.');
    }

    public function resetColor(): RedirectResponse
    {
        Branding::resetColor();

        return redirect()->route('admin.settings.appearance.edit')->with('success', 'Brand colour reset to the default.');
    }
}
