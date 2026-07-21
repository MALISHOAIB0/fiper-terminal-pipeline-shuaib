# Home / Markets / Heatmap pages + lightweight CMS — design

**Date:** 2026-07-21
**Status:** Approved by user, pending implementation plan

## Context

The only real frontend page today is `/instrument/{symbol}` (`resources/views/pages/instrument.blade.php`),
a self-contained, dark-themed, bilingual (EN/AR + RTL) single-file Blade view rendering live data from
the DB (price, chart, AI brief, correlations, news). All 81 instruments are now seeded
(`database/seeders/InstrumentSeeder.php`).

This spec covers the three remaining top-level pages named in `PROJECT-STATUS.md`'s next steps —
Home, Markets, Heatmap — plus a small CMS for their editorial copy (hero titles/subtitles), decided
in favor of keeping real API key integration deferred.

## Goals

- Add `/`, `/markets`, `/heatmap` routes/pages, visually and behaviorally consistent with the
  existing instrument page (dark theme, EN/AR toggle with RTL, client-side JS, no page framework).
- Let a small amount of per-page editorial/marketing text (title + subtitle, 2 fields × 3 pages) be
  edited by a non-technical admin through a real login-gated UI, without touching code — everything
  else on these pages stays live from the DB, same as the instrument page.
- Stop tripling ~150 lines of shared topbar/CSS/locale-toggle boilerplate across new pages — extract
  it once, and migrate the existing instrument page onto it (no behavior change).

## Non-goals

- No general-purpose page builder / block editor — just 2 fixed text fields per page.
- No live-data widgets on Home (explicitly decided: hero + 2 CTA buttons only).
- No sorting/column customization on Markets beyond the asset-class tabs.
- No new automated test layer — match the existing precedent (manual Playwright pass, no PHP feature
  tests for the instrument page either).
- No change to real API key / provider status — still fully on stubs.

## 1. Shared layout

- `resources/views/layouts/app.blade.php` — extracted `<head>` (CSS custom properties, dark theme,
  shared component classes), topbar markup (brand, live-status dot, locale toggle, **new**: Home /
  Markets / Heatmap nav links with active-page highlight).
- `resources/views/partials/i18n.blade.php` — extracted `setLang()` / `applyI18n()` JS engine
  (walks `[data-i18n]` elements against a page-supplied `i18n` dictionary object, toggles
  `dir`/`lang` on `#app`). Each page defines its own dictionary (`{ en: {...}, ar: {...} }`) and
  includes this partial after defining it.
- `instrument.blade.php` refactored to consume both instead of inlining them. No visual or
  behavioral change — verify via the same Playwright checks (EN/AR screenshots, RTL toggle, console
  errors, chart interactivity) that were used when the page was first built, run before and after
  the refactor to confirm parity.

## 2. CMS content

**Migration — `page_contents` table:**

```
id
page_slug   string, indexed        -- 'home' | 'markets' | 'heatmap'
field_key   string                 -- 'hero_title' | 'hero_subtitle' | 'page_title' | 'page_subtitle'
value_en    text
value_ar    text
timestamps
unique(page_slug, field_key)
```

**Model — `PageContent`:**
- Standard Eloquent model over the table.
- Static helper `PageContent::for(string $slug): array` returns
  `['field_key' => ['en' => ..., 'ar' => ...], ...]` for that page, so controllers/views do
  `$content['hero_title']['en']`.

**Seeder — `PageContentSeeder`:**
- `home`: `hero_title`, `hero_subtitle`
- `markets`: `page_title`, `page_subtitle`
- `heatmap`: `page_title`, `page_subtitle`
- Registered in `DatabaseSeeder` alongside `InstrumentSeeder`.

**Admin UI — Filament:**
- `composer require filament/filament`, panel installed at `/admin`.
- One `PageContentResource`: list + edit form (page_slug select, field_key text, value_en textarea,
  value_ar textarea).
- One admin user created via `php artisan make:filament-user` during implementation (password set
  interactively, not committed to the repo or this doc).

## 3. Pages

### Home (`/`)

- `HomeController@index` loads `PageContent::for('home')`.
- Renders hero title + subtitle (bilingual, from CMS) and two CTA buttons: "Browse Markets" →
  `/markets`, "View Heatmap" → `/heatmap`.
- No live data on this page (explicit decision — keep it to hero + navigation).
- Replaces the current `Route::get('/', fn () => view('welcome'))`.

### Markets (`/markets`)

- `MarketsController@index` loads
  `Instrument::where('is_active', true)->with('latestQuote')->orderBy('asset_class')->orderBy('symbol')->get()`
  plus `PageContent::for('markets')`.
- New `Instrument::latestQuote()` relation: `hasOne(QuoteSnapshot::class)->latestOfMany('quoted_at')`
  — avoids 81 separate "latest quote" queries (the existing single-instrument controller does this
  inline per-request, which doesn't scale to a query of ALL instruments).
- Renders one HTML table: symbol, name, price, change, change%, bias badge. Each `<tr>` tagged
  `data-asset-class="{{ $instrument->asset_class }}"`.
- Tabs above the table (All / Forex / Crypto / Metals / Stocks / Indices / Commodities) — pure
  client-side show/hide of rows by `data-asset-class`, no server round-trip, matching the existing
  client-side-first pattern (like the instrument page's period tabs).
- Row click → `/instrument/{symbol}`.

### Heatmap (`/heatmap`)

- `HeatmapController@index` — same query shape as Markets (`with('latestQuote')`) plus
  `PageContent::for('heatmap')`.
- Instruments grouped into 7 `asset_class` panels (Forex, Crypto, Metals, Stocks, Indices,
  Commodities — Forex rendered as one group despite major/minor split in the seeder, since
  `asset_class` doesn't distinguish them).
- Each tile: symbol + change%, background color interpolated red→green by `change_percent`
  magnitude, saturation capped at ±3% (values beyond that render at full color rather than scaling
  further, so a handful of volatile instruments don't wash out the rest of the grid).
- Tile click → `/instrument/{symbol}`.

Both pages reuse existing CSS (`.badge-bull`/`.badge-bear`, `.num`, panel styles) from the shared
layout — no new color system.

## Data flow summary

```
Instrument (81 rows, seeded)
  ├─ latestQuote (hasOne latestOfMany) ──> used by Markets, Heatmap
  ├─ ohlcDaily, ai_brief_en/ar, etc.   ──> unchanged, instrument page only

PageContent (6 rows, seeded, admin-editable via Filament)
  ├─ 'home'    : hero_title, hero_subtitle       ──> Home
  ├─ 'markets' : page_title, page_subtitle        ──> Markets
  └─ 'heatmap' : page_title, page_subtitle        ──> Heatmap
```

## Verification plan

1. `php -l` on all new/changed PHP files.
2. Re-seed (`InstrumentSeeder` + new `PageContentSeeder`), confirm `page_contents` has 6 rows.
3. Hit all 3 new routes + `/instrument/2222.SR` (post-layout-refactor) via `curl`, expect 200.
4. Playwright pass: screenshot EN + AR for all 4 pages, toggle RTL, click through nav
   (Home→Markets→Heatmap→instrument row/tile→back), check browser console for errors.
5. Confirm Filament `/admin` loads, login works, editing a `PageContentResource` row and reloading
   the public page reflects the change.
