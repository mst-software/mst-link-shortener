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
 * Decode the local preview data embedded on the unlock station.
 *
 * @return array<string, mixed>|null
 */
function visitPreviewData(string $url): ?array
{
    $value = visitPageXPath($url)->query('//section[@id="visit"]/@data-preview')->item(0)?->nodeValue;

    return $value === null ? null : json_decode($value, true);
}

it('renders the unlock station with the dial, six code boxes, quick check, button and status line', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//section[@id="visit"][@data-mode="loading"]')->length)->toBe(1)
        ->and($xpath->query('//section[@id="visit"][@data-info-url and @data-finish-url and @data-start-url and @data-report-url]')->length)->toBe(1)
        ->and($xpath->query('//section[@id="visit"][@data-captcha-provider and @data-captcha-site-key and @data-captcha-script and @data-captcha-global]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-dial"]//*[@id="visit-ring"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-code"][@role="group"]//input[contains(@class, "code-cell")]')->length)->toBe(6)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-quick-check"]//*[@id="visit-captcha"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-session"][@data-mode-only="keyword"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//button[@id="visit-button"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[@id="visit-status"][@aria-live="polite"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit-title"][@data-title-wait and @data-title-keyword]')->length)->toBe(1);
});

it('renders a start again panel for sessions that cannot go on', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[@id="visit"]//*[@id="visit-stop"][@hidden]//a[@id="visit-stop-action"][contains(@href, "/Ab3xYz")]')->length)->toBe(1);
});

it('renders the keyword journey with four collapsible stages, three illustrations and a hidden video block', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//section[@id="keyword-guide"][@data-mode-only="keyword"][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="keyword-guide"]//li[contains(@class, "stage")]//button[@aria-expanded][@aria-controls]')->length)->toBe(4)
        ->and($xpath->query('//*[@id="keyword-guide"]//a[@data-google-search][@target="_blank"][@rel="noopener noreferrer"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="keyword-guide"]//details//figure[@data-shot="google" or @data-shot="button" or @data-shot="position"]')->length)->toBe(3)
        ->and($xpath->query('//*[@id="keyword-guide"]//*[@data-kw="keyword"]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@id="keyword-guide"]//*[@data-kw="domain"]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@id="keyword-video"][@hidden]//iframe[@sandbox]')->length)->toBe(1);
});

it('renders the wait mode step ads outside the unlock station', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//*[contains(@class, "step-ads")][@data-mode-only="wait"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="visit"]//*[contains(@class, "step-ads")]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="visit"]//button[@data-report-open]')->length)->toBe(1);
});

it('renders the report sheet with three choice cards, a captcha box and cancel and send buttons', function () {
    $xpath = visitPageXPath();

    expect($xpath->query('//dialog[@id="report-dialog"]//input[@type="radio"][@name="choice"]')->length)->toBe(3)
        ->and($xpath->query('//dialog[@id="report-dialog"]//input[@value="code_not_shown" or @value="page_not_shown" or @value="other"]')->length)->toBe(3)
        ->and($xpath->query('//dialog[@id="report-dialog"]//*[@id="report-captcha"]')->length)->toBe(1)
        ->and($xpath->query('//dialog[@id="report-dialog"]//button[@type="submit"]')->length)->toBe(1)
        ->and($xpath->query('//dialog[@id="report-dialog"]//button[@data-report-close]')->length)->toBe(2);
});

it('renders the earn strip and the footer links', function () {
    $this->get('/go/Ab3xYz')
        ->assertOk()
        ->assertSee('Ready to earn money?')
        ->assertSee('Earn from your own links')
        ->assertSee(route('register'), false)
        ->assertSee(route('login'), false)
        ->assertSee(route('payout-rates'), false);
});

it('shows only English copy and nothing borrowed from link4m', function () {
    $html = $this->get('/go/Ab3xYz')->assertOk()->getContent();

    expect($html)->not->toMatch('/[ăâđêôơưĂÂĐÊÔƠƯ]|[àáảãạèéẻẽẹìíỉĩịòóỏõọùúủũụỳýỷỹỵ]/u')
        ->and(strtolower($html))->not->toContain('link4m')
        ->and($html)->not->toContain('PLEASE ENTER THE CODE')
        ->and($html)->not->toContain('HOW TO GET THE CODE - KEYWORD SEARCH');
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
