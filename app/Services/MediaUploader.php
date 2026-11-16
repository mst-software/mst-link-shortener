<?php

namespace App\Services;

use App\Enums\MediaPreset;
use App\Exceptions\MediaUploadException;
use App\Support\MediaStorage;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Decoders\FilePathImageDecoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * The single entry point for image uploads in the admin and advertiser areas.
 *
 * Every image is checked (extension, real content type, size, pixel count), decoded with GD and encoded
 * again, so only freshly written pixels are stored: a PHP or HTML "polyglot" hidden in an image cannot
 * survive. Stored file names are random and never come from the upload.
 *
 * SVG is rejected: it is a document that can carry scripts, and GD cannot decode it to re-encode it.
 * Animated GIFs are rejected too: GD would keep only the first frame, which would silently change the
 * image, and storing them as they are would skip the re-encoding that keeps uploads safe.
 *
 * PHP must accept uploads of this size: upload_max_filesize and post_max_size in php.ini must be at
 * least MAX_KILOBYTES (5 MB), and post_max_size larger when one form sends several images.
 */
class MediaUploader
{
    /**
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Content types detected from the file bytes (not the browser's claim).
     *
     * @var list<string>
     */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public const MAX_KILOBYTES = 5120;

    /**
     * Largest image GD is asked to decode (width times height), so a small file that expands to a huge
     * bitmap cannot exhaust the memory.
     */
    private const MAX_PIXELS = 25_000_000;

    private const WEBP_QUALITY = 80;

    private const NAME_LENGTH = 40;

    /**
     * Validate, re-encode and store the image and return the reference to save (see MediaStorage).
     *
     * @throws MediaUploadException
     */
    public function store(UploadedFile $file, MediaPreset $preset): string
    {
        $this->assertAcceptable($file);

        $image = $this->decode($file);

        if ($image->isAnimated()) {
            throw new MediaUploadException('Animated GIFs are not supported. Upload a still image.');
        }

        $maxDimension = $preset->maxDimension();

        if ($maxDimension !== null) {
            $image->scaleDown($maxDimension, $maxDimension);
        }

        $encoded = $preset->savesAsPng() ? $image->toPng() : $image->toWebp(quality: self::WEBP_QUALITY);
        $extension = $preset->savesAsPng() ? 'png' : 'webp';

        return MediaStorage::put(
            $preset->directory().'/'.Str::random(self::NAME_LENGTH).'.'.$extension,
            (string) $encoded,
        );
    }

    /**
     * Store the new image, then delete the file it replaces.
     *
     * @throws MediaUploadException
     */
    public function replace(UploadedFile $file, MediaPreset $preset, ?string $oldReference): string
    {
        $reference = $this->store($file, $preset);

        MediaStorage::delete($oldReference);

        return $reference;
    }

    /**
     * @throws MediaUploadException
     */
    private function assertAcceptable(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new MediaUploadException('The upload did not complete. Try again with a smaller file.');
        }

        if (! in_array(strtolower($file->getClientOriginalExtension()), self::ALLOWED_EXTENSIONS, true)) {
            throw new MediaUploadException('Upload a JPG, PNG, WebP or GIF image.');
        }

        if ((int) $file->getSize() > self::MAX_KILOBYTES * 1024) {
            throw new MediaUploadException('The image must not be larger than 5 MB.');
        }

        $path = (string) $file->getRealPath();

        if (! in_array((new finfo(FILEINFO_MIME_TYPE))->file($path), self::ALLOWED_MIME_TYPES, true)) {
            throw new MediaUploadException('The file is not a JPG, PNG, WebP or GIF image.');
        }

        $size = @getimagesize($path);

        if ($size === false) {
            throw new MediaUploadException('The file could not be read as an image.');
        }

        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw new MediaUploadException('The image has too many pixels. Upload an image of at most 5000 by 5000 pixels.');
        }
    }

    /**
     * Decode with GD. Harmless libpng warnings (for example about colour profiles) are ignored instead
     * of being turned into errors.
     *
     * @throws MediaUploadException
     */
    private function decode(UploadedFile $file): ImageInterface
    {
        set_error_handler(fn (int $level, string $message): bool => str_contains($message, 'libpng warning'));

        try {
            return ImageManager::gd(strip: true)->read((string) $file->getRealPath(), [FilePathImageDecoder::class]);
        } catch (Throwable) {
            throw new MediaUploadException('The file could not be read as an image.');
        } finally {
            restore_error_handler();
        }
    }
}
