<?php

use App\Enums\InvalidReason;
use App\Enums\TransactionAction;
use App\Enums\ViewStatus;
use App\Enums\VisitStatus;
use App\Models\CpmRate;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Models\View;
use App\Models\ViewDedup;
use App\Models\VisitSession;
use App\Services\UserAccountService;
use App\Services\Views\EarningsReleaser;
use App\Services\Views\ViewRecorder;
use App\Services\Views\VisitorContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->owner = User::factory()->create();
    $this->link = Link::factory()->create(['user_id' => $this->owner->id, 'alias' => 'Own3rX']);
});

test('links of a banned owner are treated as missing and come back after an unban', function () {
    $accounts = app(UserAccountService::class);

    $accounts->ban($this->admin, $this->owner, 'Fraud');

    $this->get('/Own3rX')->assertNotFound();
    $this->get('/go/Own3rX')->assertNotFound();

    $accounts->unban($this->admin, $this->owner);

    $this->get('/Own3rX')->assertRedirect('/go/Own3rX');
});

test('a visit that finishes after the owner was banned is recorded unpaid', function () {
    CpmRate::factory()->create(['country' => 'XX', 'level' => 1, 'rate_per_1000_micro' => 5_000_000]);
    $session = VisitSession::factory()->create(['link_id' => $this->link->id, 'fingerprint' => 'abc123']);
    $context = new VisitorContext($session->ip, $session->ua_hash, 'XX', 'test-agent');

    app(UserAccountService::class)->ban($this->admin, $this->owner, 'Fraud');

    $view = app(ViewRecorder::class)->record($session, $context, null);

    expect($view->status)->toBe(ViewStatus::Invalid)
        ->and($view->invalid_reason)->toBe(InvalidReason::OwnerBanned)
        ->and($view->earn_micro)->toBe(0)
        ->and($this->link->refresh()->views_count)->toBe(0)
        ->and(ViewDedup::query()->count())->toBe(0)
        ->and($session->refresh()->status)->toBe(VisitStatus::Failed);
});

test('another invalid reason is kept when the owner is banned', function () {
    $session = VisitSession::factory()->create(['link_id' => $this->link->id]);
    $context = new VisitorContext($session->ip, $session->ua_hash, 'XX', 'test-agent');
    $this->owner->forceFill(['status' => 'banned'])->save();

    $view = app(ViewRecorder::class)->record($session, $context, InvalidReason::Bot);

    expect($view->invalid_reason)->toBe(InvalidReason::Bot);
});

test('pending earnings of a banned owner are not released until the owner is unbanned', function () {
    $view = View::factory()->create([
        'link_id' => $this->link->id,
        'earn_micro' => 4_000,
        'matures_at' => now()->subMinute(),
    ]);
    $accounts = app(UserAccountService::class);
    $accounts->ban($this->admin, $this->owner, 'Fraud');

    expect(app(EarningsReleaser::class)->release())->toBe(0)
        ->and($view->refresh()->status)->toBe(ViewStatus::Pending)
        ->and($this->owner->refresh()->balance_micro)->toBe(0)
        ->and(Transaction::query()->where('action', TransactionAction::ViewEarnings)->count())->toBe(0);

    $accounts->unban($this->admin, $this->owner);

    expect(app(EarningsReleaser::class)->release())->toBe(1)
        ->and($view->refresh()->status)->toBe(ViewStatus::Released)
        ->and($this->owner->refresh()->balance_micro)->toBe(4_000);
});
