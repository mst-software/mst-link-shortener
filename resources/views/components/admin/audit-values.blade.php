@props(['values' => null])

@foreach ($values ?? [] as $key => $value)
  <div><small>{{ str_replace('_', ' ', $key) }}: {{ $value === null ? '-' : (is_scalar($value) ? $value : json_encode($value)) }}</small></div>
@endforeach
