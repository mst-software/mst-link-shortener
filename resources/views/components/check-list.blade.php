@props(['items'])

<ul {{ $attributes->class(['check-list']) }}>
  @foreach ($items as $item)
    <li>{{ $item }}</li>
  @endforeach
</ul>
