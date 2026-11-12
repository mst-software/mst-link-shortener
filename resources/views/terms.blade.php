@extends('layouts.app')

@section('title', 'Terms of Use - LinkCash')
@section('description', 'Please read these terms before using LinkCash.')
@section('page', 'terms')

@section('content')
  <x-legal-document title="Terms of Use" subtitle="Please read these terms before using LinkCash." updated="November 12, 2026">
    <h3 id="accept">1. Acceptance of Terms</h3>
    <p>By creating an account or using LinkCash, you agree to these Terms of Use and our <a href="{{ route('privacy') }}">Privacy Policy</a>. If you do not agree, please do not use the service. You must be at least 18 years old.</p>

    <h3 id="accounts">2. Your Account</h3>
    <ul>
      <li>Provide accurate information and keep your password confidential.</li>
      <li>Each person may only have one account. Duplicate or fake accounts may be closed.</li>
      <li>You are responsible for all activity that takes place in your account.</li>
    </ul>

    <h3 id="service">3. The Service</h3>
    <p>LinkCash lets you shorten URLs and earn money when visitors view your shortened links. We may change, suspend or discontinue any part of the service at any time, including features, rates and limits.</p>

    <h3 id="content">4. Prohibited Content</h3>
    <p>You may not shorten or promote links that lead to content that is:</p>
    <ul>
      <li>adult, pornographic or sexually offensive;</li>
      <li>related to gambling, betting or illegal drugs;</li>
      <li>fraudulent, phishing or used to distribute malware;</li>
      <li>infringing on copyrights, trademarks or other rights;</li>
      <li>promoting violence or hatred, or otherwise unlawful.</li>
    </ul>
    <p>We scan links and may remove links, suspend accounts and cancel related earnings without prior notice.</p>

    <h3 id="traffic">5. Traffic and Prohibited Conduct</h3>
    <ul>
      <li>Do not generate views using bots, scripts, proxies, VPNs, auto-refresh tools or any other automated means.</li>
      <li>Do not click or view your own links, or encourage or force users to do so in a misleading way.</li>
      <li>Do not attempt to disrupt, scrape, reverse engineer or attack the service.</li>
      <li>A view is only counted when it is unique, stays on the page long enough, has JavaScript and cookies enabled, and reaches the destination page. We decide which views are valid in good faith. See the <a href="{{ route('payout-rates') }}#view-policy">View Counting Policy</a> for the full rules.</li>
    </ul>

    <h3 id="payments">6. Earnings and Payments</h3>
    <ul>
      <li>Earnings are shown in USD and credited according to the <a href="{{ route('payout-rates') }}">current payout rates</a>, which may change at any time.</li>
      <li>Withdrawals require reaching the minimum payout threshold and are processed after a review period.</li>
      <li>We may withhold or cancel earnings that come from invalid traffic, policy violations or advertiser chargebacks.</li>
      <li>You are responsible for any taxes that apply to your earnings.</li>
    </ul>

    <h3 id="referral">7. Referral Program</h3>
    <p>You can earn a percentage of the earnings of members you refer. Self-referrals, fake accounts and spam promotion are not allowed; commissions earned this way may be revoked.</p>

    <h3 id="ip">8. Intellectual Property and Reporting Abuse</h3>
    <p>The service, its design and its software belong to us or our licensors. You keep ownership of the content behind your links. To report a link that violates these terms or your rights, use our <a href="{{ route('home') }}#contact">contact form</a> and include the shortened link and a description; we will review it promptly.</p>

    <h3 id="termination">9. Suspension and Termination</h3>
    <p>We may suspend or terminate your account at any time, with or without notice, if you violate these terms or we suspect fraud or abuse. You may close your account at any time by contacting support.</p>

    <h3 id="disclaimer">10. Disclaimer of Warranties</h3>
    <p>The service is provided "as is" and "as available", without warranties of any kind. We do not guarantee that the service will be uninterrupted, or any level of traffic or earnings.</p>

    <h3 id="liability">11. Limitation of Liability</h3>
    <p>To the maximum extent permitted by law, we are not liable for any indirect, incidental or consequential damages, or for any loss of profits, data or goodwill arising from your use of the service. Our total liability will not exceed the amount you earned in the three months before the claim.</p>

    <h3 id="changes">12. Changes to These Terms</h3>
    <p>We may update these terms from time to time. Continuing to use the service after the changes take effect means you accept the new terms.</p>

    <h3 id="contact">13. Contact Us</h3>
    <p>Questions about these terms? Email us at <a href="mailto:support@yourdomain.com">support@yourdomain.com</a>.</p>
  </x-legal-document>
@endsection
