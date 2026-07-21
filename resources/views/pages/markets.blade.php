<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fiper Terminal — Markets</title>
@include('layouts.app-head')
<style>
  .page-hero{padding:22px 0 6px;}
  .page-hero h1{margin:0 0 6px;font-size:22px;font-weight:700;}
  .page-hero p{margin:0;font-size:13px;color:var(--text-dim);max-width:60ch;}
  .market-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:18px 0 16px;}
  .market-tabs button{padding:7px 14px;border-radius:100px;border:1px solid var(--border);background:var(--surface);color:var(--text-dim);font-size:12.5px;font-weight:600;cursor:pointer;}
  .market-tabs button.is-active{background:var(--surface-3);color:var(--text);}
  .market-table{width:100%;border-collapse:collapse;}
  .market-table th{text-align:start;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-faint);font-weight:700;padding:0 12px 10px;border-bottom:1px solid var(--border-soft);}
  .market-table td{padding:12px;border-bottom:1px solid var(--border-soft);font-size:13px;}
  .market-table tbody tr{cursor:pointer;}
  .market-table tbody tr:hover{background:var(--surface-2);}
  .market-table tbody tr.is-hidden{display:none;}
  .mkt-symbol{font-family:var(--font-mono);font-weight:700;}
  @media (max-width:640px){ .market-table th:nth-child(2), .market-table td:nth-child(2){display:none;} }
</style>
</head>
<body>
<div id="app" dir="ltr" lang="en">

  @include('layouts.app-topbar', ['activeNav' => 'markets'])

  <section class="page-hero">
    <h1 data-i18n="hero_title">{{ $content['page_title']['en'] ?? 'Markets' }}</h1>
    <p data-i18n="hero_subtitle">{{ $content['page_subtitle']['en'] ?? '' }}</p>
  </section>

  <div class="market-tabs" role="group" aria-label="Asset class filter">
    <button type="button" class="is-active" data-filter="all" data-i18n="tab_all">All</button>
    <button type="button" data-filter="forex" data-i18n="tab_forex">Forex</button>
    <button type="button" data-filter="crypto" data-i18n="tab_crypto">Crypto</button>
    <button type="button" data-filter="metals" data-i18n="tab_metals">Metals</button>
    <button type="button" data-filter="stocks" data-i18n="tab_stocks">Stocks</button>
    <button type="button" data-filter="indices" data-i18n="tab_indices">Indices</button>
    <button type="button" data-filter="commodities" data-i18n="tab_commodities">Commodities</button>
  </div>

  <div class="panel">
    <table class="market-table">
      <thead>
        <tr>
          <th data-i18n="col_symbol">Symbol</th>
          <th data-i18n="col_name">Name</th>
          <th data-i18n="col_price">Price</th>
          <th data-i18n="col_change">Change</th>
          <th data-i18n="col_bias">AI Bias</th>
        </tr>
      </thead>
      <tbody>
        @foreach($instruments as $instrument)
          @php
            $q = $instrument->latestQuote;
            $up = $q && $q->change >= 0;
            $bias = \App\Models\Instrument::biasMeta($instrument->ai_bias);
          @endphp
          <tr data-asset-class="{{ $instrument->asset_class }}" onclick="window.location.href='{{ route('instrument.show', $instrument->symbol) }}'">
            <td><span class="mkt-symbol">{{ $instrument->symbol }}</span></td>
            <td><span data-name-en="{{ $instrument->name }}" data-name-ar="{{ $instrument->name_localized ?? $instrument->name }}">{{ $instrument->name }}</span></td>
            <td><span class="num">{{ $q ? number_format($q->price, 2) : '—' }}</span></td>
            <td class="{{ $up ? 'change up' : 'change down' }}">
              <span class="num">
                @if($q)
                  {{ $up ? '+' : '' }}{{ number_format($q->change_percent, 2) }}%
                @else
                  —
                @endif
              </span>
            </td>
            <td><span class="badge {{ $bias['class'] }}" data-bias-en="{{ $bias['en'] }}" data-bias-ar="{{ $bias['ar'] }}">{{ $bias['en'] }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

</div>

<script>
(function(){
  "use strict";

  var i18n = {
    en: {
      brand_name:"Fiper Terminal", live_label:"LIVE",
      nav_home:"Home", nav_markets:"Markets", nav_heatmap:"Heatmap",
      hero_title: @json($content['page_title']['en'] ?? 'Markets'),
      hero_subtitle: @json($content['page_subtitle']['en'] ?? ''),
      tab_all:"All", tab_forex:"Forex", tab_crypto:"Crypto", tab_metals:"Metals",
      tab_stocks:"Stocks", tab_indices:"Indices", tab_commodities:"Commodities",
      col_symbol:"Symbol", col_name:"Name", col_price:"Price", col_change:"Change", col_bias:"AI Bias"
    },
    ar: {
      brand_name:"فايبر تيرمينال", live_label:"مباشر",
      nav_home:"الرئيسية", nav_markets:"الأسواق", nav_heatmap:"الخريطة الحرارية",
      hero_title: @json($content['page_title']['ar'] ?? 'الأسواق'),
      hero_subtitle: @json($content['page_subtitle']['ar'] ?? ''),
      tab_all:"الكل", tab_forex:"فوركس", tab_crypto:"عملات رقمية", tab_metals:"معادن",
      tab_stocks:"أسهم", tab_indices:"مؤشرات", tab_commodities:"سلع",
      col_symbol:"الرمز", col_name:"الاسم", col_price:"السعر", col_change:"التغير", col_bias:"توجه الذكاء الاصطناعي"
    }
  };

  var currentLang = "en";

  function onLangChange(){
    document.querySelectorAll("[data-name-en]").forEach(function(el){
      el.textContent = currentLang === "ar" ? el.getAttribute("data-name-ar") : el.getAttribute("data-name-en");
    });
    document.querySelectorAll("[data-bias-en]").forEach(function(el){
      el.textContent = currentLang === "ar" ? el.getAttribute("data-bias-ar") : el.getAttribute("data-bias-en");
    });
  }

  @include('partials.i18n')

  var tabButtons = document.querySelectorAll(".market-tabs button");
  var rows = document.querySelectorAll(".market-table tbody tr");
  tabButtons.forEach(function(btn){
    btn.addEventListener("click", function(){
      tabButtons.forEach(function(b){ b.classList.remove("is-active"); });
      btn.classList.add("is-active");
      var filter = btn.getAttribute("data-filter");
      rows.forEach(function(row){
        var show = filter === "all" || row.getAttribute("data-asset-class") === filter;
        row.classList.toggle("is-hidden", !show);
      });
    });
  });

  applyI18n();
})();
</script>
</body>
</html>
