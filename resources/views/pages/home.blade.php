<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fiper Terminal — Home</title>
@include('layouts.app-head')
<style>
  .hero{padding:64px 0;text-align:center;}
  .hero h1{margin:0 0 14px;font-size:32px;font-weight:800;}
  .hero p{margin:0 auto 28px;font-size:14.5px;color:var(--text-dim);max-width:52ch;}
  .hero-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
  .btn{display:inline-block;padding:11px 22px;border-radius:8px;font-size:13.5px;font-weight:700;text-decoration:none;}
  .btn-primary{background:var(--accent);color:#fff;}
  .btn-secondary{background:var(--surface);border:1px solid var(--border);color:var(--text);}
</style>
</head>
<body>
<div id="app" dir="ltr" lang="en">

  @include('layouts.app-topbar', ['activeNav' => 'home'])

  <section class="hero">
    <h1 data-i18n="hero_title">{{ $content['hero_title']['en'] ?? 'Fiper Terminal' }}</h1>
    <p data-i18n="hero_subtitle">{{ $content['hero_subtitle']['en'] ?? '' }}</p>
    <div class="hero-actions">
      <a href="/markets" class="btn btn-primary" data-i18n="cta_markets">Browse Markets</a>
      <a href="/heatmap" class="btn btn-secondary" data-i18n="cta_heatmap">View Heatmap</a>
    </div>
  </section>

</div>

<script>
(function(){
  "use strict";
  var i18n = {
    en: {
      brand_name:"Fiper Terminal", live_label:"LIVE",
      nav_home:"Home", nav_markets:"Markets", nav_heatmap:"Heatmap",
      hero_title: @json($content['hero_title']['en'] ?? 'Fiper Terminal'),
      hero_subtitle: @json($content['hero_subtitle']['en'] ?? ''),
      cta_markets:"Browse Markets", cta_heatmap:"View Heatmap"
    },
    ar: {
      brand_name:"فايبر تيرمينال", live_label:"مباشر",
      nav_home:"الرئيسية", nav_markets:"الأسواق", nav_heatmap:"الخريطة الحرارية",
      hero_title: @json($content['hero_title']['ar'] ?? 'فايبر تيرمينال'),
      hero_subtitle: @json($content['hero_subtitle']['ar'] ?? ''),
      cta_markets:"تصفح الأسواق", cta_heatmap:"عرض الخريطة الحرارية"
    }
  };
  var currentLang = "en";
  @include('partials.i18n')
  applyI18n();
})();
</script>
</body>
</html>
