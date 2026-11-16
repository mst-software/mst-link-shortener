{{-- Shown by `php artisan down` and abort(503). The admin maintenance switch renders maintenance.blade.php instead. --}}
@extends('errors.layout')

@section('code', '503')
@section('title', 'Back soon')
@section('message', \App\Http\Middleware\CheckMaintenanceMode::MESSAGE)
