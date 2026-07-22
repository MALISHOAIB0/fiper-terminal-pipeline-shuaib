@php
    $groupLabels = [
        'forex' => ['en' => 'Forex', 'ar' => 'فوركس'],
        'crypto' => ['en' => 'Crypto', 'ar' => 'عملات رقمية'],
        'metals' => ['en' => 'Metals', 'ar' => 'معادن'],
        'stocks' => ['en' => 'Stocks', 'ar' => 'أسهم'],
        'indices' => ['en' => 'Indices', 'ar' => 'مؤشرات'],
        'commodities' => ['en' => 'Commodities', 'ar' => 'سلع'],
    ];
    $groupOrder = ['forex', 'crypto', 'metals', 'stocks', 'indices', 'commodities'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fiper Terminal — Heatmap</title>
@include('layouts.app-head')
<style>
  .page-hero{padding:22px 0 6px;}
  .page-hero h1{margin:0 0 6px;font-size:22px;font-weight:700;}
  .page-hero p{margin:0 0 24px;font-size:13px;color:var(--text-dim);max-width:60ch;}
  .heatmap-group{margin-bottom:24px;}
  .heatmap-group h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-dim);font-weight:700;margin:0 0 10px;}
  .heatmap-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;}
  .heatmap-tile{display:block;border-radius:8px;padding:10px;border:1px solid var(--border-soft);text-decoration:none;color:var(--text);}
  .heatmap-tile .tile-symbol{font-family:var(--font-mono);font-weight:700;font-size:12.5px;}
  .heatmap-tile .tile-change{margin-top:4px;font-size:12px;font-weight:700;}
</style>
</head>
<body>
<div id="app" dir="ltr" lang="en">

  @include('layouts.app-topbar', ['activeNav' => 'heatmap'])

  <section class="page-hero">
    <h1 data-i18n="hero_title">{{ $content['page_title']['en'] ?? 'Heatmap' }}</h1>
    <p data-i18n="hero_subtitle">{{ $content['page_subtitle']['en'] ?? '' }}</p>
  </section>

  @foreach($groupOrder as $class)
    @continue(!isset($grouped[$class]))
    <section class="heatmap-group">
      <h2 data-name-en="{{ $groupLabels[$class]['en'] }}" data-name-ar="{{ $groupLabels[$class]['ar'] }}">{{ $groupLabels[$class]['en'] }}</h2>
      <div class="heatmap-grid">
        @foreach($grouped[$class] as $instrument)
          @php
            $q = $instrument->latestQuote;
            $pct = $q ? (float) $q->change_percent : 0.0;
            $capped = max(-3, min(3, $pct));
            $intensity = round(abs($capped) / 3, 2);
            $tileColor = $pct >= 0 ? "rgba(47,190,143,{$intensity})" : "rgba(244,40,33,{$intensity})";
          @endphp
          <a href="{{ route('instrument.show', $instrument->symbol) }}" class="heatmap-tile" data-symbol="{{ $instrument->symbol }}" style="background:{{ $tileColor }};">
            <div class="tile-symbol">{{ $instrument->symbol }}</div>
            <div class="tile-change num">{{ $q ? ($pct >= 0 ? '+' : '').number_format($pct, 2).'%' : '—' }}</div>
          </a>
        @endforeach
      </div>
    </section>
  @endforeach

</div>

<script>
(function(){
  "use strict";

  var i18n = {
    en: {
      brand_name:"Fiper Terminal", live_label:"LIVE",
      nav_home:"Home", nav_markets:"Markets", nav_heatmap:"Heatmap",
      hero_title: @json($content['page_title']['en'] ?? 'Heatmap'),
      hero_subtitle: @json($content['page_subtitle']['en'] ?? '')
    },
    ar: {
      brand_name:"فايبر تيرمينال", live_label:"مباشر",
      nav_home:"الرئيسية", nav_markets:"الأسواق", nav_heatmap:"الخريطة الحرارية",
      hero_title: @json($content['page_title']['ar'] ?? 'الخريطة الحرارية'),
      hero_subtitle: @json($content['page_subtitle']['ar'] ?? '')
    }
  };

  var currentLang = "en";

  function onLangChange(){
    document.querySelectorAll("[data-name-en]").forEach(function(el){
      el.textContent = currentLang === "ar" ? el.getAttribute("data-name-ar") : el.getAttribute("data-name-en");
    });
  }

  @include('partials.i18n')

  if (window.Echo) {
    window.Echo.channel("quotes").listen(".quote.updated", function (e) {
      var tile = document.querySelector('.heatmap-tile[data-symbol="' + e.symbol + '"]');
      if (!tile) return;
      var pct = e.change_percent;
      var capped = Math.max(-3, Math.min(3, pct));
      var intensity = Math.round((Math.abs(capped) / 3) * 100) / 100;
      tile.style.background = pct >= 0
        ? "rgba(47,190,143," + intensity + ")"
        : "rgba(244,40,33," + intensity + ")";
      var changeEl = tile.querySelector(".tile-change");
      changeEl.textContent = (pct >= 0 ? "+" : "") + Number(pct).toFixed(2) + "%";
    });
  }

  applyI18n();
})();
</script>
</body>
</html>
