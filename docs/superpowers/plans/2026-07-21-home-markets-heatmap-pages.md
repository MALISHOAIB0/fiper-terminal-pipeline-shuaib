# Home / Markets / Heatmap Pages + CMS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `/`, `/markets`, and `/heatmap` pages (dark-themed, bilingual EN/AR + RTL, matching the existing `/instrument/{symbol}` page), backed by a small Filament-admin-editable CMS for their editorial copy.

**Architecture:** Extract the existing instrument page's shared topbar/CSS/i18n-engine into reusable Blade partials (no behavior change to the instrument page), add a `page_contents` table + `PageContent` model for the 6 CMS-editable text fields, add an `Instrument::latestQuote()` relation for efficient multi-instrument quote lookups, then build three new controller+view pairs on top of that shared foundation, and finally bolt on a Filament admin panel to edit the CMS rows.

**Tech Stack:** Laravel 13 / PHP 8.5, Blade (no frontend framework — vanilla JS + inline `<style>`, matching the existing instrument page), PostgreSQL, Filament v5 (`filament/filament`, confirmed via `composer require filament/filament --dry-run` to resolve to `^5.7` against this project's Laravel/PHP versions).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-21-home-markets-heatmap-pages-design.md` — every task's requirements implicitly include it.
- **No new automated test layer** (explicit spec non-goal) — this codebase has no PHPUnit feature tests for the instrument page either. "Test" steps in this plan are `php -l` lint, `php artisan tinker` one-liners, and `curl`/`grep` smoke checks against the running dev server, matching the project's existing verification precedent. A full manual Playwright pass (screenshots EN+AR, RTL toggle, console-error check, nav flow) is the final task, not per-task.
- Dev server assumptions: `php artisan serve --port=8123`, Horizon, PostgreSQL, and Redis are already running locally (confirmed running as of this plan). If any command in this plan fails with a connection error, check those first before debugging the code.
- No page-builder / block editor — `page_contents` has exactly 6 rows (2 fields × 3 pages), fixed by this plan, not a general schema.
- Match existing code style: no docblocks/comments unless explaining a non-obvious "why", 4-space indentation, existing Blade patterns (inline `<style>`, vanilla JS IIFE, `data-i18n` attributes).
- Every `git commit` in this plan follows the existing repo's commit style (imperative subject line, `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>` trailer).

---

### Task 1: `page_contents` table + `PageContent` model

**Files:**
- Create: `database/migrations/2026_07_21_120000_create_page_contents_table.php`
- Create: `app/Models/PageContent.php`

**Interfaces:**
- Produces: `PageContent::for(string $slug): array` — returns `['field_key' => ['en' => string, 'ar' => string], ...]` for all rows matching `page_slug = $slug`. Used by every controller in Tasks 5–7.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_contents', function (Blueprint $table) {
            $table->id();
            $table->string('page_slug');
            $table->string('field_key');
            $table->text('value_en');
            $table->text('value_ar');
            $table->timestamps();

            $table->unique(['page_slug', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_contents');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: output includes `2026_07_21_120000_create_page_contents_table ... DONE`

- [ ] **Step 3: Verify the table shape**

Run:
```bash
php artisan tinker --execute="print_r(Illuminate\Support\Facades\Schema::getColumnListing('page_contents'));"
```
Expected: array containing `id, page_slug, field_key, value_en, value_ar, created_at, updated_at`.

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageContent extends Model
{
    protected $fillable = ['page_slug', 'field_key', 'value_en', 'value_ar'];

    public static function for(string $slug): array
    {
        return static::where('page_slug', $slug)
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->field_key => ['en' => $row->value_en, 'ar' => $row->value_ar],
            ])
            ->all();
    }
}
```

- [ ] **Step 5: Verify `php -l` and commit**

Run:
```bash
php -l app/Models/PageContent.php
php -l database/migrations/2026_07_21_120000_create_page_contents_table.php
git add database/migrations/2026_07_21_120000_create_page_contents_table.php app/Models/PageContent.php
git commit -m "$(cat <<'EOF'
Add page_contents table and PageContent model

Backs the CMS editorial copy (hero titles/subtitles) for the upcoming
Home/Markets/Heatmap pages — see spec
docs/superpowers/specs/2026-07-21-home-markets-heatmap-pages-design.md.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```
Expected: both `php -l` calls print `No syntax errors detected`; commit succeeds.

---

### Task 2: `PageContentSeeder`

**Files:**
- Create: `database/seeders/PageContentSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: `PageContent` model from Task 1.
- Produces: 6 seeded `page_contents` rows (`home.hero_title`, `home.hero_subtitle`, `markets.page_title`, `markets.page_subtitle`, `heatmap.page_title`, `heatmap.page_subtitle`), consumed by `PageContent::for()` calls in Tasks 5–7.

- [ ] **Step 1: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\PageContent;
use Illuminate\Database\Seeder;

class PageContentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['home', 'hero_title', 'Markets, decoded.', 'الأسواق، بوضوح.'],
            ['home', 'hero_subtitle', 'Live prices, AI-generated briefs, and Shariah screening across 81 instruments — forex, crypto, metals, stocks, indices, and commodities.', 'أسعار حية، وموجزات بالذكاء الاصطناعي، وفحص شرعي عبر 81 أداة مالية — فوركس وعملات رقمية ومعادن وأسهم ومؤشرات وسلع.'],
            ['markets', 'page_title', 'Markets', 'الأسواق'],
            ['markets', 'page_subtitle', 'All 81 instruments, live prices and AI sentiment at a glance.', 'كل الأدوات الـ81، بأسعارها الحية وتوجهها حسب الذكاء الاصطناعي دفعة واحدة.'],
            ['heatmap', 'page_title', 'Heatmap', 'الخريطة الحرارية'],
            ['heatmap', 'page_subtitle', "Today's move across every instrument, grouped by asset class.", 'حركة اليوم عبر كل أداة، مجمّعة حسب فئة الأصل.'],
        ];

        foreach ($rows as [$slug, $key, $en, $ar]) {
            PageContent::updateOrCreate(
                ['page_slug' => $slug, 'field_key' => $key],
                ['value_en' => $en, 'value_ar' => $ar],
            );
        }
    }
}
```

- [ ] **Step 2: Register it in `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, change:
```php
    public function run(): void
    {
        $this->call(InstrumentSeeder::class);
    }
```
to:
```php
    public function run(): void
    {
        $this->call(InstrumentSeeder::class);
        $this->call(PageContentSeeder::class);
    }
```

