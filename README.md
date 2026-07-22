# Fiper Terminal — Pipeline Rebuild

A Laravel-based financial market terminal with live instrument data, Markets/Heatmap views, and a Filament CMS.

Built from scratch based on documented architecture from the original Fiper Terminal handover (no legacy source code was available), covering 81 instruments across forex, crypto, metals, stocks, indices, and commodities — with bilingual (EN/AR, RTL-aware) pages throughout.

## Stack

- **Laravel 13** / **PHP 8.5**
- **PostgreSQL 16**, **Redis 7** (queue + cache)
- **Filament 5** (admin CMS)
- **Laravel Horizon** (queue dashboard) / **Laravel Pulse** (performance dashboard)
- `ta_lib` (compiled from source) for real technical indicators

## Pages

- `/` — Home (CMS-driven hero + CTAs)
- `/markets` — all 81 instruments, tabbed by asset class
- `/heatmap` — all 81 instruments as color-graded tiles, grouped by asset class
- `/instrument/{symbol}` — instrument detail: price, candlestick chart, bilingual AI brief, RSI/MACD, correlation panel, related news
- `/admin` — Filament CMS for editing page copy (EN/AR)

## Architecture highlights

- **Swappable providers**: market data, news, AI briefs, and price forecasts are each behind an interface with a deterministic **stub** implementation and a **live** implementation (real APIs, not yet keyed), toggled via `.env` (`PIPELINE_PROVIDER_MODE`, `FORECAST_PROVIDER_MODE`).
- **Unified AI brief pipeline**: single `analytics:refresh-briefs {--tier=}` command, queued via Redis, one job per instrument.
- **Real indicators & correlation**: RSI(14)/MACD(12,26,9) via `ta_lib`, Pearson correlation of daily returns across instruments — no mocked math.

See `PROJECT-STATUS.md` for full build notes, verification details, and known limitations.

## Local development

```bash
composer install
php artisan migrate --seed
php artisan serve --port=8123

# in separate terminals
php artisan horizon
brew services start postgresql@16
brew services start redis
```

## Status

No real provider API keys are configured yet (Anthropic, TwelveData, Marketaux) — the app currently runs on deterministic stubs. See `PROJECT-STATUS.md` → "Honest limitations / not yet done" for the full list.
