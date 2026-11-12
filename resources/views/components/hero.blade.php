@props(['inner' => false])

<header {{ $attributes->class(['shorten', 'inner' => $inner]) }}>
  <div class="section-inner">
    <div class="container">
      <div id="particles-js">
        <div class="particles">
          <div class="intro-text">
            {{ $slot }}
          </div>
        </div>
        <canvas id="particles-canvas"></canvas>
      </div>
    </div>
  </div>
</header>
