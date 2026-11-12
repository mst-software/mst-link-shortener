<?php

use App\Enums\PayoutMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function profileData(array $overrides = []): array
{
    return array_merge([
        'fullname' => 'Jane Doe',
        'phone_number' => '0123456789',
        'address' => '1 Main Street',
        'district' => 'Central',
        'province' => 'Capital',
        'country' => 'VN',
        'traffic_source' => 'My blog',
        'default_level' => 2,
        'payout_method' => 'paypal',
        'paypal_email' => 'jane@paypal.test',
    ], $overrides);
}

test('the profile is saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('member.user.profile.update'), profileData())
        ->assertRedirect(route('member.user.profile'))
        ->assertSessionHas('success', 'Saved.');

    $user->refresh();

    expect($user->fullname)->toBe('Jane Doe')
        ->and($user->country)->toBe('VN')
        ->and($user->default_level)->toBe(2)
        ->and($user->payout_method)->toBe(PayoutMethod::Paypal)
        ->and($user->payout_details)->toBe(['paypal_email' => 'jane@paypal.test']);
});

test('bank transfer details are stored under their own keys', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('member.user.profile.update'), profileData([
        'payout_method' => 'bank_transfer',
        'bank_name' => 'Example Bank',
        'account_number' => '123456',
        'account_holder' => 'Jane Doe',
        'branch' => 'Main',
        'bank_phone_number' => '0987654321',
    ]))->assertSessionHasNoErrors();

    expect($user->refresh()->payout_details)->toBe([
        'bank_name' => 'Example Bank',
        'account_number' => '123456',
        'account_holder' => 'Jane Doe',
        'branch' => 'Main',
        'phone_number' => '0987654321',
    ]);
});

test('the default level must be 1, 2 or 3', function (int $level, bool $valid) {
    $response = $this->actingAs(User::factory()->create())
        ->post(route('member.user.profile.update'), profileData(['default_level' => $level]));

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('default_level');
})->with([[0, false], [4, false], [2, true]]);

test('paypal requires an email', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('member.user.profile.update'), profileData(['paypal_email' => '']))
        ->assertSessionHasErrors('paypal_email');
});

test('the settings pages render', function (string $route) {
    $this->actingAs(User::factory()->create())->get(route($route))->assertOk();
})->with(['member.user.profile', 'member.user.change-password', 'member.user.change-email']);

test('the password is changed and the session is kept', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('member.user.change-password.update'), ['current_password' => 'password', 'password' => 'a-new-password'])
        ->assertSessionHas('success', 'Saved.');

    expect(Hash::check('a-new-password', $user->refresh()->password))->toBeTrue();

    $this->get(route('member.dashboard'))->assertOk();
});

test('a wrong current password is rejected when changing the password', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('member.user.change-password.update'), ['current_password' => 'wrong', 'password' => 'a-new-password'])
        ->assertSessionHasErrors('current_password');
});

test('the email is changed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('member.user.change-email.update'), ['current_password' => 'password', 'email' => 'fresh@example.com'])
        ->assertSessionHas('success', 'Saved.');

    expect($user->refresh()->email)->toBe('fresh@example.com');
});

test('a wrong current password is rejected when changing the email', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('member.user.change-email.update'), ['current_password' => 'wrong', 'email' => 'fresh@example.com'])
        ->assertSessionHasErrors('current_password');
});

test('an email that is already used is rejected', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs(User::factory()->create())
        ->post(route('member.user.change-email.update'), ['current_password' => 'password', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});
