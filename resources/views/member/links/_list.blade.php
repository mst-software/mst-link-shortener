<div class="box box-solid">
  <div class="box-body">
    <form method="post" accept-charset="utf-8" class="form-inline" action="{{ $hiddenList ? route('member.links.hidden') : route('member.links') }}">
      @csrf
      <div class="form-group text">
        <input type="text" name="filter[alias]" class="form-control" size="10" placeholder="Alias" value="{{ $alias }}">
      </div>
      <button class="btn btn-default btn-sm" type="submit">Filter</button>
      <a href="{{ $hiddenList ? route('member.links.hidden') : route('member.links') }}" class="btn btn-link btn-sm">Reset</a>
    </form>
  </div>
</div>

@forelse ($links as $link)
  @php($shortUrl = url($link->alias))
  <div class="box box-solid link-card">
    <div class="box-body">
      <h4>
        <a href="{{ $shortUrl }}" target="_blank" rel="nofollow noopener noreferrer">
          <span class="glyphicon glyphicon-link"></span> {{ $link->url }}
        </a>
      </h4>
      <p class="text-muted">
        <small>
          <i class="fa fa-bar-chart"></i> <a href="{{ route('link.info', $link->alias) }}" target="_blank">Stats</a> -
          <i class="fa fa-calendar"></i> {{ $link->created_at?->format('H:i d/m/Y') }} -
          <a target="_blank" rel="nofollow noopener noreferrer" href="{{ $link->url }}">{{ $link->url }}</a>
        </small>
      </p>
      <div class="row">
        <div class="col-sm-6">
          <div class="input-group">
            <input type="text" class="form-control input-sm" value="{{ $shortUrl }}" readonly onfocus="this.select()" id="short-{{ $link->id }}">
            <div class="input-group-addon js-copy" data-copy-target="#short-{{ $link->id }}" title="Copy"><i class="fa fa-clone"></i></div>
          </div>
        </div>
        <div class="col-sm-6">
          <form method="post" action="{{ $hiddenList ? route('member.links.restore') : route('member.links.hide') }}" class="text-right">
            @csrf
            <input type="hidden" name="alias" value="{{ $link->alias }}">
            @if ($hiddenList)
              <button type="submit" class="btn btn-success btn-sm">Restore</button>
            @else
              <button type="submit" class="btn btn-danger btn-sm">Hide</button>
            @endif
          </form>
        </div>
      </div>
    </div>
  </div>
@empty
  <div class="text-center">No links yet.</div>
@endforelse

<div class="text-center">{{ $links->links() }}</div>
