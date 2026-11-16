<?php

use App\Enums\PayoutMethod;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;

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

test('changing the email resets verification and sends a new verification mail', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('member.user.change-email.update'), ['current_password' => 'password', 'email' => 'fresh@example.com'])
        ->assertRedirect(route('member.user.change-email'))
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->email)->toBe('fresh@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('an unverified member cannot change their password', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('member.user.change-password.update'), ['current_password' => 'password', 'password' => 'a-new-password'])
        ->assertRedirect(route('verification.notice'));

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('an unverified member can fix a mistyped email and gets a new verification mail', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'typo@example.con']);

    $this->actingAs($user)
        ->post(route('member.user.change-email.update'), ['current_password' => 'password', 'email' => 'fixed@example.com'])
        ->assertRedirect(route('member.user.change-email'))
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->email)->toBe('fixed@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('the email change is saved when the verification mail cannot be sent', function () {
    Exceptions::fake();
    Notification::shouldReceive('send')->andThrow(new TransportException('Mail server is down.'));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('member.user.change-email.update'), ['current_password' => 'password', 'email' => 'fresh@example.com'])
        ->assertRedirect(route('member.user.change-email'));

    $user->refresh();

    expect($user->email)->toBe('fresh@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();
    Exceptions::assertReported(TransportException::class);
});

test('the current password is required when changing the email', function (?string $currentPassword) {
    $user = User::factory()->unverified()->create(['email' => 'old@example.com']);

    $this->actingAs($user)
        ->post(route('member.user.change-email.update'), ['current_password' => $currentPassword, 'email' => 'fresh@example.com'])
        ->assertSessionHasErrors('current_password');

    expect($user->refresh()->email)->toBe('old@example.com');
})->with([
    'missing' => [null],
    'wrong' => ['wrong'],
]);

test('an email that is already used is rejected', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs(User::factory()->create())
        ->post(route('member.user.change-email.update'), ['current_password' => 'password', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

test('the profile only offers the payout methods the admin switched on', function () {
    Settings::set('payout_methods', 'bank_transfer,usdt');

    $this->actingAs(User::factory()->create())
        ->get(route('member.user.profile'))
        ->assertOk()
        ->assertSee('value="bank_transfer"', false)
        ->assertSee('value="usdt"', false)
        ->assertDontSee('value="paypal"', false)
        ->assertDontSee('value="momo"', false);
});

test('a payout method the admin switched off cannot be chosen', function () {
    Settings::set('payout_methods', 'bank_transfer');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('member.user.profile.update'), profileData())
        ->assertSessionHasErrors('payout_method');

    expect($user->refresh()->payout_method)->toBeNull();
});

test('a member whose saved payout method was switched off can still save other profile fields', function () {
    $user = User::factory()->create([
        'payout_method' => PayoutMethod::Paypal,
        'payout_details' => ['paypal_email' => 'jane@paypal.test'],
    ]);
    Settings::set('payout_methods', 'bank_transfer');

    $this->actingAs($user)
        ->get(route('member.user.profile'))
        ->assertOk()
        ->assertSee('value="paypal"', false)
        ->assertSee('Payouts by PayPal are no longer offered.');

    $this->actingAs($user)
        ->post(route('member.user.profile.update'), profileData(['fullname' => 'Jane Updated']))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->fullname)->toBe('Jane Updated')
        ->and($user->payout_method)->toBe(PayoutMethod::Paypal);
});
