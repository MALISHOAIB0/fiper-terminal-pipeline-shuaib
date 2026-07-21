<header class="topbar">
  <div class="brand"><span class="mark">F</span><span data-i18n="brand_name">Fiper Terminal</span></div>
  <nav class="main-nav">
    <a href="/" class="{{ ($activeNav ?? null) === 'home' ? 'is-active' : '' }}" data-i18n="nav_home">Home</a>
    <a href="/markets" class="{{ ($activeNav ?? null) === 'markets' ? 'is-active' : '' }}" data-i18n="nav_markets">Markets</a>
    <a href="/heatmap" class="{{ ($activeNav ?? null) === 'heatmap' ? 'is-active' : '' }}" data-i18n="nav_heatmap">Heatmap</a>
  </nav>
  <div class="topbar-actions">
    <div class="live-status">
      <span class="pulse-dot"></span>
      <span data-i18n="live_label">LIVE</span>
    </div>
    <div class="locale-toggle" role="group" aria-label="Language">
      <button id="btnEn" class="is-active" type="button">EN</button>
      <button id="btnAr" type="button">AR</button>
    </div>
  </div>
</header>
