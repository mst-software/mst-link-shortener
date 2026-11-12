@extends('layouts.app')

@section('title', 'Publisher Rates - LinkCash')
@section('description', 'See how much you earn for every 1,000 valid views on LinkCash.')
@section('page', 'rates')

@section('content')
  <x-page-header title="Publisher Rates" subtitle="Know exactly how much you earn. Rates are per 1,000 valid views and depend on the visitor's country and the level you choose." />

  @php
    $rates = [
      ['Vietnam', 4.50, 6.00, 7.50],
      ['United States', 12.00, 16.00, 20.00],
      ['United Kingdom', 10.00, 13.00, 16.00],
      ['Canada', 9.00, 12.00, 15.00],
      ['Australia', 9.00, 12.00, 14.00],
      ['Germany', 8.00, 11.00, 13.00],
      ['France', 7.00, 9.00, 11.00],
      ['Japan', 6.00, 8.00, 10.00],
      ['Thailand', 3.50, 4.50, 5.50],
      ['Philippines', 2.50, 3.50, 4.50],
      ['Indonesia', 3.00, 4.00, 5.00],
      ['India', 2.00, 3.00, 4.00],
      ['All other countries', 1.50, 2.00, 2.50],
    ];

    $policyRules = [
      ['Unique visitor', 'Each visitor counts for only one paid view every 24 hours, identified by their IP address and browser fingerprint together.', '24 hours'],
      ['Time on page', 'Measured on the server with a signed token, so it cannot be skipped by editing the page.', '&ge; 10 seconds'],
      ['JavaScript &amp; cookies', 'Both must be enabled in the visitor\'s browser.', 'Required'],
      ['All steps completed', 'Every step of the chosen level (1&ndash;3) is completed and the captcha on the last step is solved.', 'Required'],
      ['Real browser', 'Bot user agents, headless browsers and automated browsers are filtered out.', 'Always on'],
      ['Network quality', 'Datacenter IPs, VPNs and proxies are blocked using an automatically updated network (ASN) list.', 'Always on'],
      ['Country', 'Detected from the visitor\'s IP (Cloudflare country header, with a GeoIP database as fallback). That country\'s rate applies.', 'Automatic'],
      ['Destination reached', 'The visitor must end up on the destination link. Closing the tab early does not count.', 'Required'],
    ];
  @endphp

  <section class="page-body">
    <div class="container">
      <x-section-title subheading="Earn more from every visit" heading="Rates per 1,000 views" />
      <p class="lead-text reveal">Pick a level when you shorten a link. The higher the level, the more steps visitors go through and the more you are paid. The rates below are examples only &mdash; the official rates are set by the administrators and may change at any time.</p>

      <div class="flex flex-wrap -mx-[15px]">
        {{-- Rate table --}}
        <div class="w-full md:w-2/3 px-[15px] mb-8 reveal">
          <div class="mb-4">
            <label class="field-label" for="rateSearch">Find a country</label>
            <input id="rateSearch" type="text" class="field" placeholder="e.g. Vietnam, United States...">
          </div>
          <div class="overflow-x-auto">
            <table class="rate-table" id="rateTable">
              <thead><tr><th>Country</th><th>Level 1</th><th>Level 2</th><th>Level 3</th></tr></thead>
              <tbody>
                @foreach ($rates as [$country, $levelOne, $levelTwo, $levelThree])
                  <tr>
                    <td>{{ $country }}</td>
                    @foreach ([$levelOne, $levelTwo, $levelThree] as $rate)
                      <td class="num" data-v="{{ number_format($rate, 2) }}">${{ number_format($rate, 2) }}</td>
                    @endforeach
                  </tr>
                @endforeach
                <tr id="noRate" style="display:none"><td colspan="4" class="text-center">No country found.</td></tr>
              </tbody>
            </table>
          </div>
          <p class="mt-3 text-sm text-slate-500 font-roboto">Rates are in USD per 1,000 valid views. Level 1: 1 step &middot; Level 2: 2 steps &middot; Level 3: 3 steps.</p>
        </div>

        {{-- Earnings calculator --}}
        <div class="w-full md:w-1/3 px-[15px] mb-8 reveal">
          <x-panel title="Earnings calculator" class="md:sticky md:top-[90px]">
            <div class="mb-3">
              <label class="field-label" for="calcCountry">Visitor's country</label>
              <select id="calcCountry" class="field"></select>
            </div>
            <div class="mb-3">
              <label class="field-label" for="calcLevel">Level</label>
              <select id="calcLevel" class="field">
                <option value="0">Level 1</option><option value="1">Level 2</option><option value="2">Level 3</option>
              </select>
            </div>
            <div class="mb-1">
              <label class="field-label" for="calcViews">Valid views: <span id="viewsLabel">10,000</span></label>
              <input id="calcViews" type="range" class="slider" min="1000" max="1000000" step="1000" value="10000">
            </div>
            <div class="result-box">
              <div class="text-sm uppercase tracking-wider">You could earn</div>
              <div class="amount" id="calcResult">$0.00</div>
            </div>
          </x-panel>
        </div>
      </div>

      <div class="separator my-10"><div class="container"></div></div>

      {{-- View counting policy --}}
      <div id="view-policy" style="scroll-margin-top:90px">
        <x-section-title subheading="Fair to link creators and advertisers" heading="View Counting Policy" />
        <p class="lead-text reveal">You are only paid for <strong>valid views</strong>. A view is valid only when the visitor passes every check below. The values are defaults and the administrators may adjust them at any time.</p>

        <ul class="flow reveal">
          @foreach ([
            ['The visitor opens your short link', 'Unique within 24 hours'],
            ['Views the interstitial pages', 'Countdown, at least 10 seconds'],
            ['Solves the captcha', 'On the last step'],
            ['Reaches the destination', 'The link opens successfully'],
            ['The view is counted', 'Money is added to your account'],
          ] as [$step, $detail])
            <li>{{ $step }}<small>{{ $detail }}</small></li>
          @endforeach
        </ul>

        <div class="overflow-x-auto reveal">
          <table class="rate-table policy-table">
            <thead><tr><th>Rule</th><th>What we check</th><th>Default</th></tr></thead>
            <tbody>
              @foreach ($policyRules as [$rule, $check, $default])
                <tr><td>{!! $rule !!}</td><td>{!! $check !!}</td><td><span class="tag-pill">{!! $default !!}</span></td></tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="flex flex-wrap -mx-[15px] mt-8">
          <div class="w-full md:w-1/2 px-[15px] mb-6 reveal">
            <x-panel title="Views that are not paid" class="h-full">
              <x-check-list :items="[
                'Returning visitors within 24 hours',
                'Visitors who leave before the countdown ends or skip a step',
                'Bots, scripts, auto-refresh tools and automated browsers',
                'Traffic from VPNs, proxies or datacenter networks',
                'Views on links to adult (18+), gambling, scam or malware content',
                'Views flagged by the fraud filters (see below)',
              ]" />
            </x-panel>
          </div>
          <div class="w-full md:w-1/2 px-[15px] mb-6 reveal">
            <x-panel title="How earnings become available" class="h-full">
              <div class="status-row"><span class="status-dot" style="background:#f5a300"></span><div><strong>Pending</strong> &ndash; new earnings wait for a short review period (set by the administrators) so invalid traffic can be filtered out.</div></div>
              <div class="status-row"><span class="status-dot" style="background:#038018"></span><div><strong>Available</strong> &ndash; reviewed earnings that you can withdraw once you reach the minimum payout.</div></div>
              <div class="status-row"><span class="status-dot" style="background:#e74c3c"></span><div><strong>Cancelled</strong> &ndash; earnings from invalid traffic or from links that break the <a href="{{ route('terms') }}" style="color:#00aeef;text-decoration:underline">Terms of Use</a>.</div></div>
            </x-panel>
          </div>
        </div>

        <x-panel title="Fraud monitoring" class="reveal mb-2">
          <p style="font-family:Roboto,sans-serif;color:#444;line-height:1.7;margin:0">We monitor every account for unusual patterns, such as skewed view-to-click ratios, many views from a single IP address or IP range, or machine-like time on page. Accounts that trigger alerts may be reviewed, new traffic sources may need approval, and earnings from suspicious traffic may be cancelled. Reports from advertising networks can also lead to adjustments. If you believe a view was rejected by mistake, contact support with the short link and the date it happened.</p>
        </x-panel>
      </div>

      <div class="separator my-10"><div class="container"></div></div>

      <div class="flex flex-wrap -mx-[15px]">
        @foreach ([
          ['f3', '12% referral commission', 'Invite friends and earn 12% of their earnings for life, on top of your own income.'],
          ['f6', 'Low minimum payout', 'Request a payout as soon as you reach $5 via bank transfer, PayPal or USDT.'],
          ['f7', 'Always increasing rates', 'We review our rates regularly and raise them whenever advertisers pay more.'],
        ] as [$icon, $title, $content])
          <div class="w-full sm:w-1/3 px-[15px] reveal">
            <div class="info-card feature">
              <div class="feature-img"><i class="sp sp-{{ $icon }}"></i></div>
              <h4 class="feature-heading">{{ $title }}</h4>
              <p>{{ $content }}</p>
            </div>
          </div>
        @endforeach
      </div>

      <div class="flex flex-wrap -mx-[15px] mt-6">
        <div class="w-full md:w-1/2 px-[15px] mb-6 reveal">
          <x-panel title="Want the details?" class="h-full">
            <p style="font-family:Roboto,sans-serif;color:#444;line-height:1.7;margin:0 0 12px">A visit is paid only when it is a unique, complete view made by a real person. We explain every rule, its default value and why a view may not be counted.</p>
            <a href="{{ route('payout-rates') }}#view-policy" class="btn-cta btn-ripple" style="background:#038018;color:#fff">Read the View Counting Policy</a>
          </x-panel>
        </div>
        <div class="w-full md:w-1/2 px-[15px] mb-6 reveal">
          <x-panel title="Payouts" class="h-full">
            <x-check-list :items="[
              'Minimum payout: $5',
              'Methods: bank transfer, PayPal, USDT (BEP20)',
              'Processing time: 1–7 days after approval',
              'Earnings are held for a short review period to filter out invalid traffic',
              'Links to adult (18+), gambling or scam content are not paid',
            ]" />
          </x-panel>
        </div>
      </div>

      <div class="cta-band reveal mt-4">
        <h3>Ready to earn?</h3>
        <p>Create a free account and shorten your first link in less than a minute.</p>
        <a href="{{ route('register') }}" class="btn-cta btn-ripple">Sign up for free</a>
      </div>
    </div>
  </section>
@endsection

@push('scripts')
  @vite('resources/js/payout-rates.js')
@endpush
