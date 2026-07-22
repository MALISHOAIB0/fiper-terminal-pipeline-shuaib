# Live data layer (real-time push for quotes/news/briefs) — design

**Date:** 2026-07-22
**Status:** Approved by user, pending implementation plan

## Context

Inspired by reviewing `Fincept-Corporation/FinceptTerminal` (a large C++/Qt6 desktop financial
terminal) alongside this project — specifically its `DataHub` in-process pub/sub layer, which lets
many UI widgets share live market data instead of each opening its own feed.

Today, Markets (`/markets`), Heatmap (`/heatmap`), and Instrument (`/instrument/{symbol}`) pages are
fully server-rendered: every request queries PostgreSQL directly (`Instrument::with('latestQuote')`
etc.). Data itself is refreshed in the background on a fixed schedule
(`quotes:refresh` every minute, `news:ingest` every 30 minutes,
`analytics:refresh-briefs` hourly/every 4 hours — see `routes/console.php`), but a page only sees new
data on its next full reload.

This spec adds real-time push so open pages update live as the existing pipeline produces new data,
without a reload — the actual "live terminal" feel, using Laravel's own first-party stack
(**Reverb** + **Echo**) rather than reinventing `DataHub`'s producer/scheduler machinery.

**Why not port `DataHub` directly:** `DataHub` referees many independent, rate-limited external
producers being pulled by many long-lived UI subscribers inside one desktop process — it needs its
own TTL/rate-limit/coalescing policy layer to prevent producers from being hammered. This project
already has a single scheduled command producing all 81 instruments' data on a fixed cadence; there's
no competing-producer problem to solve. The right-sized equivalent here is: write → broadcast event →
Echo listener updates the DOM.

## Goals

- Markets/Heatmap pages show live price/change% updates (color-coded) as `quotes:refresh` runs, with
  no page reload.
- Instrument detail page shows its own live price header, live-prepended related news, and a live
  brief swap-in, filtered to its own symbol.
- Broadcasting is purely additive: if Reverb is down or a broadcast fails, the underlying data
  pipeline (`quotes:refresh`, `news:ingest`, brief generation) is completely unaffected — it already
  wrote to the DB before the event was dispatched.
- Reuse the existing shared-layout partial pattern (`layouts/app-head.blade.php`) for the Echo
  bootstrap, matching how i18n/topbar are already shared.

## Non-goals

- No live-redrawing of the Instrument page's candlestick chart (chart-library state management is a
  separate, nontrivial problem — stays reload-based).
- No per-symbol channels (`quotes.{symbol}`) — traffic is low (81 instruments/minute across the whole
  app), so one shared public `quotes` channel for every listener is simpler and sufficient.
- No connection-status UI indicator (Echo auto-reconnects silently; can be added later if wanted).
- No private/authenticated channels — all three channels carry public market data, matching the
  pages' current no-login access.
- No porting of `DataHub`'s TTL/rate-limit/coalescing policy abstraction — see rationale above.
- No changes to the CMS, Filament admin, or any existing page's non-live behavior.

## 1. Backend — broadcast events

Three events, each implementing `ShouldBroadcast` (queued on the existing Redis queue connection —
already used by `GenerateInstrumentBrief` — so broadcast dispatch is async and never blocks the
firing command):

- **`QuoteUpdated`** — channel `quotes` (public). Payload: `{symbol, price, change, change_percent,
  quoted_at}`. Fired once per instrument from `quotes:refresh`, immediately after each
  `quote_snapshots` row is written.
- **`NewsArticleIngested`** — channel `news` (public). Payload: `{id, headline, source, published_at,
  instrument_symbols[]}`. Fired from `news:ingest` for each newly-inserted article.
- **`BriefGenerated`** — channel `briefs` (public). Payload: `{symbol, tier, summary_en, summary_ar,
  generated_at}`. Fired from the `GenerateInstrumentBrief` job after it saves.

All three are dispatched from inside the existing commands/job — no new commands, no changes to
what data is produced, only an added broadcast call after each existing write.

## 2. Setup

- `composer require laravel/reverb`, then `php artisan install:broadcasting` — installs config,
  `routes/channels.php`, and Echo/Reverb JS scaffolding (Vite plugin config, `resources/js/echo.js`
  or equivalent).
- `php artisan reverb:start` becomes a new required local process, alongside `php artisan serve`,
  `php artisan horizon`, PostgreSQL, and Redis — added to `PROJECT-STATUS.md`'s restart-commands
  section.
- `.env`: `BROADCAST_CONNECTION=reverb` (replacing the current no-op `log` driver).

## 3. Frontend — Echo listeners

- Echo/Reverb JS client bootstraps once in `layouts/app-head.blade.php`, so every page gets the
  connection for free (same pattern as the shared i18n/topbar partials).
- **Markets page**: subscribes to `quotes` only. On each event, finds the table row by `symbol` and
  updates the price/change% cells in place, reusing the existing `.change` up/down color-class logic.
- **Heatmap page**: subscribes to `quotes` only. On each event, updates the matching tile's
  background color/percentage using the existing color-scale function.
- **Instrument page**: subscribes to all three channels, filtering client-side to its own `symbol`
  (and, for briefs, the currently-displayed tier) — updates the price header live, prepends new
  related news items, and swaps in a new AI brief when one arrives.
- **Home page**: no listeners — it shows no live data (CMS hero + CTAs only).

## 4. Error handling

- Backend: broadcasting is a queued side-effect dispatched *after* the DB write succeeds. A failed or
  retried broadcast job never re-runs or blocks the pipeline command itself.
- Frontend: Echo's built-in reconnect logic handles transient WebSocket drops silently. If Reverb is
  unreachable at page load, listeners simply never fire and the page behaves exactly as it does
  today (static until reload) — no JS errors surfaced to the user.

## 5. Testing

- Backend: feature tests per event, asserting `QuoteUpdated`/`NewsArticleIngested`/`BriefGenerated`
  are dispatched with the correct payload when `quotes:refresh`/`news:ingest`/the brief job run
  (`Event::fake()`, standard Laravel convention).
- Frontend: manual Playwright verification (matching the precedent set for Home/Markets/Heatmap) —
  open a page, trigger the relevant Artisan command in another terminal, confirm the DOM updates live
  without a reload.

## Relationship to other FinceptTerminal-inspired work

This is the first of three planned sub-projects surveyed from FinceptTerminal (live data layer → new
feature screens → AI tool-calling layer). The other two are deliberately deferred to their own
spec/plan cycles and are out of scope here.
