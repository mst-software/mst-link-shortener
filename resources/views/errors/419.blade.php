@extends('errors.layout')

@section('code', '419')
@section('title', 'Page expired')
@section('message', 'This page was open for too long and your session expired. Go back, refresh the page and try again.')

@section('actions')
  <a href="{{ url()->previous(route('home')) }}" class="btn-ghost">Go back</a>
@endsection