- [ ] **Step 3: Run and verify**

Run:
```bash
php artisan db:seed --class=PageContentSeeder
php artisan tinker --execute="echo App\Models\PageContent::count().PHP_EOL; echo json_encode(App\Models\PageContent::for('home')).PHP_EOL;"
```
Expected: `6` on the first line; second line is a JSON object with `hero_title` and `hero_subtitle` keys, each an object with `en`/`ar`.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/PageContentSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "$(cat <<'EOF'
Seed default CMS copy for Home/Markets/Heatmap pages

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Extract shared layout partials, refactor the instrument page onto them

This is the highest-risk task: it touches the one page that's already verified working (per `PROJECT-STATUS.md`, 4 real bugs were found and fixed on this page via Playwright). The goal is **zero behavior change** — only where the shared markup/CSS/JS physically lives changes.

**Files:**
- Create: `resources/views/layouts/app-head.blade.php`
- Create: `resources/views/layouts/app-topbar.blade.php`
- Create: `resources/views/partials/i18n.blade.php`
- Modify: `app/Models/Instrument.php` (add `biasMeta()`)
- Modify: `resources/views/pages/instrument.blade.php`

**Interfaces:**
- Produces: `Instrument::biasMeta(?string $bias): array` — returns `['en' => string, 'ar' => string, 'class' => 'badge-bull'|'badge-bear'|'badge-neutral']`, defaulting to the neutral entry for any unrecognized/null value. Used by Task 3's own refactor and by Task 6 (Markets table).
- Produces: `@include('layouts.app-head')` — a `<style>` block; include once inside `<head>`, after `<title>`.
- Produces: `@include('layouts.app-topbar', ['activeNav' => 'home'|'markets'|'heatmap'|null])` — renders `<header class="topbar">` with brand, nav links (Home/Markets/Heatmap, static hrefs `/`, `/markets`, `/heatmap` — no named-route dependency, since those routes don't exist until Tasks 5–7), live-status dot, and the EN/AR toggle buttons (`#btnEn`, `#btnAr`). `activeNav` defaults to `null` (no nav item highlighted) if omitted.
- Produces: `@include('partials.i18n')` — raw JS (no `<script>` tags of its own) providing `applyI18n()` and `setLang()`, plus click listeners on `#btnEn`/`#btnAr`. Must be included *inside* the consuming page's own `<script>(function(){ ... })();</script>` IIFE, after that page has declared `var i18n = {...}` and `var currentLang = "en";`. If the page needs to react to language changes beyond the generic `[data-i18n]` text swap (e.g. swapping a value pulled from JSON, not from the dict), it must define `function onLangChange(){ ... }` before or after the include (function-hoisted, so position doesn't matter) — `applyI18n()` calls it if present.

