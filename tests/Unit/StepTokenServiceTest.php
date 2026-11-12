<?php

use App\Enums\TokenResult;
use App\Models\VisitSession;
use App\Services\Views\StepTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new StepTokenService;
    $this->uaHash = hash('sha256', 'Mozilla/5.0 test');
    $this->session = VisitSession::factory()->create(['ua_hash' => $this->uaHash, 'ip' => '203.0.113.9']);
});

it('verifies a valid token once and reports reuse the second time', function () {
    $token = $this->service->issue($this->session, 1);

    expect($this->service->verify($this->session, 1, $token, $this->uaHash))->toBe(TokenResult::Valid)
        ->and($this->session->fresh()->token_hash)->toBeNull()
        ->and($this->service->verify($this->session->fresh(), 1, $token, $this->uaHash))->toBe(TokenResult::Reused);
});

it('stores the token hash and the issue time on the session', function () {
    $token = $this->service->issue($this->session, 1);
    $fresh = $this->session->fresh();

    expect($fresh->token_hash)->toBe(hash('sha256', $token))
        ->and($fresh->step_issued_at)->not->toBeNull();
});

it('rejects a token with one altered character as tampered', function (string $part) {
    $token = $this->service->issue($this->session, 1);
    [$payload, $signature] = explode('.', $token);

    $subject = $part === 'payload' ? $payload : $signature;
    $altered = substr($subject, 0, -1).($subject[strlen($subject) - 1] === 'A' ? 'B' : 'A');
    $tampered = $part === 'payload' ? $altered.'.'.$signature : $payload.'.'.$altered;

    expect($this->service->verify($this->session, 1, $tampered, $this->uaHash))->toBe(TokenResult::Tampered);
})->with(['payload', 'signature']);

it('rejects malformed tokens as tampered', function (string $token) {
    expect($this->service->verify($this->session, 1, $token, $this->uaHash))->toBe(TokenResult::Tampered);
})->with(['empty' => '', 'no dot' => 'abc', 'too many parts' => 'a.b.c', 'bad base64' => '!!!.???']);

it('rejects a token presented with a different user agent', function () {
    $token = $this->service->issue($this->session, 1);

    expect($this->service->verify($this->session, 1, $token, hash('sha256', 'other')))->toBe(TokenResult::WrongAgent);
});

it('rejects a step 1 token presented for step 2', function () {
    $token = $this->service->issue($this->session, 1);

    expect($this->service->verify($this->session, 2, $token, $this->uaHash))->toBe(TokenResult::WrongStep);
});

it('rejects a token that belongs to another session', function () {
    $other = VisitSession::factory()->create(['ua_hash' => $this->uaHash]);
    $token = $this->service->issue($other, 1);

    expect($this->service->verify($this->session, 1, $token, $this->uaHash))->toBe(TokenResult::Tampered);
});

it('treats an older token as reused once a newer one was issued', function () {
    $first = $this->service->issue($this->session, 1);
    $this->service->issue($this->session, 1);

    expect($this->service->verify($this->session->fresh(), 1, $first, $this->uaHash))->toBe(TokenResult::Reused);
});

it('does not consume the token when the user agent is wrong', function () {
    $token = $this->service->issue($this->session, 1);

    $this->service->verify($this->session, 1, $token, hash('sha256', 'other'));

    expect($this->service->verify($this->session->fresh(), 1, $token, $this->uaHash))->toBe(TokenResult::Valid);
});

it('does not put the ip address in the token', function () {
    $token = $this->service->issue($this->session, 1);
    [$payload] = explode('.', $token);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    expect(array_keys($claims))->toEqualCanonicalizing(['sid', 'step', 'ua', 'iat', 'nonce'])
        ->and($token)->not->toContain('203.0.113.9')
        ->and(json_encode($claims))->not->toContain('203.0.113.9');
});
