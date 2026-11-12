@extends('layouts.app')

@section('page', 'home')

@section('content')
  <x-hero>
    <div class="intro-lead-in anim-zoom">Shorten URLs and</div>
    <div class="intro-heading shimmer">earn money</div>
    <div class="anim-up">
      <div class="sm:mx-auto" style="max-width:100%">
        <form id="shorten" class="block sm:inline-block w-full sm:w-auto" novalidate>
          <div class="relative mb-[15px]">
            <input type="text" id="url" class="url" placeholder="Your URL Here" autocomplete="off">
            <button type="submit" aria-label="Shorten"><img src="{{ Vite::asset('resources/images/right-arrow.png') }}" alt=""></button>
          </div>
        </form>
        <div class="add-link-result"></div>
      </div>
    </div>
  </x-hero>

  <section class="steps">
    <div class="container text-center">
      <x-section-title subheading="It's just three steps" subheading-class="section-subheading-edit" heading="How You <b>Start</b>?" />

      <div class="flex flex-wrap -mx-[15px] reveal">
        @foreach ([1 => 'Create an account', 2 => 'Shorten your link', 3 => 'Earn Money'] as $number => $title)
          <div class="w-full sm:w-1/3 px-[15px] flex">
            <div class="step w-full">
              <div class="step-img"><i class="sp sp-step{{ $number }}"></i></div>
              <h4 class="step-heading">{{ $title }}</h4>
              <div class="step-content"></div>
              <div class="step-num"><span>{{ $number }}</span></div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <div class="separator"><div class="container"></div></div>

  <section class="features">
    <div class="container text-center">
      <x-section-title subheading="Earn extra money" heading="Why join us?" />

      @php
        $features = [
          ['What is LinkCash?', 'LinkCash is a completely free tool where you can create short links, which apart from being free, you get paid! So, now you can make money from home, when managing and protecting your links.'],
          ['How and how much do I earn?', 'How can you start making money with LinkCash? It\'s just 3 steps: create an account, create a link and post it - for every visit, you earn money. It\'s just that easy!'],
          ['12% Referral Bonus', 'The LinkCash referral program is a great way to spread the word of this great service and to earn even more money with your short links! Refer friends and receive 12% of their earnings for life!'],
          ['Featured Administration Panel', 'Control all of the features from the administration panel with a click of a button.'],
          ['Detailed Stats', 'Know your audience. Analyse in detail what brings you the most income and what strategies you should adapt.'],
          ['Low Minimum Payout', 'You are required to earn only $5.0 before you will be paid. We can pay all users via bank transfer, PayPal and more.'],
          ['Highest Rates', 'Make the most out of your traffic with our always increasing rates.'],
          ['API', 'Shorten links more quickly with easy to use API and bring your creative and advanced ideas to life.'],
          ['Support', 'A dedicated support team is ready to help with any questions you may have.'],
        ];
      @endphp

      <div class="flex flex-wrap -mx-[15px]">
        @foreach ($features as $index => [$title, $content])
          <div class="w-full sm:w-1/3 px-[15px] reveal">
            <div @class(['feature', 'last' => $index >= 6])>
              <div class="feature-img"><i class="sp sp-f{{ $index + 1 }}"></i></div>
              <h4 class="feature-heading">{{ $title }}</h4>
              <div class="feature-content">{{ $content }}</div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section class="stats">
    <div class="container">
      <x-section-title class="text-center" subheading="Numbers speak for themselves" heading="Fast Growing" />
      <div class="flex flex-wrap -mx-[15px]">
        @foreach ([['clicks', 68217828, 'Total Clicks'], ['links', 15768989, 'Total Links'], ['users', 79837, 'Registered users']] as [$icon, $target, $label])
          <div class="w-full sm:w-1/3 px-[15px] text-center">
            <div class="stat reveal">
              <div class="stat-img"><i class="sp sp-{{ $icon }}"></i></div>
              <div class="stat-num" data-target="{{ $target }}">0</div>
              <div class="stat-text">{{ $label }}</div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <div class="separator"><div class="container"></div></div>

  <section id="contact">
    <div class="container">
      <x-section-title class="text-center" subheading="Contact Us" heading="Get in touch!" />

      <form id="contact-form" novalidate>
        <div class="flex flex-wrap -mx-[15px]">
          <div class="w-full md:w-1/2 px-[15px]">
            <x-form-group name="name" label="Your Name *" />
            <x-form-group name="email" label="Your Email *" />
            <x-form-group name="subject" label="Your Subject *" />
          </div>
          <div class="w-full md:w-1/2 px-[15px]">
            <x-form-group name="message" label="Your Message *" textarea />
          </div>
        </div>

        <div class="form-group">
          <label for="accept" class="cursor-pointer"><input type="checkbox" name="accept" id="accept"><b> I consent to having this website store my submitted information so they can respond to my inquiry</b></label>
          <p class="text-danger"></p>
        </div>

        <div class="text-center">
          <div id="success" class="mb-3"></div>
          <button class="btn-contact btn-ripple" type="submit">Send Message</button>
        </div>
      </form>
    </div>
  </section>
@endsection

@push('scripts')
  @vite('resources/js/home.js')
@endpush
