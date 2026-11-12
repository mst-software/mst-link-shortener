@extends('layouts.member')

@section('title', 'Full Page Script')

@section('breadcrumb')
  <li>Tools</li>
  <li class="active">Full Page Script</li>
@endsection

@section('content')
  <div class="box box-primary">
    <div class="box-body">
      <p>If you have a website with hundreds or thousands of links you want to change over to LinkCash, please use the script below.</p>
      <p>Simply copy and paste the code below on to your webpage or blog and the links will be updated automatically!</p>
      <p>You can add or remove any domains for the code that you use on your website.</p>

      <pre>&lt;script type="text/javascript"&gt;
    var linkcash_url = '{{ $baseUrl }}/';
    var linkcash_api_token = '{{ $token }}';
    var linkcash_advert = {{ $level }};
    var linkcash_domains = ['depositfiles.com', 'uploading.com', 'uploadable.ch'];
&lt;/script&gt;
&lt;script src='{{ $baseUrl }}/js/full-script.js'&gt;&lt;/script&gt;</pre>

      <p>Or if you wish to change every link to LinkCash on your website (without stating exactly which domains) please use the following code.</p>

      <pre>&lt;script type="text/javascript"&gt;
    var linkcash_url = '{{ $baseUrl }}/';
    var linkcash_api_token = '{{ $token }}';
    var linkcash_advert = {{ $level }};
    var linkcash_exclude_domains = ['example.com', 'yoursite.com'];
&lt;/script&gt;
&lt;script src='{{ $baseUrl }}/js/full-script.js'&gt;&lt;/script&gt;</pre>
      <hr>
    </div>
  </div>
@endsection
