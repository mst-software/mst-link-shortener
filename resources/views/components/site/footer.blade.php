<footer>
  <div class="footer-top">
    <div class="container">
      <div class="flex flex-wrap -mx-[15px]">
        <div class="w-full md:w-5/12 px-[15px] mb-8 footer-brand">
          <a href="{{ route('home') }}"><img src="{{ Vite::asset('resources/images/logo-poppins-white.png') }}" alt="LinkCash" class="h-[44px] w-auto"></a>
          <p>Shorten your links, share them anywhere and get paid for every valid view. Clear rates, fast payouts and a support team that answers.</p>
          <div class="pay-icons">
            <img src="{{ Vite::asset('resources/images/banktransfer.png') }}" alt="Bank transfer">
            <img src="{{ Vite::asset('resources/images/paypal.png') }}" alt="PayPal">
            <img src="{{ Vite::asset('resources/images/bitcoin.png') }}" alt="Bitcoin">
          </div>
        </div>
        <div class="w-1/2 md:w-3/12 px-[15px] mb-8">
          <h5>Quick links</h5>
          <ul class="footer-links">
            <li><a href="{{ route('home') }}">Home</a></li>
            <li><a href="{{ route('payout-rates') }}">Publisher Rates</a></li>
            <li><a href="{{ route('blog') }}">Blog</a></li>
            <li><a href="{{ route('login') }}">Sign In</a></li>
            <li><a href="{{ route('register') }}">Sign Up</a></li>
          </ul>
        </div>
        <div class="w-1/2 md:w-4/12 px-[15px] mb-8">
          <h5>Legal</h5>
          <ul class="footer-links">
            <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
            <li><a href="{{ route('terms') }}">Terms of Use</a></li>
            <li><a href="{{ route('payout-rates') }}#view-policy">View Counting Policy</a></li>
            <li><a href="{{ route('home') }}#contact">Contact Us</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="separator"><div class="container"></div></div>

  <div class="footer-bottom">
    <div class="container flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="copyright">Copyright &copy; LinkCash <span id="year">{{ now()->year }}</span>. All rights reserved.</div>
      <div class="dev-credit">Developed by <a href="https://www.facebook.com/mstsoftware.vn" target="_blank" rel="noopener">MST Software</a></div>
    </div>
  </div>
</footer>
