@php
    $bias = \App\Models\Instrument::biasMeta($instrument->ai_bias);
    $changeUp = $quote && $quote->change >= 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fiper Terminal — {{ $instrument->symbol }}</title>
@include('layouts.app-head')
<style>
  .instrument-header{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px;padding:18px 0 20px;border-bottom:1px solid var(--border-soft);margin-bottom:22px;}
  .instrument-id{display:flex;align-items:center;gap:14px;}
  .instrument-icon{width:44px;height:44px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;color:var(--gold);}
  .instrument-titles h1{margin:0;font-size:19px;font-weight:700;display:flex;align-items:center;gap:10px;}
  .instrument-titles .symbol{direction:ltr;unicode-bidi:isolate;font-family:var(--font-mono);font-size:12.5px;color:var(--text-dim);font-weight:600;background:var(--surface-2);border:1px solid var(--border);padding:2px 8px;border-radius:6px;}
  .instrument-meta{margin-top:4px;font-size:12.5px;color:var(--text-dim);display:flex;gap:10px;flex-wrap:wrap;}
  .price-block{text-align:end;}
  .price-block .price{font-size:26px;font-weight:700;}
  .price-block .change{margin-top:2px;font-size:13.5px;font-weight:700;}
  .price-block .updated{margin-top:4px;font-size:11.5px;color:var(--text-faint);}
  .main-grid{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;}
  @media (max-width:860px){.main-grid{grid-template-columns:1fr;}}
  .period-tabs{display:flex;gap:4px;}
  .period-tabs button{padding:5px 10px;border-radius:6px;border:1px solid transparent;background:transparent;color:var(--text-faint);font-size:12px;font-weight:600;cursor:pointer;font-family:var(--font-mono);}
  .period-tabs button:hover{color:var(--text-dim);}
  .period-tabs button.is-active{background:var(--surface-3);border-color:var(--border);color:var(--text);}
  .chart-wrap{position:relative;width:100%;}
  .chart-svg{width:100%;height:auto;display:block;}
  .chart-tooltip{position:absolute;pointer-events:none;background:var(--surface-3);border:1px solid var(--border);border-radius:8px;padding:8px 10px;font-size:11.5px;font-family:var(--font-mono);color:var(--text);white-space:nowrap;opacity:0;transition:opacity .1s;z-index:5;box-shadow:0 8px 20px rgba(0,0,0,.4);}
  .chart-tooltip.visible{opacity:1;}
  .chart-tooltip .tt-row{display:flex;gap:8px;justify-content:space-between;}
  .chart-tooltip .tt-label{color:var(--text-faint);}
  .chart-legend{display:flex;gap:16px;margin-top:10px;font-size:11.5px;color:var(--text-faint);}
  .chart-legend span{display:flex;align-items:center;gap:5px;}
  .legend-swatch{width:9px;height:9px;border-radius:2px;}
  .brief-title{font-size:15px;font-weight:700;margin:0 0 10px;}
  .brief-summary{font-size:13px;color:var(--text-dim);margin:0 0 16px;max-width:65ch;}
  .brief-section{margin-bottom:16px;} .brief-section:last-child{margin-bottom:0;}
  .brief-section h3{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-faint);margin:0 0 8px;font-weight:700;}
  .levels-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
  .level-item{background:var(--surface-2);border:1px solid var(--border-soft);border-radius:8px;padding:8px 10px;}
  .level-item .lvl-label{font-size:10.5px;color:var(--text-faint);}
  .level-item .lvl-value{font-size:13px;font-weight:700;margin-top:2px;}
  .level-item.res .lvl-value{color:var(--bear);} .level-item.sup .lvl-value{color:var(--bull);}
  .brief-section p{font-size:12.5px;color:var(--text-dim);margin:0;}
  .indicator-row{display:flex;gap:14px;font-size:12.5px;color:var(--text-dim);flex-wrap:wrap;}
  .indicator-row .num{color:var(--text);font-weight:600;}
  .disclaimer-inline{margin-top:16px;padding-top:12px;border-top:1px solid var(--border-soft);font-size:10.5px;color:var(--text-faint);}
  .corr-section{margin-top:20px;} .corr-list{display:flex;flex-direction:column;gap:10px;}
  .corr-row{display:grid;grid-template-columns:90px 1fr 60px;align-items:center;gap:12px;}
  .corr-symbol{font-family:var(--font-mono);font-size:12.5px;font-weight:700;color:var(--text);}
  .corr-bar-track{position:relative;height:6px;background:var(--surface-2);border-radius:100px;overflow:hidden;}
  .corr-bar-fill{position:absolute;top:0;bottom:0;border-radius:100px;}
  .corr-value{font-size:12.5px;text-align:end;font-weight:700;}
  .news-section{margin-top:20px;} .news-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;}
  @media (max-width:640px){.news-grid{grid-template-columns:1fr;}}
  .news-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px;}
  .news-meta{display:flex;justify-content:space-between;font-size:10.5px;color:var(--text-faint);margin-bottom:8px;direction:ltr;unicode-bidi:isolate;}
  .news-headline{font-size:13px;font-weight:600;margin:0;}
  .foot-disclaimer{margin-top:28px;padding:14px 16px;background:var(--surface-2);border:1px solid var(--border-soft);border-radius:var(--radius);font-size:11.5px;color:var(--text-faint);text-align:center;}
  #app[dir="rtl"] .instrument-titles h1{flex-direction:row-reverse;}
