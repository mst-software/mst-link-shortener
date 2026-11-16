<nav id="mainNav" class="fixed top-0 inset-x-0 z-50" aria-label="Main">
  <div class="container flex flex-wrap items-center justify-between">
    <a href="{{ route('home') }}" class="nav-brand flex items-center h-[48px] sm:h-[56px]">
      <img src="{{ \App\Services\Branding::imageUrl(\App\Enums\BrandAsset::LogoDark) }}" alt="{{ config('app.name') }}" class="h-[34px] sm:h-[42px] w-auto">
    </a>

    <button id="navToggle" type="button" aria-label="Toggle navigation" aria-controls="navMenu" aria-expanded="false"class="nav-toggle sm:hidden">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <ul id="navMenu" class="hidden sm:flex w-full sm:w-auto list-none m-0 p-0 flex-col sm:flex-row sm:items-center sm:gap-1">
      <li><a class="nav-link" data-page="home" href="{{ route('home') }}">Home</a></li>
      <li><a class="nav-link" data-page="rates" href="{{ route('payout-rates') }}">Publisher Rates</a></li>
      <li><a class="nav-link" data-page="blog" href="{{ route('blog') }}">Blog</a></li>
      <li><a class="nav-link" data-page="login" href="{{ route('login') }}">Login</a></li>
      <li class="sm:ml-2"><a class="nav-link nav-cta" data-page="signup" href="{{ route('register') }}">Sign Up</a></li>
    </ul>
  </div>
</nav>
