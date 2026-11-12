@extends('layouts.member')

@section('title', 'Mass Shrinker')

@section('breadcrumb')
  <li>Tools</li>
  <li class="active">Mass Shrinker</li>
@endsection

@section('content')
  <div class="box box-primary">
    <div class="box-body">
      <p>Enter up to 20 URLs (one per line) to shorten them. The links are added to your account statistics.</p>
      <p>Note: the Mass Shrinker may be disabled if it is abused. Only create links that you will actually use.</p>
      <form action="{{ route('member.tools.mass-shrinker.submit') }}" method="post">
        @csrf
        <div class="form-group @error('urls') has-error @enderror">
          <label for="urls">Links</label>
          <textarea name="urls" id="urls" class="form-control" placeholder="Links" rows="4">{{ old('urls') }}</textarea>
          @error('urls')
            <span class="help-block">{{ $message }}</span>
          @enderror
        </div>
        <input type="submit" name="submit" id="submit" value="Submit" class="btn btn-primary">
      </form>
    </div>
  </div>

  @if ($results !== null)
    <div class="box box-solid">
      <div class="box-body">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>URL</th>
              <th>Result</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($results as $result)
              <tr>
                <td style="word-break: break-all;">{{ $result['url'] }}</td>
                <td>
                  @if ($result['short_url'])
                    <a href="{{ $result['short_url'] }}" target="_blank" rel="nofollow noopener noreferrer">{{ $result['short_url'] }}</a>
                  @else
                    <span class="text-danger">{{ $result['error'] }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection
