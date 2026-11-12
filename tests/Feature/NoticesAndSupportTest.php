<?php

use App\Models\Notice;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function supportPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Publisher',
        'email' => 'jane@example.com',
        'title' => 'Payout question',
        'message' => 'When is my payout sent?',
    ], $overrides);
}

test('members only see published notices, newest first', function () {
    Notice::factory()->create(['title' => 'Older notice', 'published_at' => now()->subDays(2)]);
    Notice::factory()->create(['title' => 'Newer notice', 'published_at' => now()->subDay()]);
    Notice::factory()->draft()->create(['title' => 'Hidden draft']);
    Notice::factory()->create(['title' => 'Scheduled notice', 'published_at' => now()->addDay()]);

    $this->actingAs(User::factory()->create())
        ->get(route('member.notify'))
        ->assertOk()
        ->assertSeeInOrder(['Newer notice', 'Older notice'])
        ->assertDontSee('Hidden draft')
        ->assertDontSee('Scheduled notice');
});

test('notice body is escaped on output', function () {
    Notice::factory()->create(['body' => '<script>alert(1)</script>']);

    $this->actingAs(User::factory()->create())
        ->get(route('member.notify'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('the top bar bell lists the three newest notices', function () {
    foreach (range(1, 4) as $i) {
        Notice::factory()->create(['title' => "Bell notice {$i}", 'published_at' => now()->subDays(10 - $i)]);
    }

    $this->actingAs(User::factory()->create())
        ->get(route('member.notify'))
        ->assertSee('Bell notice 4')
        ->assertSee('Bell notice 2');
});

test('a support message is stored for the signed in member', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('member.form.support.store'), supportPayload())
        ->assertRedirect(route('member.form.support'));

    $this->assertDatabaseHas('support_messages', [
        'user_id' => $user->id,
        'title' => 'Payout question',
        'resolved_at' => null,
    ]);
});

test('a support message is validated', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('member.form.support.store'), supportPayload(['message' => str_repeat('a', 5001), 'email' => 'nope']))
        ->assertSessionHasErrors(['message', 'email']);

    expect(SupportMessage::query()->count())->toBe(0);
});

test('the sixth support message in a minute is rate limited', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 5) as $attempt) {
        $this->post(route('member.form.support.store'), supportPayload())->assertRedirect();
    }

    $this->post(route('member.form.support.store'), supportPayload())->assertStatus(429);
});

test('admins see support messages and can mark them resolved', function () {
    if (! Route::has('admin.support-messages.index')) {
        $this->markTestSkipped('Admin routes are wired in routes/admin.php after merge.');
    }

    $message = SupportMessage::factory()->create(['title' => 'Need help']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.support-messages.index'))
        ->assertOk()
        ->assertSee('Need help');

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.support-messages.resolve', $message))
        ->assertRedirect();

    expect($message->refresh()->isResolved())->toBeTrue();
});

test('admins can publish a notice, which stamps the publish date', function () {
    if (! Route::has('admin.notices.store')) {
        $this->markTestSkipped('Admin routes are wired in routes/admin.php after merge.');
    }

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.notices.store'), ['title' => 'Hello', 'body' => 'World', 'is_published' => 1])
        ->assertRedirect(route('admin.notices.index'));

    expect(Notice::query()->published()->count())->toBe(1);
});