- [ ] **Step 1: Add `Instrument::biasMeta()`**

In `app/Models/Instrument.php`, add this method inside the class (after the existing relations):

```php
    public static function biasMeta(?string $bias): array
    {
        return match ($bias) {
            'bullish' => ['en' => 'Bullish', 'ar' => 'صعودي', 'class' => 'badge-bull'],
            'lean_bullish' => ['en' => 'Lean Bullish', 'ar' => 'ميل صعودي حذر', 'class' => 'badge-bull'],
            'lean_bearish' => ['en' => 'Lean Bearish', 'ar' => 'ميل هبوطي حذر', 'class' => 'badge-bear'],
            'bearish' => ['en' => 'Bearish', 'ar' => 'هبوطي', 'class' => 'badge-bear'],
            default => ['en' => 'Neutral', 'ar' => 'محايد', 'class' => 'badge-neutral'],
        };
    }
```

This replaces the `$biasMeta` array currently inlined at the top of `instrument.blade.php` (lines 2–9) — same 5 keys, same fallback-to-neutral behavior (`$biasMeta[$instrument->ai_bias] ?? $biasMeta['neutral']` becomes `Instrument::biasMeta($instrument->ai_bias)`).

- [ ] **Step 2: Verify with tinker**

```bash
php artisan tinker --execute="print_r(App\Models\Instrument::biasMeta('bullish')); print_r(App\Models\Instrument::biasMeta(null)); print_r(App\Models\Instrument::biasMeta('nonsense'));"
```
Expected: first call returns `['en'=>'Bullish','ar'=>'صعودي','class'=>'badge-bull']`; the other two both return the neutral entry.

- [ ] **Step 3: Create `resources/views/layouts/app-head.blade.php`**

```blade
<style>
  :root{
    --bg:#0b0a0a; --surface:#141211; --surface-2:#1c1918; --surface-3:#242020;
    --border:#2b2624; --border-soft:#1e1a19;
    --text:#f2eeec; --text-dim:#a89e9a; --text-faint:#6f6663;
    --accent:#f42821; --accent-dark:#a30100;
    --bull:#2fbe8f; --bull-soft:rgba(47,190,143,.13);
    --bear:#f42821; --bear-soft:rgba(244,40,33,.13);
    --gold:#d9a94d; --gold-soft:rgba(217,169,77,.13);
    --radius:10px;
    --font-ui:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif;
    --font-mono:ui-monospace,"SF Mono","Cascadia Mono","Roboto Mono",Consolas,monospace;
  }
  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{background:var(--bg);color:var(--text);font-family:var(--font-ui);font-size:14px;line-height:1.55;-webkit-font-smoothing:antialiased;}
  #app{max-width:1180px;margin:0 auto;padding:0 20px 48px;}
  a{color:inherit;} button{font-family:inherit;}
  .num{direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums;font-family:var(--font-mono);display:inline-block;}
  .topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 0;border-bottom:1px solid var(--border-soft);}
  .brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:15px;white-space:nowrap;}
  .brand .mark{width:26px;height:26px;border-radius:6px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;}
  .main-nav{display:flex;gap:4px;}
  .main-nav a{padding:7px 12px;border-radius:8px;font-size:12.5px;font-weight:600;color:var(--text-dim);text-decoration:none;}
  .main-nav a:hover{color:var(--text);}
  .main-nav a.is-active{background:var(--surface-2);color:var(--text);}
  .topbar-actions{display:flex;align-items:center;gap:10px;}
  .locale-toggle{display:flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;}
  .locale-toggle button{padding:7px 12px;background:var(--surface);color:var(--text-dim);border:none;cursor:pointer;font-size:12.5px;font-weight:600;}
  .locale-toggle button.is-active{background:var(--surface-3);color:var(--text);}
  .live-status{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-dim);}
  .pulse-dot{width:7px;height:7px;border-radius:50%;background:var(--bull);animation:pulse 2s infinite;}
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(47,190,143,.45);}70%{box-shadow:0 0 0 7px rgba(47,190,143,0);}100%{box-shadow:0 0 0 0 rgba(47,190,143,0);}}
  .breadcrumb{display:flex;gap:8px;align-items:center;font-size:12.5px;color:var(--text-faint);margin:18px 0 6px;}
  .badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:100px;font-size:11.5px;font-weight:700;}
  .badge-gold{background:var(--gold-soft);color:var(--gold);}
  .badge-bull{background:var(--bull-soft);color:var(--bull);}
  .badge-bear{background:var(--bear-soft);color:var(--bear);}
  .badge-neutral{background:var(--surface-2);color:var(--text-dim);border:1px solid var(--border);}
  .panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;}
  .panel-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
  .panel-title h2{font-size:13px;margin:0;text-transform:uppercase;letter-spacing:.06em;color:var(--text-dim);font-weight:700;}
  .empty-note{font-size:12px;color:var(--text-faint);font-style:italic;}
  #app[dir="rtl"] .brand{flex-direction:row-reverse;}
  #app[dir="rtl"] .locale-toggle{flex-direction:row-reverse;}
  #app[dir="rtl"] .main-nav{flex-direction:row-reverse;}
</style>
```

