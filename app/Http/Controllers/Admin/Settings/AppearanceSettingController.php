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
use Illuminate\Http\UploadedFile;

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
            'textColor' => BrandPalette::textColor(Branding::color()),
            'serverLimitTooLow' => UploadedFile::getMaxFilesize() < MediaUploader::MAX_KILOBYTES * 1024,
        ]);
    }

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
                Settings::set($asset->settingKey(), $uploader->replace($file, $asset->preset(), Settings::string($asset->settingKey())));
            } catch (MediaUploadException $exception) {
                $errors[$asset->value] = $exception->getMessage();
            }
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
