@extends('layouts.member')

@section('title', 'Quick Link')

@section('breadcrumb')
  <li>Tools</li>
  <li class="active">Quick Link</li>
@endsection

@section('content')
  <div class="box box-primary">
    <div class="box-body">
      <div class="callout callout-success">
        <h4>Your API token:</h4>
        <pre>{{ $token }}</pre>
      </div>
      <p>Anyone can use the shortest way to shorten links with LinkCash.</p>
      <p>Just copy the link below into your browser's address bar, replace yourdestinationlink.com with the link you want to shorten and press ENTER. LinkCash will shorten the link automatically. Copy it anywhere you want to share it and you will get paid for every valid view.</p>
      <pre>{{ $baseUrl }}/st?api={{ $token }}&amp;url=yourdestinationlink.com</pre>
    </div>
  </div>
@endsection