- [ ] **Step 4: Create `resources/views/layouts/app-topbar.blade.php`**

```blade
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
```

- [ ] **Step 5: Create `resources/views/partials/i18n.blade.php`**

```blade
function applyI18n(){
  var dict = i18n[currentLang];
  document.querySelectorAll("[data-i18n]").forEach(function(el){
    var key = el.getAttribute("data-i18n");
    if(dict[key] !== undefined){ el.textContent = dict[key]; }
  });
  if (typeof onLangChange === "function") { onLangChange(); }
}

function setLang(lang){
  currentLang = lang;
  var app = document.getElementById("app");
  app.setAttribute("lang", lang);
  app.setAttribute("dir", lang === "ar" ? "rtl" : "ltr");
  document.getElementById("btnEn").classList.toggle("is-active", lang === "en");
  document.getElementById("btnAr").classList.toggle("is-active", lang === "ar");
  applyI18n();
}

document.getElementById("btnEn").addEventListener("click", function(){ setLang("en"); });
document.getElementById("btnAr").addEventListener("click", function(){ setLang("ar"); });
```

- [ ] **Step 6: Refactor `resources/views/pages/instrument.blade.php`**

Replace the `@php` block at the top (lines 1–11) with:
```blade
@php
    $bias = \App\Models\Instrument::biasMeta($instrument->ai_bias);
    $changeUp = $quote && $quote->change >= 0;
@endphp
```

Replace the entire `<style>...</style>` block (lines 17–110) with the page-specific rules only (everything that moved to `app-head.blade.php` removed), preceded by the include:
```blade
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
  .change.up{color:var(--bull);} .change.down{color:var(--bear);}
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
```

Replace the `<header class="topbar">...</header>` block (originally lines 115–127) with:
```blade
  @include('layouts.app-topbar')
```

In the `<script>` IIFE, replace the `function applyI18n(){...}` and `function setLang(lang){...}` definitions plus the two `addEventListener` lines (originally lines 326–362) with:
```blade
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
```

Finally, in the `i18n` dictionary object (both `en` and `ar` sub-objects), add three keys each, matching the nav labels used by `app-topbar.blade.php`. In `en`, right after `crumb_markets:"Markets",` add `nav_home:"Home", nav_markets:"Markets", nav_heatmap:"Heatmap",`. In `ar`, right after `crumb_markets:"الأسواق",` add `nav_home:"الرئيسية", nav_markets:"الأسواق", nav_heatmap:"الخريطة الحرارية",`.

- [ ] **Step 7: `php -l` the real PHP file**

