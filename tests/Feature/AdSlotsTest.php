<?php

use App\Enums\AdDevice;
use App\Enums\AdPlacement;
use App\Models\AdSlot;
use App\Models\Link;
use App\Models\User;
use App\Services\AdSlotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

const AD_DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36';
const AD_MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

/**
 * Render the ad slot component for a placement as a visitor with the given user agent.
 */
function renderAdSlot(AdPlacement $placement, string $userAgent = AD_DESKTOP_UA): string
{
    request()->headers->set('User-Agent', $userAgent);

    return Blade::render('<x-visit.ad-slot :placement="$placement" />', ['placement' => $placement]);
}

test('an active slot shows at its own placement and nowhere else', function () {
    AdSlot::factory()->forPlacement(AdPlacement::Step2)->create(['code' => '<b>STEP-TWO-AD</b>']);

    expect(renderAdSlot(AdPlacement::Step2))->toContain('<b>STEP-TWO-AD</b>')
        ->and(renderAdSlot(AdPlacement::Step1))->not->toContain('STEP-TWO-AD')
        ->and(renderAdSlot(AdPlacement::Top))->not->toContain('STEP-TWO-AD');
});

test('an inactive slot is not shown', function () {
    AdSlot::factory()->inactive()->create(['code' => '<b>OFF-AD</b>']);

    expect(renderAdSlot(AdPlacement::Top))->not->toContain('OFF-AD');
});

test('a mobile slot is hidden from desktop user agents and shown to mobile ones', function () {
    AdSlot::factory()->forDevice(AdDevice::Mobile)->create(['code' => '<b>MOBILE-AD</b>']);

    expect(renderAdSlot(AdPlacement::Top, AD_DESKTOP_UA))->not->toContain('MOBILE-AD')
        ->and(renderAdSlot(AdPlacement::Top, AD_MOBILE_UA))->toContain('MOBILE-AD');
});

test('slots are ordered by sort order', function () {
    AdSlot::factory()->create(['code' => 'SECOND-AD', 'sort_order' => 2]);
    AdSlot::factory()->create(['code' => 'FIRST-AD', 'sort_order' => 1]);

    $html = renderAdSlot(AdPlacement::Top);

    expect(strpos($html, 'FIRST-AD'))->toBeLessThan(strpos($html, 'SECOND-AD'));
});

test('the visit page keeps the continue button outside every ad container', function () {
    Link::factory()->create(['alias' => 'Ab3xYz']);
    AdSlot::factory()->forPlacement(AdPlacement::Step1)->create(['code' => '<b>STEP-ONE-AD</b>']);
    AdSlot::factory()->forPlacement(AdPlacement::Top)->create(['code' => '<b>TOP-AD</b>']);
    AdSlot::factory()->forPlacement(AdPlacement::Bottom)->create(['code' => '<b>BOTTOM-AD</b>']);

    $html = $this->withHeader('User-Agent', AD_DESKTOP_UA)->get('/go/Ab3xYz')
        ->assertOk()
        ->assertSee('TOP-AD', false)
        ->assertSee('STEP-ONE-AD', false)
        ->assertSee('BOTTOM-AD', false)
        ->getContent();

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@id="visit-button"]')->length)->toBe(1)
        ->and($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ad-container ")]//*[@id="visit-button"]')->length)->toBe(0)
        ->and($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ad-container ")]')->length)->toBe(3);
});

test('saving a slot clears the cached placement list', function () {
    $repository = app(AdSlotRepository::class);
    $repository->forPlacement(AdPlacement::Top, AdDevice::Desktop);

    expect(Cache::has('ad_slots.top.desktop'))->toBeTrue();

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.ad-slots.store'), [
        'name' => 'New banner',
        'placement' => 'top',
        'device' => 'all',
        'code' => '<b>NEW-AD</b>',
        'is_active' => 1,
        'sort_order' => 0,
    ])->assertRedirect(route('admin.ad-slots.index'));

    expect(Cache::has('ad_slots.top.desktop'))->toBeFalse()
        ->and($repository->forPlacement(AdPlacement::Top, AdDevice::Desktop))->toHaveCount(1);
});

test('admins can create, update and delete ad slots', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.ad-slots.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.ad-slots.create'))->assertOk()->assertSee('without escaping');

    $slot = AdSlot::factory()->create(['name' => 'Old name']);

    $this->actingAs($admin)->put(route('admin.ad-slots.update', $slot), [
        'name' => 'New name',
        'placement' => 'bottom',
        'device' => 'mobile',
        'code' => '<b>X</b>',
        'sort_order' => 3,
    ])->assertRedirect(route('admin.ad-slots.index'));

    $slot->refresh();

    expect($slot->name)->toBe('New name')
        ->and($slot->placement)->toBe(AdPlacement::Bottom)
        ->and($slot->device)->toBe(AdDevice::Mobile)
        ->and($slot->is_active)->toBeFalse();

    $this->actingAs($admin)->delete(route('admin.ad-slots.destroy', $slot))->assertRedirect(route('admin.ad-slots.index'));

    expect(AdSlot::query()->count())->toBe(0);
});

test('invalid placements are rejected', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.ad-slots.store'), ['name' => 'X', 'placement' => 'nope', 'device' => 'all', 'code' => 'x'])
        ->assertSessionHasErrors('placement');
});

test('members get a 403 on the ad slot routes', function () {
    $member = User::factory()->create();
    $slot = AdSlot::factory()->create();

    $this->actingAs($member)->get(route('admin.ad-slots.index'))->assertForbidden();
    $this->actingAs($member)->post(route('admin.ad-slots.store'), [])->assertForbidden();
    $this->actingAs($member)->delete(route('admin.ad-slots.destroy', $slot))->assertForbidden();
});
