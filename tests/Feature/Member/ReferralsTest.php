<?php

use App\Enums\TransactionAction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the page shows the member referral link and commission percent', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('member.referrals'))
        ->assertOk()
        ->assertSee('My Referrals')
        ->assertSee(url('/ref/'.$member->referral_code))
        ->assertSee('12%');
});

test('an empty state is shown when nobody was referred', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('member.referrals'))
        ->assertOk()
        ->assertSee('You have not referred anyone yet');
});

test('only the own referrals are listed with masked usernames', function () {
    $member = User::factory()->create();
    User::factory()->create(['username' => 'alicewonder', 'referred_by' => $member->id]);
    User::factory()->create(['username' => 'strangerdanger', 'referred_by' => User::factory()->create()->id]);

    $this->actingAs($member)
        ->get(route('member.referrals'))
        ->assertOk()
        ->assertSee('ali***')
        ->assertDontSee('alicewonder')
        ->assertDontSee('str***');
});

test('commission totals sum the referral commission transactions per referred member', function () {
    $member = User::factory()->create();
    $referred = User::factory()->create(['username' => 'bobbuilder', 'referred_by' => $member->id]);

    foreach ([1_500_000, 500_000] as $amount) {
        Transaction::factory()->create([
            'user_id' => $member->id,
            'action' => TransactionAction::ReferralCommission,
            'amount_micro' => $amount,
            'reference_id' => (string) $referred->id,
        ]);
    }
    Transaction::factory()->create([
        'user_id' => $member->id,
        'action' => TransactionAction::ViewEarnings,
        'amount_micro' => 9_000_000,
        'reference_id' => (string) $referred->id,
    ]);

    $this->actingAs($member)
        ->get(route('member.referrals'))
        ->assertOk()
        ->assertSee('bob***')
        ->assertSee('$2.000')
        ->assertDontSee('$11.000');
});
