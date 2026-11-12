<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('member pages', [
    'dashboard' => ['member.dashboard', '/member/dashboard'],
    'notify' => ['member.notify', '/member/notify'],
    'statistic' => ['member.statistic', '/member/statistic'],
    'popular link' => ['member.popular-link', '/member/popular-link'],
    'links' => ['member.links', '/member/links'],
    'hidden links' => ['member.links.hidden', '/member/links/hidden'],
    'withdraws' => ['member.withdraws', '/member/withdraws'],
    'quick link' => ['member.tools.quick', '/member/tools/quick'],
    'mass shrinker' => ['member.tools.mass-shrinker', '/member/tools/mass-shrinker'],
    'full page script' => ['member.tools.full', '/member/tools/full'],
    'developers api' => ['member.tools.api', '/member/tools/api'],
    'referrals' => ['member.referrals', '/member/referrals'],
    'profile' => ['member.user.profile', '/member/user/profile'],
    'change password' => ['member.user.change-password', '/member/user/change-password'],
    'change email' => ['member.user.change-email', '/member/user/change-email'],
    'support' => ['member.form.support', '/member/form/support'],
]);

test('guests are redirected to sign in', function () {
    $this->get('/member/dashboard')->assertRedirect(route('login'));
});

test('member page is served at the reference url inside the adminlte layout', function (string $name, string $path) {
    expect(route($name, absolute: false))->toBe($path);

    $this->actingAs(User::factory()->create())
        ->get($path)
        ->assertOk()
        ->assertSee('class="hold-transition skin-blue sidebar-mini"', false)
        ->assertSee('class="main-header"', false)
        ->assertSee('class="main-sidebar"', false)
        ->assertSee('class="content-wrapper"', false)
        ->assertSee('class="main-footer"', false);
})->with('member pages');

test('links page also accepts the filter post', function () {
    $this->actingAs(User::factory()->create())
        ->post('/member/links', ['filter' => ['alias' => 'abc']])
        ->assertOk();
});

test('sidebar shows every menu label', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertSeeInOrder([
            'Shorten a new link',
            'Overview',
            'Notifications',
            'Statistics',
            'Top Links',
            'Links',
            'All Links',
            'Hidden Links',
            'Withdraws',
            'Tools',
            'Quick Link',
            'Mass Shrinker',
            'Full Page Script',
            'Developers API',
            'Referrals',
            'Settings',
            'Profile',
            'Change Password',
            'Change Email',
            'Support',
        ]);
});

test('top bar shows the member label, the user name and a sign out form', function () {
    $user = User::factory()->create(['fullname' => 'Jane Publisher']);

    $this->actingAs($user)
        ->get(route('member.dashboard'))
        ->assertSee('<span class="logo-lg"><b>Member</b></span>', false)
        ->assertSee('Jane Publisher')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('Copyright &copy; '.now()->year, false)
        ->assertSee('Version</b> 1.0', false);
});

test('shorten box posts to the shorten endpoint with the member default level selected', function () {
    $user = User::factory()->create(['default_level' => 3]);

    $this->actingAs($user)
        ->get(route('member.dashboard'))
        ->assertSee('action="'.url('links/shorten').'"', false)
        ->assertSee('name="url"', false)
        ->assertSee('name="alias"', false)
        ->assertSee('<option value="3" selected>Level 3</option>', false)
        ->assertDontSee('<option value="1" selected>', false);
});

test('submenu of the current page is open and its item is active', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('member.user.change-password'))
        ->assertSee('<li class="treeview active">', false)
        ->assertSee('<ul class="treeview-menu menu-open">', false)
        ->assertSee('<li class="active">', false);
});

test('sidebar has no admin link', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('member.dashboard'))
        ->assertDontSee('/admin', false);
});

test('banned users are signed out and sent to sign in with an error', function () {
    $user = User::factory()->banned()->create();

    $this->actingAs($user)
        ->get(route('member.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('member pages contain no vietnamese text', function () {
    expect($this->actingAs(User::factory()->create())->get(route('member.dashboard'))->getContent())
        ->not->toMatch('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu');
});
