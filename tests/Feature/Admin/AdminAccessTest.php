<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to sign in', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('members get a 403', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admins can open the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Admin');
});

test('banned admins are signed out', function () {
    $this->actingAs(User::factory()->admin()->banned()->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});
