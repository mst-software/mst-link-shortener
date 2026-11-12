<?php

use App\Services\Views\BotUserAgentDetector;

it('flags automated and empty user agents as bots', function (?string $userAgent) {
    expect((new BotUserAgentDetector)->isBot($userAgent))->toBeTrue();
})->with([
    'headless chrome' => 'Mozilla/5.0 HeadlessChrome/120.0.0.0 Safari/537.36',
    'null' => null,
    'empty' => '',
    'blank' => '   ',
    'googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
    'crawler' => 'SomeCrawler/1.0',
    'spider' => 'Baiduspider/2.0',
    'curl' => 'curl/8.4.0',
    'python requests' => 'python-requests/2.31',
    'phantomjs' => 'Mozilla/5.0 PhantomJS/2.1.1',
    'selenium' => 'Mozilla/5.0 Selenium/4',
]);

it('does not flag a regular browser', function () {
    $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 '
        .'(KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    expect((new BotUserAgentDetector)->isBot($iphone))->toBeFalse();
});
