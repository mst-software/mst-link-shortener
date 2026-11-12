@extends('layouts.member')

@section('title', 'Hidden Links')

@section('breadcrumb')
  <li>Links</li>
  <li class="active">Hidden Links</li>
@endsection

@section('content')
  @include('member.links._list', ['hiddenList' => true])
@endsection
