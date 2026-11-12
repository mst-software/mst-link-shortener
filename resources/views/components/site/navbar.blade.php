<nav id="mainNav" class="fixed top-0 inset-x-0 z-50 font-roboto uppercase">
  <div class="container flex flex-wrap items-center justify-between">
    <a href="{{ route('home') }}" class="flex items-center h-[44px] sm:h-[52px] text-white" style="margin-left:-15px; padding-left:15px;">
      <img src="{{ Vite::asset('resources/images/logo-poppins-white.png') }}" alt="LinkCash" class="h-[38px] sm:h-[48px] w-auto">
    </a>

    <button id="navToggle" type="button" aria-label="Toggle navigation" class="sm:hidden bg-white rounded px-[10px] py-[9px] mr-[15px] my-1 border border-white">
      <span class="block w-[22px] h-[2px] bg-g rounded-sm"></span>
      <span class="block w-[22px] h-[2px] bg-g rounded-sm mt-1"></span>
      <span class="block w-[22px] h-[2px] bg-g rounded-sm mt-1"></span>
    </button>

    <ul id="navMenu" class="hidden sm:flex w-full sm:w-auto list-none m-0 p-0 flex-col sm:flex-row">
      <li><a class="nav-link" data-page="home" href="{{ route('home') }}">Home</a></li>
      <li><a class="nav-link" data-page="rates" href="{{ route('payout-rates') }}">Publisher Rates</a></li>
      <li><a class="nav-link" data-page="blog" href="{{ route('blog') }}">Blog</a></li>
      <li><a class="nav-link" data-page="login" href="{{ route('login') }}">Login</a></li>
      <li><a class="nav-link" data-page="signup" href="{{ route('register') }}">Sign Up</a></li>
    </ul>
  </div>
</nav>
