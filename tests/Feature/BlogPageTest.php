<?php

test('blog page lists every post with its category filter', function () {
    $this->get(route('blog'))
        ->assertOk()
        ->assertSee('<title>Blog - LinkCash</title>', false)
        ->assertSee('5 ways to get more views on your short links')
        ->assertSee('data-filter="guide"', false)
        ->assertSee('id="postModal"', false);
});

test('blog page contains no vietnamese text', function () {
    expect($this->get(route('blog'))->getContent())
        ->not->toMatch('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu');
});
