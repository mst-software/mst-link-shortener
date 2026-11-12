<?php

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Link::factory()->create(['alias' => 'Ab3xYz']);
});

/**
 * Load the visit page and return an XPath over its HTML.
 */
function visitPageXPath(string $url = '/go/Ab3xYz'): DOMXPath
{
    $html = test()->get($url)->assertOk()->getContent();

    $document = new DOMDocument;
    @$document->loadHTML($html);

    return new DOMXPath($document);
}

it('renders one shared card with the code box, captcha, session timer, button and status line', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//section[@id="visit"][@data-mode="loading"]')->length)->toBe(1)
        ->and($xpath->query('//section[@id="visit"][@data-info-url and @data-finish-url and @data-report-url and @data-captcha-provider]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//input[@id="visit-code"][@placeholder="Enter code"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-captcha"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-session"][@data-mode-only="keyword"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//button[@id="visit-button"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-status"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit-title"][@data-title-wait and @data-title-keyword]')->length)->toBe(1);
});

it('renders the keyword guide with six numbered steps, three images and a hidden video block', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//section[@id="keyword-guide"][@data-mode-only="keyword"][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="keyword-guide"]//ol/li//*[contains(@class, "visit-step-num")]')->length)->toBe(6)
        ->and($xpath->query('//*[@id="keyword-guide"]//ul[contains(@class, "visit-tips")]/li')->length)->toBe(3)
        ->and($xpath->query('//*[@id="keyword-guide"]//figure[@data-shot="google" or @data-shot="button" or @data-shot="position"]')->length)->toBe(3)
        ->and($xpath->query('//*[@id="keyword-guide"]//*[@data-kw="keyword"]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@id="keyword-guide"]//*[@data-kw="domain"]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@id="keyword-video"][@hidden]//iframe')->length)->toBe(1)
        ->and($xpath->query('//*[@id="keyword-guide"]//button[@data-report-open]')->length)->toBe(1);
});

it('renders the wait mode step indicator inside the shared card', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[@id="visit"]//*[@id="visit-step"][@data-mode-only="wait"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//button[@data-report-open]')->length)->toBe(1);
});

it('renders the report dialog with three choices, a captcha box and close and send buttons', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//dialog[@id="report-dialog"]//input[@type="radio"][@name="choice"]')->length)->toBe(3)
        ->and($xpath->query('//dialog[@id="report-dialog"]//input[@value="code_not_shown" or @value="page_not_shown" or @value="other"]')->length)->toBe(3)
        ->and($xpath->query('//dialog[@id="report-dialog"]//*[@id="report-captcha"]')->length)->toBe(1)
        ->and($xpath->query('//dialog[@id="report-dialog"]//button[@type="submit"]')->length)->toBe(1)
        ->and($xpath->query('//dialog[@id="report-dialog"]//button[@data-report-close]')->length)->toBe(2);
});

it('renders the sign up call to action and the footer links', function () {
    $this->get('/go/Ab3xYz')
        ->assertOk()
        ->assertSee('Ready to earn money?')
        ->assertSee(route('register'), false)
        ->assertSee(route('login'), false)
        ->assertSee(route('payout-rates'), false);
});

it('ignores the keyword preview switch outside the local environment', function () {
    $xpath = visitPageXPath('/go/Ab3xYz?preview=keyword');

    expect($xpath->query('//section[@id="visit"][@data-preview]')->length)->toBe(0);
});

it('embeds sample keyword data when previewing locally', function () {
    app()->detectEnvironment(fn () => 'local');

    $xpath = visitPageXPath('/go/Ab3xYz?preview=keyword');
    $preview = json_decode($xpath->query('//section[@id="visit"]/@data-preview')->item(0)?->nodeValue ?? '', true);

    expect($preview)->toBeArray()
        ->and($preview['mode'])->toBe('keyword')
        ->and($preview)->toHaveKeys(['keyword', 'display_domain', 'images', 'session_seconds_left', 'tutorial_video_url', 'captcha_required']);
});
