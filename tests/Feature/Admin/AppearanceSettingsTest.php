<?php

use App\Enums\BrandAsset;
use App\Models\Link;
use App\Models\User;
use App\Services\Branding;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
});

/**
 * Save the appearance form with the default colour and the given fields.
 *
 * @param  array<string, mixed>  $fields
 */
function saveAppearance(array $fields): TestResponse
{
    return test()->actingAs(test()->admin)
        ->put(route('admin.settings.appearance.update'), array_merge(['brand_color' => Branding::DEFAULT_COLOR], $fields));
}

test('the appearance page shows the default images while nothing is uploaded', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.appearance.edit'))
        ->assertOk()
        ->assertSee('Logo for light backgrounds')
        ->assertSee('Default image')
        ->assertDontSee('Reset to default');
});

test('an uploaded logo is converted to webp, previewed and used on the public pages', function () {
    saveAppearance(['logo_dark' => UploadedFile::fake()->image('white-logo.png', 1600, 400)])
        ->assertRedirect(route('admin.settings.appearance.edit'))
        ->assertSessionHasNoErrors();

    $reference = Settings::string('brand_logo_dark');
    $url = Storage::disk('public')->url($reference);

    expect($reference)->toMatch('#^branding/[A-Za-z0-9]{40}\.webp$#');
    Storage::disk('public')->assertExists($reference);

    $this->actingAs($this->admin)->get(route('admin.settings.appearance.edit'))->assertSee($url)->assertSee('Reset to default');
    $this->get(route('home'))->assertOk()->assertSee($url);
});

test('the light logo, favicon and share image are used by the layouts', function () {
    saveAppearance([
        'logo_light' => UploadedFile::fake()->image('dark-logo.png'),
        'favicon' => UploadedFile::fake()->image('icon.png', 64, 64),
        'share_image' => UploadedFile::fake()->image('share.jpg', 1200, 630),
    ])->assertSessionHasNoErrors();

    $this->get(route('login'))
        ->assertSee(Branding::imageUrl(BrandAsset::LogoLight))
        ->assertSee('<link rel="icon" type="image/png" href="'.Branding::imageUrl(BrandAsset::Favicon).'">', false);

    $this->get(route('home'))
        ->assertSee('<meta property="og:image" content="'.Branding::imageUrl(BrandAsset::ShareImage).'">', false);
});

test('no share image is announced while none is uploaded', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('og:image', false);
});

