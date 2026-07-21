# Fiper Terminal — Pipeline Rebuild — Project Status

**Last updated:** 2026-07-22 (Home/Markets/Heatmap pages + Filament CMS, integration-verified)
**Location:** `/Users/mohammadalishuaib/Developer/fiper-terminal-pipeline`

## Context

Original Fiper Terminal handover docs (`~/Downloads/files (3)/`) describe a live production
Laravel app, but no actual source code was available to us — only documentation. Everything
below was built **from scratch**, informed by the documented architecture and known issues,
not by editing an existing codebase.

## Stack decisions (deviations from the documented stack, and why)

| Documented | Actually used | Why |
|---|---|---|
| Laravel 11 / PHP 8.3 | **Laravel 13.20 / PHP 8.5** | Laravel 11.x has an unpatched CRLF-injection advisory across the whole branch; no reason to inherit it building fresh |
| — | PostgreSQL 16, Redis 7 (via Homebrew, local) | Matches documented prod stack |

## What's built and verified (not just written — actually run and checked)

### Database schema
`instruments`, `ohlc_daily`, `quote_snapshots`, `news_articles` (+ pivot),
`api_request_log`, `setups`. No legacy `ai_syntheses` table — see pipeline note below.

Seeded instruments: full 81 (`database/seeders/InstrumentSeeder.php`) — 7 forex-major, 8
forex-minor, 12 crypto, 4 metals, 25 stocks (12 MENA: Tadawul/QSE/DFM + 13 global
mega-caps), 15 indices (incl. TASI/DFMGI/QSI), 10 commodities. Matches the category
counts and every symbol named in the original handover docs, including all 10 documented
tier-one symbols (`BTCUSDT`, `ETHUSDT`, `XAUUSD`, `EURUSD`, `US500`, `BRENT`, `DXY`,
`US10Y`, `VIX`, `USDJPY`). The docs never shipped an actual symbol list — only counts and
a few examples — so the rest (tickers, Arabic names, MENA Shariah screening) was curated
from scratch. Verified: `ohlc:backfill`, `quotes:refresh`, `news:ingest`, and
`analytics:refresh-briefs` (both tiers) all run clean across all 81; every instrument has
both EN and AR briefs; `CorrelationService` returns 80 correlations for a single instrument
in ~0.16s.

### Unified AI brief pipeline
Single command `analytics:refresh-briefs {--tier=}` replaces the original two parallel,
diverging pipelines (one of which had gone silently stale in the documented handover).
`is_tier_one` is a column on `instruments`, not a hardcoded symbol list.

Real queue worker (Redis) via a `GenerateInstrumentBrief` job — one job per instrument —
fixing the documented "no queue worker exists" gap.

Scheduler registered in `routes/console.php`:
- `quotes:refresh` — every minute
- `news:ingest` — every 30 min
- `ohlc:daily-refresh` — daily 00:15
- `analytics:refresh-briefs --tier=one` — hourly
- `analytics:refresh-briefs --tier=standard` — every 4 hours

### Swappable provider architecture
Every external dependency is behind an interface with a **stub** (deterministic, no
network, no keys) and a **live** implementation (real API, not yet exercised — no keys
configured):

- `MarketDataProvider` → stub / `TwelveDataProvider`
- `NewsProvider` → stub / `MarketauxProvider`
- `AiBriefProvider` → stub / `AnthropicBriefProvider`
- `PriceForecastProvider` → stub (volatility-band placeholder) / `KronosForecastProvider`
  (calls a not-yet-built Python microservice)

Toggle via `.env`: `PIPELINE_PROVIDER_MODE` and `FORECAST_PROVIDER_MODE` (`stub` | `live`/`kronos`).

### Real technical indicators
`TechnicalIndicatorsService` wraps the compiled `ta_lib` PHP extension (built from source —
not on PECL, had to `phpize`/`configure`/`make`). Computes real RSI(14) and MACD(12,26,9)
from actual OHLC, feeds into both the stub and live brief providers. RSI extremes (≥70 / ≤30)
cap the momentum-derived bias rather than being ignored.

