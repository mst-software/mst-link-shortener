<?php

test('privacy page renders the policy inside the legal document layout', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('<title>Privacy Policy - LinkCash</title>', false)
        ->assertSee('<body data-page="privacy"', false)
        ->assertSee('<nav class="toc md:sticky md:top-[90px]" id="toc"></nav>', false)
        ->assertSee('Last updated: November 12, 2026')
        ->assertSee('1. Introduction')
        ->assertSee('2. Information We Collect')
        ->assertSee("10. Children's Privacy", false)
        ->assertSee('12. Contact Us');
});

test('privacy page links use named routes', function () {
    $this->get(route('privacy'))
        ->assertSee(route('home').'#contact', false);
});

test('privacy page contains no vietnamese text', function () {
    expect($this->get(route('privacy'))->getContent())
        ->not->toMatch('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu');
});
