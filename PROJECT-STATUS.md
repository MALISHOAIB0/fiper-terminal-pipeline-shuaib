# Fiper Terminal — Pipeline Rebuild — Project Status

**Last updated:** 2026-07-21 (instrument seeding expanded to full 81)
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
- Only the instrument detail page exists — no home/markets/heatmap/setups pages yet

## Suggested next steps

1. Start a fresh session in this project directory to pick up the OpenBB MCP tools
2. Decide: real API keys now (Anthropic/TwelveData/Marketaux) vs. keep building more pages on stubs
3. Build remaining pages (home, markets, heatmap) reusing the same Blade/i18n pattern
