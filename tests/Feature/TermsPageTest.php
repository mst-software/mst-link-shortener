<?php

test('terms page renders the terms inside the legal document layout', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('<title>Terms of Use - LinkCash</title>', false)
        ->assertSee('<body data-page="terms"', false)
        ->assertSee('<nav class="toc md:sticky md:top-[90px]" id="toc"></nav>', false)
        ->assertSee('Last updated: November 12, 2026')
        ->assertSee('1. Acceptance of Terms')
        ->assertSee('4. Prohibited Content')
        ->assertSee('6. Earnings and Payments')
        ->assertSee('13. Contact Us');
});

test('terms page links use named routes', function () {
    $this->get(route('terms'))
        ->assertSee(route('privacy'), false)
        ->assertSee(route('payout-rates').'#view-policy', false)
        ->assertSee(route('home').'#contact', false);
});

test('terms page contains no vietnamese text', function () {
    expect($this->get(route('terms'))->getContent())
        ->not->toMatch('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu');
});
