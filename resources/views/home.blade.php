@extends('layouts.app')

@section('page', 'home')

@section('content')
  <x-hero>
    <span class="hero-badge anim-up"><span class="hero-badge-dot" aria-hidden="true"></span>Free to join &middot; Paid for every valid view</span>
    <h1 class="intro-heading anim-up">
      <span class="intro-lead-in">Shorten URLs and</span>
      <span class="shimmer">earn money</span>
    </h1>
    <p class="intro-sub anim-up">Create short links, share them anywhere and get paid for every real visitor. Clear rates, payouts from just $5.</p>

    <div class="anim-up">
      <form id="shorten" class="shorten-form" novalidate>
        <label for="url" class="sr-only">Your long URL</label>
        <div class="shorten-field">
          <svg class="shorten-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
          <input type="text" id="url" class="url" placeholder="Paste a long URL here" autocomplete="off">
          <button type="submit" class="btn-shorten">
            <span>Shorten</span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
          </button>
        </div>
      </form>
      <div class="add-link-result" aria-live="polite"></div>

      <ul class="hero-trust">
        <li>$5 minimum payout</li>
        <li>Paid within 1&ndash;7 days</li>
        <li>Bank transfer, PayPal &amp; crypto</li>
      </ul>
    </div>
  </x-hero>

  <section class="steps">
    <div class="container text-center">
      <x-section-title subheading="It's just three steps" subheading-class="section-subheading-edit" heading="How You <b>Start</b>?" />

      @php
        $steps = [
          1 => ['Create an account', 'Sign up for free in less than a minute. No credit card needed.'],
          2 => ['Shorten your link', 'Paste any long URL and get a short link you can share anywhere.'],
          3 => ['Earn Money', 'Get paid for every valid view and withdraw as soon as you reach $5.'],
        ];
      @endphp

      <ol class="step-list">
        @foreach ($steps as $number => [$title, $content])
          <li class="step reveal">
            <span class="step-num">{{ $number }}</span>
            <div class="step-img"><i class="sp sp-step{{ $number }}"></i></div>
            <h3 class="step-heading">{{ $title }}</h3>
            <p class="step-content">{{ $content }}</p>
          </li>
        @endforeach
      </ol>
    </div>
  </section>

  <section class="features">
    <div class="container">
      <x-section-title class="text-center" subheading="Earn extra money" heading="Why join us?" />

      @php
        $features = [
          ['What is '.config('app.name').'?', config('app.name').' is a completely free tool where you can create short links, which apart from being free, you get paid! So, now you can make money from home, when managing and protecting your links.'],
          ['How and how much do I earn?', 'How can you start making money with '.config('app.name').'? It\'s just 3 steps: create an account, create a link and post it - for every visit, you earn money. It\'s just that easy!'],
          ['12% Referral Bonus', 'The '.config('app.name').' referral program is a great way to spread the word of this great service and to earn even more money with your short links! Refer friends and receive 12% of their earnings for life!'],
          ['Featured Administration Panel', 'Control all of the features from the administration panel with a click of a button.'],
          ['Detailed Stats', 'Know your audience. Analyse in detail what brings you the most income and what strategies you should adapt.'],
          ['Low Minimum Payout', 'You are required to earn only $5.0 before you will be paid. We can pay all users via bank transfer, PayPal and more.'],
          ['Highest Rates', 'Make the most out of your traffic with our always increasing rates.'],
          ['API', 'Shorten links more quickly with easy to use API and bring your creative and advanced ideas to life.'],
          ['Support', 'A dedicated support team is ready to help with any questions you may have.'],
        ];
      @endphp

      <div class="feature-grid">
        @foreach ($features as $index => [$title, $content])
          <div class="feature reveal">
            <div class="feature-img"><i class="sp sp-f{{ $index + 1 }}"></i></div>
            <h3 class="feature-heading">{{ $title }}</h3>
            <p class="feature-content">{{ $content }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section class="stats">
    <div class="container">
      <x-section-title class="text-center" subheading="Numbers speak for themselves" heading="Fast Growing" />
      <div class="stat-grid">
        @foreach ([['clicks', 68217828, 'Total Clicks'], ['links', 15768989, 'Total Links'], ['users', 79837, 'Registered users']] as [$icon, $target, $label])
          <div class="stat reveal">
            <div class="stat-img"><i class="sp sp-{{ $icon }}"></i></div>
            <div class="stat-num" data-target="{{ $target }}">0</div>
            <div class="stat-text">{{ $label }}</div>
          </div>
        @endforeach
      </div>

      <div class="payout-band reveal">
        <div class="payout-band-text">
          <p class="payout-band-eyebrow">Reliable payouts</p>
          <h2>Get paid your way, on time</h2>
          <ul class="payout-facts">
            <li><strong>$5</strong> minimum payout</li>
            <li><strong>1&ndash;7 days</strong> processing after approval</li>
            <li><strong>12%</strong> lifetime referral bonus</li>
          </ul>
          <div class="pay-icons">
            <img src="{{ Vite::asset('resources/images/banktransfer.png') }}" alt="Bank transfer">
            <img src="{{ Vite::asset('resources/images/paypal.png') }}" alt="PayPal">
            <img src="{{ Vite::asset('resources/images/bitcoin.png') }}" alt="Bitcoin">
          </div>
        </div>
        <div class="payout-band-actions">
          <a href="{{ route('register') }}" class="btn-cta btn-ripple">Create a free account</a>
          <a href="{{ route('payout-rates') }}" class="btn-ghost">See publisher rates</a>
        </div>
      </div>
    </div>
  </section>

  <section id="contact">
    <div class="container">
      <x-section-title class="text-center" subheading="Contact Us" heading="Get in touch!" />

      <form id="contact-form" class="contact-card reveal" novalidate>
        <div class="flex flex-wrap -mx-[12px]">
          <div class="w-full md:w-1/2 px-[12px]">
            <x-form-group name="name" label="Your Name *" />
            <x-form-group name="email" label="Your Email *" type="email" />
            <x-form-group name="subject" label="Your Subject *" />
          </div>
          <div class="w-full md:w-1/2 px-[12px]">
            <x-form-group name="message" label="Your Message *" textarea />
          </div>
        </div>

        <div class="form-group form-consent">
          <label for="accept"><input type="checkbox" name="accept" id="accept"><span>I consent to having this website store my submitted information so they can respond to my inquiry</span></label>
          <p class="text-danger" aria-live="polite"></p>
        </div>

        <div class="text-center">
          <div id="success" class="mb-3" aria-live="polite"></div>
          <button class="btn-contact btn-ripple" type="submit">Send Message</button>
        </div>
      </form>
    </div>
  </section>
@endsection

@push('scripts')
  @vite('resources/js/home.js')
@endpush