test('replacing an image deletes the old file', function () {
    saveAppearance(['logo_light' => UploadedFile::fake()->image('first.png')]);
    $first = Settings::string('brand_logo_light');

    saveAppearance(['logo_light' => UploadedFile::fake()->image('second.png')]);
    $second = Settings::string('brand_logo_light');

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('a rejected replacement keeps the current image and its file', function () {
    saveAppearance(['logo_light' => UploadedFile::fake()->image('first.png')]);
    $current = Settings::string('brand_logo_light');

    saveAppearance(['logo_light' => UploadedFile::fake()->createWithContent('logo.png', "\x89PNG\r\n\x1a\n".'broken')])
        ->assertSessionHasErrors('logo_light');

    expect(Settings::string('brand_logo_light'))->toBe($current)
        ->and(Storage::disk('public')->allFiles())->toBe([$current]);
});

test('reset to default removes the uploaded image and its file', function () {
    saveAppearance(['favicon' => UploadedFile::fake()->image('icon.png', 64, 64)]);
    $reference = Settings::string('brand_favicon');

    $this->actingAs($this->admin)
        ->delete(route('admin.settings.appearance.images.reset', BrandAsset::Favicon))
        ->assertRedirect(route('admin.settings.appearance.edit'))
        ->assertSessionHas('success', 'Favicon reset to the default.');

    expect(Settings::has('brand_favicon'))->toBeFalse()
        ->and(Branding::uploadedImageUrl(BrandAsset::Favicon))->toBeNull();
    Storage::disk('public')->assertMissing($reference);
});

test('an unknown image cannot be reset', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.settings.appearance.images.reset', 'site_name'))
        ->assertNotFound();
});

test('files that are not acceptable images are rejected', function (Closure $file) {
    saveAppearance(['logo_light' => $file()])->assertSessionHasErrors('logo_light');

    expect(Settings::has('brand_logo_light'))->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'pdf' => [fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')],
    'svg' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
    'php disguised as png' => [fn () => UploadedFile::fake()->createWithContent('logo.png', '<?php echo "owned"; ?>')],
    'png header without an image' => [fn () => UploadedFile::fake()->createWithContent('logo.png', "\x89PNG\r\n\x1a\n".'<?php echo 1; ?>')],
    'oversized' => [fn () => UploadedFile::fake()->image('logo.png')->size(6000)],
]);

test('a rejected image does not undo the other changes', function () {
    saveAppearance([
        'brand_color' => '#1e3a8a',
        'logo_dark' => UploadedFile::fake()->image('ok.png'),
        'logo_light' => UploadedFile::fake()->createWithContent('logo.png', "\x89PNG\r\n\x1a\n".'broken'),
    ])->assertSessionHasErrors('logo_light');

    expect(Branding::customColor())->toBe('#1e3a8a')
        ->and(Settings::has('brand_logo_dark'))->toBeTrue()
        ->and(Settings::has('brand_logo_light'))->toBeFalse();
});

test('the brand colour must be a #RRGGBB colour', function (string $color) {
    saveAppearance(['brand_color' => $color])->assertSessionHasErrors('brand_color');

    expect(Branding::customColor())->toBeNull();
})->with(['038018', '#03801', '#zzzzzz', 'red', '#038018;}body{display:none}', '']);

test('a chosen brand colour overrides the brand variables on the public, sign in and visit pages', function () {
    saveAppearance(['brand_color' => '#1E3A8A'])->assertSessionHasNoErrors();

    expect(Branding::customColor())->toBe('#1e3a8a');

    Link::factory()->create(['alias' => 'Ab3xYz']);

    foreach ([route('home'), route('login'), '/go/Ab3xYz'] as $url) {
        $this->get($url)->assertOk()->assertSee('<style>:root{--color-g:#1e3a8a;', false)->assertSee('--color-brand-600:#1e3a8a;', false);
    }
});

test('the member, advertiser and admin areas keep their own colours', function () {
    Branding::setColor('#1e3a8a');

    $this->actingAs(User::factory()->create())->get(route('member.dashboard'))->assertOk()->assertDontSee('--color-brand-600', false);
    $this->actingAs($this->admin)->get(route('admin.settings.appearance.edit'))->assertOk()->assertDontSee('<style>:root{', false);
});

test('no colour override is rendered while the default colour is used', function () {
    saveAppearance(['brand_color' => '#038018'])->assertSessionHasNoErrors();

    expect(Settings::has('brand_color'))->toBeFalse();

    $this->get(route('home'))->assertOk()->assertDontSee('<style>:root{', false);
});

test('a light brand colour gets dark button text', function () {
    Branding::setColor('#facc15');

    expect(Branding::css())->not->toContain('--color-brand-contrast:#ffffff;');
    $this->get(route('home'))->assertSee('--color-brand-contrast:#', false);
});

test('the cached colour css follows a new colour and reset to default', function () {
    Branding::setColor('#1e3a8a');
    expect(Branding::css())->toContain('--color-brand-600:#1e3a8a;');

    saveAppearance(['brand_color' => '#e11d48']);
    expect(Branding::css())->toContain('--color-brand-600:#e11d48;');

    $this->actingAs($this->admin)
        ->delete(route('admin.settings.appearance.color.reset'))
        ->assertRedirect(route('admin.settings.appearance.edit'))
        ->assertSessionHas('success', 'Brand colour reset to the default.');

    expect(Branding::customColor())->toBeNull()
        ->and(Branding::css())->toBe('');
});

test('members cannot change the appearance', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->put(route('admin.settings.appearance.update'), ['brand_color' => '#1e3a8a'])->assertForbidden();
    $this->actingAs($member)->delete(route('admin.settings.appearance.color.reset'))->assertForbidden();
    $this->actingAs($member)->delete(route('admin.settings.appearance.images.reset', BrandAsset::LogoLight))->assertForbidden();

    expect(Branding::customColor())->toBeNull();
});