</style>
</head>
<body>
<div id="app" dir="ltr" lang="en">

  @include('layouts.app-topbar')

  <div class="breadcrumb">
    <span data-i18n="crumb_markets">Markets</span><span>/</span>
    <span>{{ $instrument->sector ?? $instrument->asset_class }}</span><span>/</span>
    <span id="crumbCurrent">{{ $instrument->name }}</span>
  </div>

  <section class="instrument-header">
    <div class="instrument-id">
      <div class="instrument-icon">{{ $instrument->icon_letter }}</div>
      <div class="instrument-titles">
        <h1>
          <span id="instrumentName">{{ $instrument->name }}</span>
          <span class="symbol">{{ $instrument->symbol }}</span>
        </h1>
        <div class="instrument-meta">
          @if($instrument->shariah_status === 'compliant')
            <span class="badge badge-gold">✓ <span data-i18n="badge_sharia">Sharia Compliant</span></span>
          @endif
          <span>{{ $instrument->sector ?? ucfirst($instrument->asset_class) }}</span>
          @if($instrument->country)
            <span>·</span><span>{{ $instrument->country }}</span>
          @endif
        </div>
      </div>
    </div>
    <div class="price-block">
      @if($quote)
        <div class="price num" id="instrumentPrice">{{ number_format($quote->price, 2) }}</div>
        <div class="change {{ $changeUp ? 'up' : 'down' }} num" id="instrumentChange">{{ $changeUp ? '+' : '' }}{{ number_format($quote->change, 2) }} ({{ $changeUp ? '+' : '' }}{{ number_format($quote->change_percent, 2) }}%)</div>
        <div class="updated"><span data-i18n="updated_label">Updated</span> <span id="instrumentUpdatedTime">{{ $quote->quoted_at->diffForHumans() }}</span></div>
      @else
        <p class="empty-note">No quote yet — run quotes:refresh.</p>
      @endif
    </div>
  </section>

  <div class="main-grid">
    <div class="panel">
      <div class="panel-title">
        <h2 data-i18n="chart_title">Price Chart</h2>
        <div class="period-tabs">
          <button data-period="7" type="button">1W</button>
          <button data-period="30" class="is-active" type="button">1M</button>
          <button data-period="90" type="button">3M</button>
        </div>
      </div>
      <div class="chart-wrap">
        <svg class="chart-svg" id="chartSvg" viewBox="0 0 720 320" preserveAspectRatio="none"></svg>
        <div class="chart-tooltip" id="chartTooltip"></div>
      </div>
      <div class="chart-legend">
        <span><span class="legend-swatch" style="background:var(--bull);"></span><span data-i18n="legend_up">Close ≥ Open</span></span>
        <span><span class="legend-swatch" style="background:var(--bear);"></span><span data-i18n="legend_down">Close &lt; Open</span></span>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">
        <h2 data-i18n="brief_panel_title">AI Brief</h2>
        <span class="badge {{ $bias['class'] }}" id="biasBadge">{{ $bias['en'] }}</span>
      </div>

      @if($instrument->ai_brief_en)
        <p class="brief-title" id="briefTitle">{{ $instrument->ai_brief_en['title'] }}</p>
        <p class="brief-summary" id="briefSummary">{{ $instrument->ai_brief_en['summary'] }}</p>

        <div class="brief-section">
          <h3 data-i18n="levels_heading">Key Levels</h3>
          <div class="levels-grid" id="levelsGrid">
            <div class="level-item res"><div class="lvl-label" data-i18n="resistance_label">Resistance</div><div class="lvl-value num">{{ $instrument->ai_brief_en['key_levels']['resistance'][1] ?? '—' }}</div></div>
            <div class="level-item res"><div class="lvl-label" data-i18n="resistance_label">Resistance</div><div class="lvl-value num">{{ $instrument->ai_brief_en['key_levels']['resistance'][0] ?? '—' }}</div></div>
            <div class="level-item sup"><div class="lvl-label" data-i18n="support_label">Support</div><div class="lvl-value num">{{ $instrument->ai_brief_en['key_levels']['support'][0] ?? '—' }}</div></div>
            <div class="level-item sup"><div class="lvl-label" data-i18n="support_label">Support</div><div class="lvl-value num">{{ $instrument->ai_brief_en['key_levels']['support'][1] ?? '—' }}</div></div>
          </div>
        </div>

        @if(!empty($instrument->ai_brief_en['indicators']) && $instrument->ai_brief_en['indicators']['rsi_14'] !== null)
          <div class="brief-section">
            <h3 data-i18n="indicators_heading">Technical Indicators</h3>
            <div class="indicator-row">
              <span>RSI(14): <span class="num">{{ $instrument->ai_brief_en['indicators']['rsi_14'] }}</span></span>
              <span>MACD: <span class="num">{{ $instrument->ai_brief_en['indicators']['macd'] }}</span></span>
              <span><span data-i18n="macd_signal_label">Signal:</span> <span class="num">{{ $instrument->ai_brief_en['indicators']['macd_signal'] }}</span></span>
            </div>
          </div>
        @endif

        @if(!empty($instrument->ai_brief_en['forecast']))
          <div class="brief-section">
            <h3 data-i18n="forecast_heading">Quantitative Forecast</h3>
            <p id="forecastText">
              {{ $instrument->ai_brief_en['forecast']['horizon_days'] }}d range:
              <span class="num">{{ $instrument->ai_brief_en['forecast']['expected_low'] }}–{{ $instrument->ai_brief_en['forecast']['expected_high'] }}</span>,
              {{ round($instrument->ai_brief_en['forecast']['upside_probability'] * 100) }}% upside probability
              ({{ $instrument->ai_brief_en['forecast']['sample_count'] }}-path simulation — placeholder model, see Methodology)
            </p>
          </div>
        @endif

        <div class="brief-section">
          <h3 data-i18n="catalysts_heading">Catalysts</h3>
          <p id="catalystsText">{{ $instrument->ai_brief_en['catalysts'] }}</p>
        </div>
        <div class="brief-section">
          <h3 data-i18n="risks_heading">Risks</h3>
          <p id="risksText">{{ $instrument->ai_brief_en['risks'] }}</p>
        </div>

        <div class="disclaimer-inline" data-i18n="ai_disclaimer">AI-generated. Not investment advice.</div>
      @else
        <p class="empty-note">No brief yet — run analytics:refresh-briefs.</p>
      @endif
    </div>
  </div>

  @if($correlations->isNotEmpty())
    <section class="panel corr-section">
      <div class="panel-title"><h2 data-i18n="corr_title">Correlation — 90 Day Daily Returns</h2></div>
      <div class="corr-list">
        @php $maxAbs = max($correlations->map(fn($c) => abs($c['value']))->all() ?: [1]); @endphp
        @foreach($correlations as $c)
          <div class="corr-row">
            <div class="corr-symbol">{{ $c['symbol'] }}</div>
            <div class="corr-bar-track">
              <div class="corr-bar-fill" style="background:{{ $c['value'] >= 0 ? 'var(--bull)' : 'var(--bear)' }};width:{{ abs($c['value']) / $maxAbs * 100 }}%;"></div>
            </div>
            <div class="corr-value num" style="color:{{ $c['value'] >= 0 ? 'var(--bull)' : 'var(--bear)' }}">{{ $c['value'] >= 0 ? '+' : '' }}{{ number_format($c['value'], 2) }}</div>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <section class="news-section" id="newsSection" style="{{ $news->isEmpty() ? 'display:none;' : '' }}">
    <div class="panel-title" style="margin-bottom:12px;"><h2 data-i18n="news_title">Related News</h2></div>
    <div class="news-grid" id="newsGrid">
      @foreach($news as $article)
        <div class="news-card">
          <div class="news-meta"><span>{{ $article->source }}</span><span>{{ $article->published_at->diffForHumans() }}</span></div>
          <p class="news-headline">{{ $article->title }}</p>
        </div>
      @endforeach
    </div>
  </section>

  <footer class="foot-disclaimer" data-i18n="footer_disclaimer">
    All analysis on this page is AI-generated and is provided for informational purposes only. It does not constitute investment advice. The Sharia compliance badge is a research tool based on published Islamic finance principles and is not a religious ruling — consult a qualified scholar for guidance.
  </footer>