```bash
php -l app/Models/Instrument.php
```
Expected: `No syntax errors detected`. (`php -l` is not run against the `.blade.php` files in this plan — Blade directives like `@php`/`@foreach`/`{{ }}` aren't valid raw PHP tags, so `php -l` on an uncompiled `.blade.php` file trivially "passes" without actually parsing the embedded logic; verified directly — it printed `No syntax errors detected` even before this refactor touched anything. The real check for Blade correctness is Step 8: a malformed Blade file throws a compile error and the page returns HTTP 500 instead of 200.)

- [ ] **Step 8: Smoke-test the refactored page**

Run:
```bash
curl -s http://127.0.0.1:8123/instrument/2222.SR -o /tmp/instrument-after.html
grep -c 'data-i18n="nav_markets"' /tmp/instrument-after.html
grep -c 'class="topbar"' /tmp/instrument-after.html
grep -c 'id="instrumentName"' /tmp/instrument-after.html
```
Expected: each `grep -c` prints `1` (nav markup present, topbar present, instrument-name element still rendering — verified against the current, pre-refactor page: `class="topbar"` already returns `1` today, `data-i18n="nav_markets"` returns `0` today since the nav doesn't exist until this task adds it, `id="instrumentName"` returns `1`). Don't grep for the instrument's *name text* (e.g. "Saudi Aramco") as a freshness check — it's not a stable count, since the stub-generated AI brief text also mentions the instrument name an unpredictable number of times (verified: 6 occurrences total on this page, not 2). If the dev server isn't running, start it first: `php artisan serve --port=8123 &`.

- [ ] **Step 9: Full Playwright parity check**

Using the `webapp-testing` skill / Playwright: load `/instrument/2222.SR`, screenshot in EN, click the AR toggle, screenshot in AR (confirm RTL layout, Arabic name/brief swap), check browser console for errors, click the 1W/3M chart period tabs and confirm the candlestick chart re-renders. This must look identical to the page's pre-refactor behavior (same page, same data) — the only new thing on screen is the 3 nav links in the topbar.

- [ ] **Step 10: Commit**

```bash
git add app/Models/Instrument.php resources/views/layouts/app-head.blade.php resources/views/layouts/app-topbar.blade.php resources/views/partials/i18n.blade.php resources/views/pages/instrument.blade.php
git commit -m "$(cat <<'EOF'
Extract shared layout/i18n partials from the instrument page

No behavior change — the topbar, dark-theme CSS, and the setLang/
applyI18n JS engine now live in resources/views/layouts and
resources/views/partials so the upcoming Home/Markets/Heatmap pages
can reuse them instead of tripling ~150 lines of boilerplate.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: `Instrument::latestQuote()` relation

**Files:**
- Modify: `app/Models/Instrument.php`

**Interfaces:**
- Produces: `Instrument::latestQuote(): HasOne` — one query for the whole collection when eager-loaded via `Instrument::with('latestQuote')->get()`, instead of N queries. Consumed by Task 6 (Markets) and Task 7 (Heatmap).

- [ ] **Step 1: Add the relation**

In `app/Models/Instrument.php`, add this method next to the existing `quoteSnapshots()` relation, and add the `HasOne` import:

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

```php
    public function latestQuote(): HasOne
    {
        return $this->hasOne(QuoteSnapshot::class)->latestOfMany('quoted_at');
    }
```

- [ ] **Step 2: Verify with tinker (single-query check)**

```bash
php artisan tinker --execute="
DB::enableQueryLog();
\$all = App\Models\Instrument::where('is_active', true)->with('latestQuote')->get();
echo 'Queries: '.count(DB::getQueryLog()).PHP_EOL;
echo 'Instruments with a quote: '.\$all->filter(fn(\$i) => \$i->latestQuote !== null)->count().' / '.\$all->count().PHP_EOL;
"
```
Expected: `Queries: 2` (one for instruments, one for the latest-quote join — not 82), and all 81 instruments have a quote (since `quotes:refresh` was already run against the full seed set).

- [ ] **Step 3: `php -l` and commit**

```bash
php -l app/Models/Instrument.php
git add app/Models/Instrument.php
git commit -m "$(cat <<'EOF'
Add Instrument::latestQuote() relation

hasOne(...)->latestOfMany() avoids an N+1 when a page needs the
latest quote for every instrument at once (Markets, Heatmap), unlike
the existing single-instrument controller's inline ->latest()->first().

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Home page

**Files:**
- Create: `app/Http/Controllers/HomeController.php`
- Create: `resources/views/pages/home.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `PageContent::for('home')` (Task 1/2), `layouts.app-head`/`layouts.app-topbar`/`partials.i18n` (Task 3).

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\PageContent;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('pages.home', [
            'content' => PageContent::for('home'),
        ]);
    }
}
```

- [ ] **Step 2: Write the view**

```blade
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
```

- [ ] **Step 3: Wire the route**

In `routes/web.php`, replace:
```php
use App\Http\Controllers\InstrumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/instrument/{symbol}', [InstrumentController::class, 'show'])->name('instrument.show');
```
with:
```php
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstrumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/instrument/{symbol}', [InstrumentController::class, 'show'])->name('instrument.show');
```
(Markets/Heatmap `use` imports and routes are added in Tasks 6–7 — don't add them here yet, to keep this task's diff self-contained.)

- [ ] **Step 4: Smoke-test**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8123/
curl -s http://127.0.0.1:8123/ | grep -c 'Markets, decoded'
curl -s http://127.0.0.1:8123/ | grep -c 'href="/markets"'
```
Expected: `200`, then `1`, then `1`.

- [ ] **Step 5: `php -l` and commit**

```bash
php -l app/Http/Controllers/HomeController.php
php -l routes/web.php
git add app/Http/Controllers/HomeController.php resources/views/pages/home.blade.php routes/web.php
git commit -m "$(cat <<'EOF'
Add Home page

Hero (CMS-editable title/subtitle via PageContent) + two CTA buttons
into Markets/Heatmap. Replaces the default Laravel welcome view at /.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Markets page

**Files:**
- Create: `app/Http/Controllers/MarketsController.php`
- Create: `resources/views/pages/markets.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `Instrument::latestQuote()` (Task 4), `Instrument::biasMeta()` (Task 3), `PageContent::for('markets')` (Task 1/2).

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\PageContent;
use Illuminate\View\View;

class MarketsController extends Controller
{
    public function index(): View
    {
        $instruments = Instrument::where('is_active', true)
            ->with('latestQuote')
            ->orderBy('asset_class')
            ->orderBy('symbol')
            ->get();

        return view('pages.markets', [
            'instruments' => $instruments,
            'content' => PageContent::for('markets'),
        ]);
    }
}
```

- [ ] **Step 2: Write the view**

```blade
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
            <td class="num">{{ $q ? number_format($q->price, 2) : '—' }}</td>
            <td class="num {{ $up ? 'change up' : 'change down' }}">
              @if($q)
                {{ $up ? '+' : '' }}{{ number_format($q->change_percent, 2) }}%
              @else
                —
              @endif
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
```

- [ ] **Step 3: Wire the route**

In `routes/web.php`, add the import and route (after the Home route, before the instrument route):
```php
use App\Http\Controllers\MarketsController;
```
```php
Route::get('/markets', [MarketsController::class, 'index'])->name('markets');
```

- [ ] **Step 4: Smoke-test**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8123/markets
curl -s http://127.0.0.1:8123/markets | grep -c 'data-asset-class='
curl -s http://127.0.0.1:8123/markets | grep -c '2222.SR'
```
Expected: `200`, `81` (one row per instrument), `1`.

- [ ] **Step 5: `php -l` and commit**

```bash
php -l app/Http/Controllers/MarketsController.php
php -l routes/web.php
git add app/Http/Controllers/MarketsController.php resources/views/pages/markets.blade.php routes/web.php
git commit -m "$(cat <<'EOF'
Add Markets page

Single sortable-by-asset-class table of all 81 instruments (price,
change%, AI bias badge), client-side tab filter, bilingual EN/AR.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Heatmap page

**Files:**
- Create: `app/Http/Controllers/HeatmapController.php`
- Create: `resources/views/pages/heatmap.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `Instrument::latestQuote()` (Task 4), `PageContent::for('heatmap')` (Task 1/2).

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\PageContent;
use Illuminate\View\View;

class HeatmapController extends Controller
{
    public function index(): View
    {
        $instruments = Instrument::where('is_active', true)
            ->with('latestQuote')
            ->orderBy('symbol')
            ->get();

        return view('pages.heatmap', [
            'grouped' => $instruments->groupBy('asset_class'),
            'content' => PageContent::for('heatmap'),
        ]);
    }
}
```

- [ ] **Step 2: Write the view**

```blade
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
          <a href="{{ route('instrument.show', $instrument->symbol) }}" class="heatmap-tile" style="background:{{ $tileColor }};">
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

  applyI18n();
})();
</script>
</body>
</html>
```

- [ ] **Step 3: Wire the route**

In `routes/web.php`, add the import and route:
```php
use App\Http\Controllers\HeatmapController;
```
```php
Route::get('/heatmap', [HeatmapController::class, 'index'])->name('heatmap');
```

- [ ] **Step 4: Smoke-test**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8123/heatmap
curl -s http://127.0.0.1:8123/heatmap | grep -c 'heatmap-tile'
curl -s http://127.0.0.1:8123/heatmap | grep -c 'BTCUSDT'
```
Expected: `200`, `81`, `1`.

- [ ] **Step 5: `php -l` and commit**

```bash
php -l app/Http/Controllers/HeatmapController.php
php -l routes/web.php
git add app/Http/Controllers/HeatmapController.php resources/views/pages/heatmap.blade.php routes/web.php
git commit -m "$(cat <<'EOF'
Add Heatmap page

All 81 instruments as color-graded tiles (red/green by change%,
saturation capped at ±3%), grouped into 6 asset-class panels.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Filament admin panel + `PageContentResource`

This task's exact commands and file paths were verified live against this project (Filament resolved to `v5.7.1` — confirmed compatible with `laravel/framework ^13.8` / `php ^8.3`) before this plan was finalized, including that `make:filament-resource --generate` does **not** auto-populate form/table fields in this version (it scaffolds empty `[]` blocks) — so the form/table code below is hand-written, not a guess at what the generator produces.

**Files:**
- Modify: `composer.json`, `composer.lock` (via `composer require`)
- Modify: `.gitignore` (exclude the published static asset directories — see Step 9)
- Create: `app/Providers/Filament/AdminPanelProvider.php` (installer-generated, not hand-authored — do not edit its contents beyond what the installer produces)
- Create: `app/Filament/Resources/PageContents/PageContentResource.php` (installer-generated, correct as-is — do not edit)
- Create: `app/Filament/Resources/PageContents/Schemas/PageContentForm.php`
- Create: `app/Filament/Resources/PageContents/Tables/PageContentsTable.php`
- Create: `app/Filament/Resources/PageContents/Pages/{ListPageContents,CreatePageContent,EditPageContent}.php` (installer-generated, correct as-is — do not edit)
- Modify: `bootstrap/providers.php` (installer adds the panel provider registration)

**Interfaces:**
- Consumes: `PageContent` model (Task 1).
- Produces: a working `/admin` login + CRUD UI over `page_contents`. Nothing public-facing depends on this — Tasks 5–7 already work against seeded data without it.

- [ ] **Step 1: Install Filament**

```bash
composer require filament/filament
```
Expected: resolves to `filament/filament v5.7.1` — no version conflicts.

- [ ] **Step 2: Install the panel**

```bash
php artisan filament:install --panels --no-interaction
```
Expected: creates `app/Providers/Filament/AdminPanelProvider.php`, registers it in `bootstrap/providers.php`, publishes Filament's static assets into `public/css/filament`, `public/js/filament`, `public/fonts/filament` (these regenerate automatically on `composer install`/`composer update` via the `filament:upgrade` call already wired into this project's `post-autoload-dump` composer script — do not hand-edit them, and see Step 7 for whether to commit them).

- [ ] **Step 3: Generate the resource skeleton**

```bash
php artisan make:filament-resource PageContent --generate --no-interaction
```
Expected output: `INFO Filament resource [App\Filament\Resources\PageContents\PageContentResource] created successfully.` and 6 files under `app/Filament/Resources/PageContents/` (`PageContentResource.php`, `Tables/PageContentsTable.php`, `Schemas/PageContentForm.php`, `Pages/CreatePageContent.php`, `Pages/EditPageContent.php`, `Pages/ListPageContents.php`). The top-level `PageContentResource.php` is already correct — it wires `form()`/`table()` to the two files you edit next, and needs no changes.

- [ ] **Step 4: Fill in the form schema**

Replace the full contents of `app/Filament/Resources/PageContents/Schemas/PageContentForm.php` (generated with an empty `->components([])`) with:

```php
<?php

namespace App\Filament\Resources\PageContents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('page_slug')
                    ->options([
                        'home' => 'Home',
                        'markets' => 'Markets',
                        'heatmap' => 'Heatmap',
                    ])
                    ->required(),
                TextInput::make('field_key')
                    ->required(),
                Textarea::make('value_en')
                    ->label('Value (English)')
                    ->rows(4)
                    ->required(),
                Textarea::make('value_ar')
                    ->label('Value (Arabic)')
                    ->rows(4)
                    ->required(),
            ]);
    }
}
```

- [ ] **Step 5: Fill in the table columns**

Replace the full contents of `app/Filament/Resources/PageContents/Tables/PageContentsTable.php` (generated with empty `->columns([])`/`->filters([])`) with:

```php
<?php

namespace App\Filament\Resources\PageContents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PageContentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('page_slug')->sortable()->searchable(),
                TextColumn::make('field_key')->sortable()->searchable(),
                TextColumn::make('value_en')->limit(50),
                TextColumn::make('value_ar')->limit(50),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

- [ ] **Step 6: `php -l` all three touched files**

```bash
php -l app/Filament/Resources/PageContents/Schemas/PageContentForm.php
php -l app/Filament/Resources/PageContents/Tables/PageContentsTable.php
php -l app/Filament/Resources/PageContents/PageContentResource.php
```
Expected: `No syntax errors detected` for all three.

- [ ] **Step 7: Create an admin user**

```bash
php artisan make:filament-user
```
Run interactively — you'll be prompted for name, email, and password. Choose your own; nothing here is committed to the repo (it writes a row to the `users` table, not a file).

- [ ] **Step 8: Verify the panel manually**

1. Visit `http://127.0.0.1:8123/admin`, log in with the user from Step 7.
2. Confirm the "Page Contents" resource appears in the nav and lists 6 rows.
3. Edit `markets` / `page_title`, change `value_en` to something distinctive (e.g. `Markets TEST`), save.
4. In a second tab, load `http://127.0.0.1:8123/markets` and confirm the `<h1>` now reads `Markets TEST`.
5. Revert the edit back to `Markets` in the admin panel.

- [ ] **Step 9: Gitignore the published static assets, then commit**

Filament's asset publish step (Step 2) creates `public/css/filament`, `public/js/filament`, `public/fonts/filament`. These regenerate automatically on `composer install`/`composer update` via `filament:upgrade`, already wired into this project's `post-autoload-dump` composer script — so they don't need to be committed. Add these three lines to `.gitignore` (anywhere in the file is fine, e.g. near the existing `/public/build` entry):
```
/public/css/filament
/public/js/filament
/public/fonts/filament
```

Then verify and commit:
```bash
git add -A
git status --short
```
Confirmed by this task's live spike, expect to see: `M .gitignore`, `M bootstrap/providers.php`, `M composer.json`, `M composer.lock`, `A app/Filament/...` (6 files), `A app/Providers/Filament/AdminPanelProvider.php`. No `public/css`, `public/js`, `public/fonts`, or `config/filament.php` entries should appear (the first three are now gitignored; Filament v5.7 does not publish a config file on install). If `git status --short` shows anything outside this set, stop and investigate before committing.

```bash
git add .gitignore bootstrap/providers.php composer.json composer.lock app/Filament app/Providers/Filament
git commit -m "$(cat <<'EOF'
Add Filament admin panel with PageContentResource

Lets a logged-in admin edit the 6 CMS text fields (Home/Markets/
Heatmap hero title+subtitle) at /admin without touching code.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Final integration verification

No new files — this is a full pass across everything built in Tasks 1–8, using the `webapp-testing` skill / Playwright.

- [ ] **Step 1: Route sanity**

```bash
for path in / /markets /heatmap /instrument/2222.SR /instrument/AAPL /admin; do
  code=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123$path)
  echo "$path -> $code"
