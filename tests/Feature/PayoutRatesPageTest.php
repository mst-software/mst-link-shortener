<?php

test('payout rates page renders', function () {
    $this->get(route('payout-rates'))
        ->assertOk()
        ->assertSee('<title>Publisher Rates - LinkCash</title>', false)
        ->assertSee('Publisher Rates');
});

test('payout rates page contains no vietnamese text', function () {
    expect($this->get(route('payout-rates'))->getContent())
        ->not->toMatch('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu');
});