</div>

<script>
(function(){
  "use strict";

  var briefAr = @json($instrument->ai_brief_ar ?: null);
  var briefEn = @json($instrument->ai_brief_en ?: null);
  var instrumentNameAr = @json($instrument->name_localized ?? $instrument->name);
  var instrumentNameEn = @json($instrument->name);
  var fullOhlc = @json($ohlc);
  var biasArLabel = @json($bias['ar']);
  var biasEnLabel = @json($bias['en']);

  var i18n = {
    en: {
      brand_name:"Fiper Terminal", live_label:"LIVE", crumb_markets:"Markets",
      nav_home:"Home", nav_markets:"Markets", nav_heatmap:"Heatmap",
      badge_sharia:"Sharia Compliant", updated_label:"Updated",
      chart_title:"Price Chart", legend_up:"Close ≥ Open", legend_down:"Close < Open",
      brief_panel_title:"AI Brief", levels_heading:"Key Levels",
      resistance_label:"Resistance", support_label:"Support",
      indicators_heading:"Technical Indicators", macd_signal_label:"Signal:",
      forecast_heading:"Quantitative Forecast",
      catalysts_heading:"Catalysts", risks_heading:"Risks",
      ai_disclaimer:"AI-generated. Not investment advice.",
      corr_title:"Correlation — 90 Day Daily Returns", news_title:"Related News",
      footer_disclaimer:"All analysis on this page is AI-generated and is provided for informational purposes only. It does not constitute investment advice. The Sharia compliance badge is a research tool based on published Islamic finance principles and is not a religious ruling — consult a qualified scholar for guidance.",
      tt_date:"Date", tt_open:"Open", tt_high:"High", tt_low:"Low", tt_close:"Close"
    },
    ar: {
      brand_name:"فايبر تيرمينال", live_label:"مباشر", crumb_markets:"الأسواق",
      nav_home:"الرئيسية", nav_markets:"الأسواق", nav_heatmap:"الخريطة الحرارية",
      badge_sharia:"متوافق شرعياً", updated_label:"آخر تحديث",
      chart_title:"الرسم البياني للسعر", legend_up:"الإغلاق ≥ الافتتاح", legend_down:"الإغلاق < الافتتاح",
      brief_panel_title:"موجز الذكاء الاصطناعي", levels_heading:"المستويات الرئيسية",
      resistance_label:"مقاومة", support_label:"دعم",
      indicators_heading:"مؤشرات فنية", macd_signal_label:"الإشارة:",
      forecast_heading:"توقّع كمي",
      catalysts_heading:"المحفزات", risks_heading:"المخاطر",
      ai_disclaimer:"محتوى مولّد بالذكاء الاصطناعي. ليس نصيحة استثمارية.",
      corr_title:"الارتباط — عوائد يومية خلال 90 يوماً", news_title:"أخبار ذات صلة",
      footer_disclaimer:"جميع التحليلات في هذه الصفحة مولّدة بالذكاء الاصطناعي وهي لأغراض معلوماتية فقط ولا تُعد نصيحة استثمارية. شارة الامتثال الشرعي أداة بحثية وليست فتوى شرعية — يُرجى الرجوع لأهل العلم المختصين.",
      tt_date:"التاريخ", tt_open:"الافتتاح", tt_high:"الأعلى", tt_low:"الأدنى", tt_close:"الإغلاق"
    }
  };

  var currentLang = "en";

  function onLangChange(){
    var brief = currentLang === "ar" ? briefAr : briefEn;
    var displayName = currentLang === "ar" ? instrumentNameAr : instrumentNameEn;
    document.getElementById("instrumentName").textContent = displayName;
    document.getElementById("crumbCurrent").textContent = displayName;
    document.getElementById("biasBadge").textContent = currentLang === "ar" ? biasArLabel : biasEnLabel;

    if(brief){
      var titleEl = document.getElementById("briefTitle");
      var summaryEl = document.getElementById("briefSummary");
      var catalystsEl = document.getElementById("catalystsText");
      var risksEl = document.getElementById("risksText");
      if(titleEl) titleEl.textContent = brief.title;
      if(summaryEl) summaryEl.textContent = brief.summary;
      if(catalystsEl) catalystsEl.textContent = brief.catalysts;
      if(risksEl) risksEl.textContent = brief.risks;
    }
  }

  @include('partials.i18n')

  /* ---------------- Chart rendering (real data from Blade) ---------------- */
  var svg = document.getElementById("chartSvg");
  var tooltip = document.getElementById("chartTooltip");
  var VB_W = 720, VB_H = 320, PAD_T = 14, PAD_B = 26, PAD_L = 4, PAD_R = 4;

  function renderChart(data){
    while(svg.firstChild){ svg.removeChild(svg.firstChild); }
    if(!data.length){ return; }

    var lows = data.map(function(c){ return c.low; });
    var highs = data.map(function(c){ return c.high; });
    var min = Math.min.apply(null, lows), max = Math.max.apply(null, highs);
    var range = (max - min) || 1;
    min -= range * 0.08; max += range * 0.08; range = max - min;

    var innerW = VB_W - PAD_L - PAD_R, innerH = VB_H - PAD_T - PAD_B;
    var slot = innerW / data.length;
    var candleW = Math.max(2, Math.min(10, slot * 0.6));
    function yFor(v){ return PAD_T + innerH - ((v - min) / range) * innerH; }
    var svgns = "http://www.w3.org/2000/svg";

    for(var g = 0; g <= 4; g++){
      var gy = PAD_T + (innerH / 4) * g;
      var line = document.createElementNS(svgns, "line");
      line.setAttribute("x1", PAD_L); line.setAttribute("x2", VB_W - PAD_R);
      line.setAttribute("y1", gy); line.setAttribute("y2", gy);
      line.setAttribute("stroke", "#241f1e"); line.setAttribute("stroke-width", "1");
      svg.appendChild(line);
      var label = document.createElementNS(svgns, "text");
      label.setAttribute("x", VB_W - PAD_R - 2); label.setAttribute("y", gy - 4);
      label.setAttribute("text-anchor", "end"); label.setAttribute("font-size", "9");
      label.setAttribute("fill", "#6f6663");
      label.textContent = (max - (range / 4) * g).toFixed(2);
      svg.appendChild(label);
    }

    data.forEach(function(c, i){
      var cx = PAD_L + slot * i + slot / 2;
      var up = c.close >= c.open;
      var color = up ? "#2fbe8f" : "#f42821";

      var wick = document.createElementNS(svgns, "line");
      wick.setAttribute("x1", cx); wick.setAttribute("x2", cx);
      wick.setAttribute("y1", yFor(c.high)); wick.setAttribute("y2", yFor(c.low));
      wick.setAttribute("stroke", color); wick.setAttribute("stroke-width", "1");
      svg.appendChild(wick);

      var bodyTop = yFor(Math.max(c.open, c.close));
      var bodyH = Math.max(1.2, Math.abs(yFor(c.open) - yFor(c.close)));
      var body = document.createElementNS(svgns, "rect");
      body.setAttribute("x", cx - candleW / 2); body.setAttribute("y", bodyTop);
      body.setAttribute("width", candleW); body.setAttribute("height", bodyH);
      body.setAttribute("fill", color); body.setAttribute("rx", "1");
      svg.appendChild(body);

      var hit = document.createElementNS(svgns, "rect");
      hit.setAttribute("x", PAD_L + slot * i); hit.setAttribute("y", PAD_T);
      hit.setAttribute("width", slot); hit.setAttribute("height", innerH);
      hit.setAttribute("fill", "transparent");
      hit.addEventListener("mouseenter", function(){
        var dict = i18n[currentLang];
        tooltip.innerHTML =
          '<div class="tt-row"><span class="tt-label">'+dict.tt_date+'</span><span>'+c.date+'</span></div>'+
          '<div class="tt-row"><span class="tt-label">'+dict.tt_open+'</span><span class="num">'+c.open.toFixed(2)+'</span></div>'+
          '<div class="tt-row"><span class="tt-label">'+dict.tt_high+'</span><span class="num">'+c.high.toFixed(2)+'</span></div>'+
          '<div class="tt-row"><span class="tt-label">'+dict.tt_low+'</span><span class="num">'+c.low.toFixed(2)+'</span></div>'+
          '<div class="tt-row"><span class="tt-label">'+dict.tt_close+'</span><span class="num">'+c.close.toFixed(2)+'</span></div>';
        tooltip.classList.add("visible");
      });
      hit.addEventListener("mousemove", function(evt){
        var rect = svg.getBoundingClientRect();
        var left = evt.clientX - rect.left + 12, top = evt.clientY - rect.top - 10;
        if(left + 140 > rect.width){ left = evt.clientX - rect.left - 150; }
        tooltip.style.left = left + "px"; tooltip.style.top = top + "px";
      });
      hit.addEventListener("mouseleave", function(){ tooltip.classList.remove("visible"); });
      svg.appendChild(hit);
    });
  }

  var periodButtons = document.querySelectorAll(".period-tabs button");
  periodButtons.forEach(function(btn){
    btn.addEventListener("click", function(){
      periodButtons.forEach(function(b){ b.classList.remove("is-active"); });
      btn.classList.add("is-active");
      var n = parseInt(btn.getAttribute("data-period"), 10);
      renderChart(fullOhlc.slice(Math.max(0, fullOhlc.length - n)));
    });
  });

  renderChart(fullOhlc.slice(Math.max(0, fullOhlc.length - 30)));

  var mySymbol = @json($instrument->symbol);

  document.addEventListener("DOMContentLoaded", function () {
    if (window.Echo) {
      window.Echo.channel("quotes").listen(".quote.updated", function (e) {
        if (e.symbol !== mySymbol) return;
        var up = e.change_percent >= 0;
        var priceEl = document.getElementById("instrumentPrice");
        var changeEl = document.getElementById("instrumentChange");
        var updatedEl = document.getElementById("instrumentUpdatedTime");
        if (priceEl) priceEl.textContent = Number(e.price).toFixed(2);
        if (changeEl) {
          changeEl.className = "change num " + (up ? "up" : "down");
          changeEl.textContent = (up ? "+" : "") + Number(e.change).toFixed(2) +
            " (" + (up ? "+" : "") + Number(e.change_percent).toFixed(2) + "%)";
        }
        if (updatedEl) updatedEl.textContent = "just now";
      });

      window.Echo.channel("news").listen(".news.article-ingested", function (e) {
        if (e.instrument_symbols.indexOf(mySymbol) === -1) return;
        var section = document.getElementById("newsSection");
        var grid = document.getElementById("newsGrid");
        section.style.display = "";
        var card = document.createElement("div");
        card.className = "news-card";
        card.innerHTML =
          '<div class="news-meta"><span>' + e.source + "</span><span>just now</span></div>" +
          '<p class="news-headline">' + e.headline + "</p>";
        grid.insertBefore(card, grid.firstChild);
      });

      window.Echo.channel("briefs").listen(".brief.generated", function (e) {
        if (e.symbol !== mySymbol) return;
        briefEn = e.brief_en;
        briefAr = e.brief_ar;
        biasEnLabel = e.bias_label_en;
        biasArLabel = e.bias_label_ar;
        var badge = document.getElementById("biasBadge");
        if (badge) badge.className = "badge " + e.bias_class;
        onLangChange();
      });
    }
  });

  applyI18n();
})();
</script>
</body>
</html>