done
```
Expected: `200` for every path except `/admin`, which should be a redirect to the login page (`302` or `200` depending on Filament's guard behavior — either is fine, a `500` is not).

- [ ] **Step 2: Playwright pass — Home**

Screenshot EN. Click AR toggle, screenshot AR (confirm hero text switches to the Arabic CMS copy and layout flips RTL). Click "Browse Markets", confirm it lands on `/markets`. Go back, click "View Heatmap", confirm it lands on `/heatmap`. Check console for errors on each step.

- [ ] **Step 3: Playwright pass — Markets**

Screenshot the full table (EN). Click each asset-class tab, confirm only matching rows are visible (e.g. clicking "Crypto" should leave exactly 12 visible rows). Click "All" to restore. Toggle AR, confirm instrument names and bias badges switch language, RTL layout applies, numbers stay LTR (the `.num` class). Click a row, confirm it navigates to that instrument's `/instrument/{symbol}` page. Check console for errors.

- [ ] **Step 4: Playwright pass — Heatmap**

Screenshot (EN), confirm 6 group panels are present with the right instrument counts per group (Forex 15, Crypto 12, Metals 4, Stocks 25, Indices 15, Commodities 10). Confirm tile colors visually track sign/magnitude of change% (mix of red/green, none uniformly gray). Toggle AR, confirm group headings translate and layout flips RTL. Click a tile, confirm navigation to its instrument page. Check console for errors.

- [ ] **Step 5: Nav consistency pass**

On each of the 4 pages (Home, Markets, Heatmap, an instrument page), confirm the topbar's 3 nav links are present and that the current page's link (where applicable) is visually highlighted via `.is-active`, and that the other two links correctly navigate.

- [ ] **Step 6: Update `PROJECT-STATUS.md`**

Add a section (or extend the existing "What's built and verified" section) documenting: the 3 new pages, the shared layout extraction, the `page_contents`/Filament CMS, and the verification performed in this task. Follow the file's existing style (concrete, "actually run and checked" framing, not aspirational).

- [ ] **Step 7: Final commit**

```bash
git add PROJECT-STATUS.md
git commit -m "$(cat <<'EOF'
Document Home/Markets/Heatmap pages and CMS in PROJECT-STATUS

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```
