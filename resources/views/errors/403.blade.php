{{-- A message given to abort(403, '...') is written for visitors (for example "Sign-ups are closed"), so it is shown. --}}
@extends('errors.layout')

@section('code', '403')
@section('title', 'Access denied')
@section('message', isset($exception) && $exception->getMessage() !== '' ? $exception->getMessage() : 'You do not have permission to open this page. Sign in with an account that has access, or go back to the home page.')
