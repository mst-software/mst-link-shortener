{{-- Warns when PHP accepts smaller uploads than the form allows (upload_max_filesize / post_max_size), so larger files would fail without a clear error. --}}
@props(['maxKilobytes'])

@if (\Illuminate\Http\UploadedFile::getMaxFilesize() < $maxKilobytes * 1024)
  <div class="callout callout-warning">
    <p>This server only accepts uploads up to {{ ini_get('upload_max_filesize') }}. Keep images below that size, or ask your host to raise <code>upload_max_filesize</code> and <code>post_max_size</code> in the PHP settings.</p>
  </div>
@endif
