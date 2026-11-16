@extends('layouts.member')

@section('title', 'Developers API')

@section('breadcrumb')
  <li>Tools</li>
  <li class="active">Developers API</li>
@endsection

@section('content')
  <div class="box box-primary">
    <div class="box-body">
      <div class="callout callout-success">
        <h4>Your API token:</h4>
        <pre>{{ $token }}</pre>
      </div>
      <p>Keep your token secret: anyone who has it can create links on your account. If it leaks, regenerate it below. The old token stops working at once, so update every website and script that uses it.</p>
      <form method="post" action="{{ route('member.tools.api.regenerate-token') }}" class="form-inline" onsubmit="return confirm('Regenerate your API token? The old token stops working at once.')" novalidate>
        @csrf
        <div @class(['form-group', 'has-error' => $errors->has('current_password')])>
          <label for="current_password" class="sr-only">Current password</label>
          <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Current password" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-warning">Regenerate API token</button>
        @error('current_password')
          <span class="help-block text-danger">{{ $message }}</span>
        @enderror
      </form>
      <hr>
      <p>For developers {{ config('app.name') }} prepared an <b>API</b> which returns responses in <b>JSON</b>.</p>
      <p>Currently there is one method which can be used to shorten links on behalf of your account.</p>
      <p>All you have to do is to send a <b>GET</b> request with your API token and URL like the following:</p>
      <div class="well">{{ $baseUrl }}/api?api={{ $token }}&amp;url=yourdestinationlink.com</div>
      <p>Optional parameters: <code>alias</code>, <code>level</code> (1 to 3, defaults to your Settings level, currently {{ $level }}), <code>password</code> (4 to 64 characters, visitors must enter it before the link opens), <code>expires_at</code> (a future date in UTC such as <code>2027-01-31 23:59</code>, after which the link stops working) and <code>format=text</code> to get the short link as plain text.</p>
      <p><b>Note:</b> the <code>password</code> parameter is sent in the query string, so it may be stored in server, proxy or browser logs. Do not reuse a password you use anywhere else.</p>
      <p>You will get a JSON response like the following:</p>
      <div class="well">{"status":"success","message":"","shortenedUrl":"{{ $baseUrl }}\/xxxxxx"}</div>
      <h3>Using the API in PHP</h3>
      <p>To use the API in your PHP application, you need to send a GET request via file_get_contents or cURL. Please check the sample below using file_get_contents.</p>
      <div class="well">
        $long_url = urlencode('yourdestinationlink.com');<br>
        $api_token = '{{ $token }}';<br>
        $api_url = "{{ $baseUrl }}/api?api={$api_token}&amp;url={$long_url}";<br>
        $result = @json_decode(file_get_contents($api_url), true);<br>
        if ($result["status"] !== 'success') {<br>
        &emsp;echo $result["message"];<br>
        } else {<br>
        &emsp;echo $result["shortenedUrl"];<br>
        }
      </div>
    </div>
  </div>
@endsection
