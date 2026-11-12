<?php

test('home page renders the hero, sections and shared layout', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('<title>LinkCash - Best URL Shortener To Earn Money</title>', false)
        ->assertSee('Shorten URLs and')
        ->assertSee('How You <b>Start</b>?', false)
        ->assertSee('Why join us?')
        ->assertSee('Get in touch!')
        ->assertSee('Publisher Rates')
        ->assertSee('Developed by');
});

test('home page links use named routes', function () {
    $this->get(route('home'))
        ->assertSee(route('payout-rates'), false)
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false)
        ->assertSee(route('privacy'), false);
});

test('home page contains no vietnamese text', function () {
    expect($this->get(route('home'))->getContent())
        ->not->toMatch('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu');
});
