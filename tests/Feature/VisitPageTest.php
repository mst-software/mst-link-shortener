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

/**
 * Decode the local preview data embedded on the visit box.
 *
 * @return array<string, mixed>|null
 */
function visitPreviewData(string $url): ?array
{
    $value = visitPageXPath($url)->query('//*[@id="visit"]/@data-preview')->item(0)?->nodeValue;

    return $value === null ? null : json_decode($value, true);
}

it('renders the visit box with the data attributes the script needs', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[@id="visit"][@data-mode="loading"][@data-alias="Ab3xYz"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"][@data-info-url and @data-finish-url and @data-start-url and @data-report-url]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"][@data-captcha-provider and @data-captcha-site-key and @data-captcha-script and @data-captcha-global]')->length)->toBe(1)
        ->and($xpath->query('//meta[@name="csrf-token"]')->length)->toBe(1)
        ->and($xpath->query('//title[contains(text(), "Untitled #Ab3xYz")]')->length)->toBe(1);
});

it('renders the code field, captcha box, session timer, button and status line', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[@id="visit"]//input[@id="visit-code"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-captcha-wrap"]//*[@id="visit-captcha"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-session"][@data-only="keyword"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//button[@id="visit-button"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-status"][@aria-live="polite"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit-title"][@data-title-wait and @data-title-keyword]')->length)->toBe(1);
});

it('renders a start again panel for sessions that cannot go on', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[@id="visit-stop"][@hidden]//a[@id="visit-stop-action"][contains(@href, "/Ab3xYz")]')->length)->toBe(1);
});

it('renders the keyword guide with six steps, the search helpers and a hidden video block', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[@data-only="keyword"][contains(@class, "lk-guide")]')->length)->toBe(1)
        ->and($xpath->query('//*[contains(@class, "lk-guide")]//p[contains(@class, "lk-step")]')->length)->toBe(6)
        ->and($xpath->query('//*[@id="visit-keyword"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit-domain"]')->length)->toBe(1)
        ->and($xpath->query('//a[@id="visit-google"][@target="_blank"][@rel="noopener noreferrer"]')->length)->toBe(1)
        ->and($xpath->query('//button[@id="visit-copy-keyword"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit-video"][@hidden]//iframe[@sandbox]')->length)->toBe(1);
});

it('offers a second code field and button at the end of the guide', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[contains(@class, "lk-guide")]//input[@id="visit-code-2"]')->length)->toBe(1)
        ->and($xpath->query('//*[contains(@class, "lk-guide")]//button[@id="visit-button-2"]')->length)->toBe(1);
});

it('renders the report dialog with three choices, a captcha box and close and send buttons', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[@id="visit-report"][@role="dialog"]//input[@type="radio"][@name="report-choice"]')->length)->toBe(3)
        ->and($xpath->query('//*[@id="visit-report"]//input[@value="code_not_shown" or @value="page_not_shown" or @value="other"]')->length)->toBe(3)
        ->and($xpath->query('//*[@id="visit-report"]//*[@id="visit-report-captcha"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit-report"]//button[@id="visit-report-send"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit-report"]//button[@id="visit-report-close"]')->length)->toBe(1);
});

it('renders the LinkCash green call to action band and footer', function () {
    $this->get('/go/Ab3xYz')
        ->assertOk()
        ->assertSee('Ready to earn money?')
        ->assertSee('Sign up for free')
        ->assertSee('Quick links')
        ->assertSee('Privacy Policy')
        ->assertSee('Developed by')
        ->assertSee(route('register'), false)
        ->assertSee(route('login'), false)
        ->assertSee(route('payout-rates'), false)
        ->assertSee(route('privacy'), false);
});

it('shows only English copy', function () {
    $html = $this->get('/go/Ab3xYz')->assertOk()->getContent();

    expect($html)->not->toMatch('/[ăâđêôơưĂÂĐÊÔƠƯ]|[àáảãạèéẻẽẹìíỉĩịòóỏõọùúủũụỳýỷỹỵ]/u')
        ->and(strtolower($html))->not->toContain('link4m');
});

it('ignores the preview switch outside the local environment', function () {
    expect(visitPreviewData('/go/Ab3xYz?preview=keyword'))->toBeNull()
        ->and(visitPreviewData('/go/Ab3xYz?preview=wait'))->toBeNull();
});

it('embeds sample keyword data when previewing locally', function () {
    app()->detectEnvironment(fn () => 'local');

    $preview = visitPreviewData('/go/Ab3xYz?preview=keyword&state=low&video=vimeo');

    expect($preview)->toBeArray()
        ->and($preview['mode'])->toBe('keyword')
        ->and($preview['preview_state'])->toBe('low')
        ->and($preview['session_seconds_left'])->toBeLessThan(60)
        ->and($preview['tutorial_video_url'])->toContain('vimeo.com')
        ->and($preview)->toHaveKeys(['keyword', 'display_domain', 'images', 'captcha_required']);
});

it('embeds sample wait data when previewing locally and falls back to the first state', function () {
    app()->detectEnvironment(fn () => 'local');

    $preview = visitPreviewData('/go/Ab3xYz?preview=wait&state=unknown');

    expect($preview)->toBeArray()
        ->and($preview['mode'])->toBe('wait')
        ->and($preview['preview_state'])->toBe('counting')
        ->and($preview)->toHaveKeys(['step', 'total_steps', 'dwell_seconds', 'token', 'captcha_required']);
});