### Real correlation
`CorrelationService` — Pearson correlation of daily returns between instruments, computed
from actual seeded OHLC (only 3 instruments, so it's a small matrix, but real, not mocked).

### Frontend — real Laravel Blade page, not the earlier Artifact mockup
`resources/views/pages/instrument.blade.php` + `InstrumentController` render
**`/instrument/{symbol}`** from live DB data: price, candlestick chart (real 90-day OHLC),
bilingual AI brief (AR/EN + RTL toggle), RSI/MACD, Kronos-stub forecast, Sharia badge,
correlation panel, related news.

Verified with Playwright (screenshots, console-error check, RTL toggle, chart interactivity).
Found and fixed 4 real bugs this way: MACD-signal value getting wiped by an i18n bug, ticker
symbol visually reversing in RTL, news timestamp/source reordering in RTL, breadcrumb not
updating on language switch.

**Note:** the earlier claude.ai Artifact prototype (Aramco mockup) is a dead end for real
data — Artifacts run under a CSP that blocks all outbound fetch, so it can never talk to this
local backend. The Blade page above is the real, working version.

### Home, Markets, Heatmap pages + shared layout + Filament CMS

The instrument page's layout was extracted into shared partials
(`layouts/app-head.blade.php`, `layouts/app-topbar.blade.php`, `partials/i18n.blade.php`) so
three new pages could reuse the same head/topbar/i18n-toggle machinery instead of each
reinventing it. `instrument.blade.php` was refactored onto these partials with **zero
behavior change** — re-verified with Playwright before any new page was built on top.

- **Home** (`/`) — hero with CMS-driven title/subtitle, CTAs to Markets and Heatmap.
- **Markets** (`/markets`) — table of all 81 instruments (symbol, name, price, change%, AI
  bias), asset-class tabs that filter rows client-side, row click → instrument page.
- **Heatmap** (`/heatmap`) — all 81 instruments grouped into 6 asset-class panels, tile
  background color driven by `change_percent` sign/magnitude (capped ±3%, intensity scaled),
  tile click → instrument page.
- **CMS**: new `page_contents` table (`page_slug`, `field_key`, `value_en`, `value_ar`) +
  `PageContent` model, seeded with 6 rows (hero copy for Home, title/subtitle for
  Markets and Heatmap). A Filament admin panel at `/admin` (`PageContentResource`) lets
  these EN/AR strings be edited without a deploy — `/admin` correctly redirects
  (302) to the login page when unauthenticated.
- **`Instrument::latestQuote()`** relation added so Markets/Heatmap can pull each
  instrument's most recent quote without a manual query per row.

One review cycle on the Markets page caught a real bug: the `.change` up/down color rule
was missing from the shared CSS, so change% cells rendered without red/green. Fixed by
moving the `.change` rules into the shared layout (commit `4821180`) — confirmed fixed in
this task's final pass (red/green colors verified pixel-level on both Markets and Heatmap).

**Task 9 final integration pass** (this task) — actually run against the live local stack
(PostgreSQL, Redis, `php artisan serve --port=8123`, all 81 instruments seeded/quoted/briefed),
not just re-reading prior task reports:

- **Routes:** `/`, `/markets`, `/heatmap`, `/instrument/2222.SR`, `/instrument/AAPL` all
  return `200`; `/admin` returns `302` (redirect to login, unauthenticated) — no `500`s.
- **Home:** EN hero renders CMS copy ("Markets, decoded."); AR toggle switches to the CMS
  Arabic string ("الأسواق، بوضوح."), `#app` flips to `dir="rtl" lang="ar"`; "Browse Markets"
  → `/markets`, "View Heatmap" → `/heatmap`; zero console errors on any step.
- **Markets:** 81 rows in the "All" view; each asset-class tab shows exactly the seeded
  count and nothing else (Forex 15, Crypto 12, Metals 4, Stocks 25, Indices 15,
  Commodities 10 — checked both by tag match and by total visible-row count); "All" restores
  81. AR toggle switches instrument names and bias badges to Arabic text, flips RTL, and
  `.num` cells stay `direction: ltr` (checked via computed style, not just visual). Clicking
  a row (BRENT) navigated to `/instrument/BRENT`. Zero console errors.
- **Heatmap:** exactly 6 group panels, with tile counts matching the same per-class seed
  counts as Markets (81 total). Tile background colors sampled programmatically: 38 distinct
  RGBA values across 81 tiles, a real mix of red (`rgba(244,40,33,…)`) and green
  (`rgba(47,190,143,…)`) — not uniformly gray. AR toggle translates group headings (e.g.
  "Forex" → "فوركس") and flips RTL. Clicking a tile (AUDCAD) navigated to
  `/instrument/AUDCAD`. Zero console errors.
- **Nav consistency:** checked the topbar's 3 links (Home/Markets/Heatmap) on all 4 page
  types. Home/Markets/Heatmap each show exactly one `.is-active` link matching the current
  page; the instrument page shows zero `.is-active` links (it isn't one of the 3 nav
  destinations, so nothing should highlight — expected, not a bug). Cross-navigation between
  all page pairs (6 link clicks) landed on the correct URL every time.

No functional bugs found in this pass — the feature (Home/Markets/Heatmap/CMS) is
integration-verified as a whole, not just task-by-task.

### Monitoring tools added
- **Laravel Horizon** (`/horizon`) — real-time queue dashboard, confirmed actually processing
  dispatched jobs in the background (no more manual `queue:work --once`)
- **Laravel Pulse** (`/pulse`) — app performance dashboard (slow queries, exceptions)

## Currently running (local)

```
php artisan serve --port=8123    # http://127.0.0.1:8123
php artisan horizon              # background queue worker + /horizon dashboard
brew services: postgresql@16, redis
```

If these have stopped (e.g. terminal closed), restart from the project directory:
```bash
eval "$(/opt/homebrew/bin/brew shellenv zsh)"
brew services start postgresql@16
brew services start redis
php artisan serve --port=8123 &
php artisan horizon &
```

## External repos evaluated (research only — see full reasoning in conversation history)

| Repo | Verdict |
|---|---|
| `shiyu-coder/Kronos` | ✅ Integrated (interface + stub; real model deferred, needs a Python service) |
| `AI4Finance-Foundation/FinRL` | ❌ Rejected — produces trading decisions, conflicts with "not investment advice" |
| `hummingbot/hummingbot` | ❌ Rejected — real trade execution, conflicts with "Terminal is not a trading platform" |
| `AI4Finance-Foundation/FinRobot` | ❌ Not adopted directly (heavy, stocks-only, needs new data providers) — architectural idea (deterministic finance math + LLM narration) noted for a future `FundamentalAnalysisProvider` |
| `AI4Finance-Foundation/FinGPT` | ❌ Rejected — redundant with existing Anthropic usage, disproportionate GPU/training infra |
| `OpenBB-finance/OpenBB` | ⏸️ Parked — legitimate, but AGPLv3 needs care if ever modified, and it doesn't solve our actual free-tier API limits. MCP server now connected (see below) but tools not yet loaded in-session |
| `QuantConnect/Lean` | ❌ Rejected for the product (live broker execution) — backtesting-only piece could be an isolated internal research tool later |
| `chrisworsey55/atlas-gic` | ❌ Rejected — credibility red flags (stars/forks vastly disproportionate to commit history, real logic paywalled, real-capital marketing claims) |
| `TauricResearch/TradingAgents` | ❌ Rejected — output is a trading signal, same philosophy conflict as FinRL. Bull/bear "debate" pattern noted as a future improvement to `AnthropicBriefProvider`'s prompt chain |

## OpenBB MCP server

Added to this project's local Claude Code config:
```
claude mcp add --transport http openbb https://backend.openbb.co/mcp \
  --header "Authorization: Bearer <token>"
```
Status: **✔ Connected** (verified via `claude mcp get openbb`), but its tools weren't loaded
in the session that added it — needs a fresh `claude` session started from this project
directory to pick them up.

## Honest limitations / not yet done

- No real provider API keys configured anywhere (Anthropic, TwelveData, Marketaux) — everything
  running on deterministic stubs
- News is English-only even in Arabic mode (stub has no AR headlines)
- Kronos and OpenBB are not actually wired to real services — interfaces/config only
- No ToS, no anti-scraping middleware, no rate limiting — same gaps as the original documented handover
- Home/Markets/Heatmap pages now exist alongside the instrument detail page, but there is
  still no Setups page
- The `/admin` (Filament) admin account (`admin@fiperterminal.test`) is a one-off created
  via `make:filament-user` during Task 8 with a password only recorded in that session's
  terminal — no password reset flow, no additional users, no roles/permissions

## Suggested next steps

1. Start a fresh session in this project directory to pick up the OpenBB MCP tools
2. Decide: real API keys now (Anthropic/TwelveData/Marketaux) vs. keep building more pages on stubs
3. Build a Setups page reusing the same Blade/i18n/CMS pattern established by Home/Markets/Heatmap
4. Give the Filament admin panel a real user-management story (reset flow, more than one account)
