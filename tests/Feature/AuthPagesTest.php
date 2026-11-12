<?php

dataset('auth pages', [
    'login' => ['login', 'Sign In - LinkCash', 'Sign in to start your session', 'signin-form'],
    'register' => ['register', 'Sign Up - LinkCash', 'Register a new membership', 'signup-form'],
    'forgot password' => ['password.request', 'Forgot Password - LinkCash', 'Enter your e-mail address below to reset your password.', 'forgotpassword-form'],
]);

test('auth page uses the standalone auth layout', function (string $route, string $title, string $text, string $formId) {
    $response = $this->get(route($route));

    $response->assertOk()
        ->assertSee("<title>{$title}</title>", false)
        ->assertSee($text)
        ->assertSee("id=\"{$formId}\"", false)
        ->assertSee('class="hold-transition login-page"', false)
        ->assertDontSee('id="mainNav"', false);
})->with('auth pages');

test('auth pages contain no vietnamese text', function () {
    foreach (['login', 'register', 'password.request'] as $route) {
        expect($this->get(route($route))->getContent())
            ->not->toMatch('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu');
    }
});

test('register page links to the legal pages', function () {
    $this->get(route('register'))
        ->assertSee(route('terms'), false)
        ->assertSee(route('privacy'), false);
});

test('login page links to forgot password and register', function () {
    $this->get(route('login'))
        ->assertSee(route('password.request'), false)
        ->assertSee(route('register'), false);
});

test('register page has a password toggle and strength meter instead of a confirm field', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('class="pw-toggle"', false)
        ->assertSee('class="pw-meter"', false)
        ->assertDontSee('password_compare', false)
        ->assertDontSee('Re-enter Password');
});
