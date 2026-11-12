@extends('layouts.member')

@section('title', 'Link Statistics')

@section('breadcrumb')
  <li>Links</li>
  <li class="active">Link Statistics</li>
@endsection

@section('content')
  <div class="box box-solid">
    <div class="box-body">
      <h4 style="word-break: break-all;">
        <a href="{{ $shortUrl }}" target="_blank" rel="nofollow noopener noreferrer">{{ $shortUrl }}</a>
      </h4>
      <table class="table table-bordered">
        <tbody>
          <tr>
            <th style="width: 200px;">Destination URL</th>
            <td style="word-break: break-all;">{{ $link->url }}</td>
          </tr>
          <tr>
            <th>Short link</th>
            <td>{{ $shortUrl }}</td>
          </tr>
          <tr>
            <th>Created</th>
            <td>{{ $link->created_at?->format('H:i d/m/Y') }}</td>
          </tr>
          <tr>
            <th>Level</th>
            <td>Level {{ $link->level }}</td>
          </tr>
          <tr>
            <th>Valid views</th>
            <td>{{ number_format($link->views_count) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
@endsection
