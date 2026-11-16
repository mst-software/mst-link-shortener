<?php

use App\Enums\MediaPreset;
use App\Exceptions\MediaUploadException;
use App\Services\MediaUploader;
use App\Services\Settings;
use App\Support\MediaStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

uses(RefreshDatabase::class);

/**
 * A PNG with a fully transparent left half and an opaque red right half.
 */
function transparentPng(int $width = 400, int $height = 200): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, imagecolorallocatealpha($image, 255, 0, 0, 0));

    ob_start();
    imagepng($image);

    return UploadedFile::fake()->createWithContent('logo.png', (string) ob_get_clean());
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
    $this->uploader = app(MediaUploader::class);
});

test('images are converted to webp with a random name and scaled down', function () {
    $reference = $this->uploader->store(UploadedFile::fake()->image('My Logo.png', 2400, 1200), MediaPreset::Logo);

    expect($reference)->toMatch('#^branding/[A-Za-z0-9]{40}\.webp$#');
    Storage::disk('public')->assertExists($reference);

    $stored = ImageManager::gd()->read(Storage::disk('public')->get($reference));

    expect($stored->width())->toBe(1000)
        ->and($stored->height())->toBe(500)
        ->and(Storage::disk('public')->mimeType($reference))->toBe('image/webp');
});

test('png transparency is kept', function () {
    $reference = $this->uploader->store(transparentPng(), MediaPreset::Logo);

    $stored = ImageManager::gd()->read(Storage::disk('public')->get($reference));

    expect($stored->pickColor(10, 10)->isTransparent())->toBeTrue()
        ->and($stored->pickColor(390, 10)->isTransparent())->toBeFalse();
});

test('favicons are saved as small png files', function () {
    $reference = $this->uploader->store(UploadedFile::fake()->image('icon.jpg', 512, 512), MediaPreset::Favicon);

    expect($reference)->toEndWith('.png')
        ->and(ImageManager::gd()->read(Storage::disk('public')->get($reference))->width())->toBe(256);
});

test('campaign images keep their size', function () {
    $reference = $this->uploader->store(UploadedFile::fake()->image('google.png', 1800, 900), MediaPreset::CampaignImage);

    expect($reference)->toStartWith('campaigns/')
        ->and(ImageManager::gd()->read(Storage::disk('public')->get($reference))->width())->toBe(1800);
});

test('code appended to an image does not survive the re-encoding', function () {
    $source = UploadedFile::fake()->image('photo.png', 50, 50);
    $png = file_get_contents($source->getRealPath());
    $polyglot = UploadedFile::fake()->createWithContent('photo.png', $png.'<?php echo "owned"; ?>');

    $reference = $this->uploader->store($polyglot, MediaPreset::Logo);

    expect(Storage::disk('public')->get($reference))->not->toContain('<?php');
});

test('files that are not acceptable images are rejected', function (Closure $file, string $message) {
    expect(fn () => $this->uploader->store($file(), MediaPreset::Logo))
        ->toThrow(MediaUploadException::class, $message);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'pdf' => [fn () => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), 'Upload a JPG, PNG, WebP or GIF image.'],
    'svg' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'Upload a JPG, PNG, WebP or GIF image.'],
    'php disguised as png' => [fn () => UploadedFile::fake()->createWithContent('logo.png', '<?php echo "owned"; ?>'), 'The file is not a JPG, PNG, WebP or GIF image.'],
    'html disguised as jpg' => [fn () => UploadedFile::fake()->createWithContent('logo.jpg', '<html><body>hi</body></html>'), 'The file is not a JPG, PNG, WebP or GIF image.'],
    'oversized' => [fn () => UploadedFile::fake()->image('big.png')->size(6000), 'The image must not be larger than 5 MB.'],
]);

test('images over 10 megapixels are rejected before decoding', function () {
    $file = UploadedFile::fake()->image('huge.png', 4000, 2600);

    expect(fn () => $this->uploader->store($file, MediaPreset::Logo))
        ->toThrow(MediaUploadException::class, 'Upload an image of at most 10 megapixels');
});

test('images that need more memory than php allows are rejected instead of crashing', function () {
    $file = UploadedFile::fake()->image('photo.png', 3000, 2000);
    $originalLimit = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage() + 30 * 1024 * 1024));

    try {
        expect(fn () => $this->uploader->store($file, MediaPreset::Logo))
            ->toThrow(MediaUploadException::class, 'The server does not have enough memory to process an image this large.');
    } finally {
        ini_set('memory_limit', $originalLimit);
    }

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('an unlimited memory limit does not block uploads', function () {
    $originalLimit = ini_get('memory_limit');
    ini_set('memory_limit', '-1');

    try {
        $reference = $this->uploader->store(UploadedFile::fake()->image('logo.png', 800, 400), MediaPreset::Logo);
    } finally {
        ini_set('memory_limit', $originalLimit);
    }

    Storage::disk('public')->assertExists($reference);
});

test('animated gifs are rejected', function () {
    $gif = ImageManager::gd()->animate(function ($animation): void {
        $animation->add(ImageManager::gd()->create(20, 20)->fill('ff0000'), 0.2);
        $animation->add(ImageManager::gd()->create(20, 20)->fill('0000ff'), 0.2);
    })->toGif();

    expect(fn () => $this->uploader->store(UploadedFile::fake()->createWithContent('spin.gif', (string) $gif), MediaPreset::Logo))
        ->toThrow(MediaUploadException::class, 'Animated GIFs are not supported.');
});

test('uploads go to the s3 disk when it is selected and keep working after switching back', function () {
    Settings::set('upload_disk', 's3');

    $reference = $this->uploader->store(UploadedFile::fake()->image('logo.png'), MediaPreset::Logo);

    expect($reference)->toStartWith('s3:branding/');
    Storage::disk('s3')->assertExists(substr($reference, 3));
    Storage::disk('public')->assertMissing(substr($reference, 3));

    Settings::set('upload_disk', 'public');

    expect(MediaStorage::url($reference))->toBe(Storage::disk('s3')->url(substr($reference, 3)));

    MediaStorage::delete($reference);

    Storage::disk('s3')->assertMissing(substr($reference, 3));
});

test('a failing disk gives a readable error', function () {
    Settings::set('upload_disk', 's3');
    Storage::set('s3', Mockery::mock(Filesystem::class, function ($mock): void {
        $mock->shouldReceive('put')->andThrow(new RuntimeException('Access Denied'));
    }));

    expect(fn () => $this->uploader->store(UploadedFile::fake()->image('logo.png'), MediaPreset::Logo))
        ->toThrow(MediaUploadException::class, 'The image could not be saved.');
});

test('references that are not generated paths are ignored', function (string $reference) {
    expect(MediaStorage::parse($reference))->toBeNull()
        ->and(MediaStorage::url($reference))->toBeNull();
})->with(['../.env', '/etc/passwd', 'campaigns/../../.env', 'https://evil.test/a.png', 's3:../secret.txt', 'branding/logo.php ']);

test('legacy public disk paths keep resolving', function () {
    expect(MediaStorage::parse('campaigns/AbC123xyz.png'))->toBe(['disk' => 'public', 'path' => 'campaigns/AbC123xyz.png']);
});
