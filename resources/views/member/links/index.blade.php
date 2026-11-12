@extends('layouts.member')

@section('title', 'All Links')

@section('breadcrumb')
  <li>Links</li>
  <li class="active">All Links</li>
@endsection

@section('content')
  @include('member.links._list', ['hiddenList' => false])
@endsection
