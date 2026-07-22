# Live Data Layer (Reverb/Echo) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Markets, Heatmap, and Instrument pages update live (price/change%, news, AI briefs) as the existing scheduled pipeline commands produce new data, with no page reload.

**Architecture:** Laravel Reverb (self-hosted WebSocket server) + Laravel Echo. Three public broadcast channels — `quotes`, `news`, `briefs`. Each existing pipeline command/job fires a `ShouldBroadcast` event immediately after its normal DB write; the write itself is never gated on the broadcast succeeding. Each page's existing inline `<script>` block gets a small Echo listener that patches the DOM in place, filtered by symbol where relevant.

**Tech Stack:** Laravel 13 / PHP 8.5, Laravel Reverb, Laravel Echo + Pusher-JS client (Reverb speaks the Pusher protocol), Vite (already configured, currently unused by the real pages), PostgreSQL 16, Redis 7 (existing queue/cache, unchanged).

## Global Constraints

- Reference spec: `docs/superpowers/specs/2026-07-22-live-data-layer-design.md`.
- Three public channels only — `quotes`, `news`, `briefs`. No private/authenticated channels, no per-symbol channels.
- Broadcasting is purely additive: a broadcast is dispatched only *after* the underlying DB write succeeds, and a failed/retried broadcast job must never block or re-run the pipeline command itself.
- No live-redraw of the Instrument page's candlestick chart — stays reload-based.
- No connection-status UI indicator.
- Tests are PHPUnit class-based (`Tests\TestCase`, `RefreshDatabase` trait) — this project does not use Pest. Match `tests/Feature/ExampleTest.php`'s style.
- `PIPELINE_PROVIDER_MODE` defaults to `stub` (see `config/pipeline.php`, bound in `app/Providers/PipelineServiceProvider.php`) — tests run against deterministic stub providers, no network calls, no API keys needed.
- `BROADCAST_CONNECTION=null` and `QUEUE_CONNECTION=sync` are already set for the test environment in `phpunit.xml` — broadcasting during tests is a safe no-op; no test-config changes are needed.
- Existing pages (`resources/views/pages/{home,markets,heatmap,instrument}.blade.php`) are deliberately framework-free: one inline `<script>` IIFE per page, no build step. This plan adds Vite/Echo as a new, shared, minimal exception (Task 5) — do not otherwise introduce a JS framework or bundle page-specific logic into shared files.
- Follow existing model relation names exactly: `Instrument::quoteSnapshots()`, `Instrument::latestQuote()`, `Instrument::newsArticles()`, `Instrument::biasMeta(?string $bias): array`.

---

### Task 1: Install and configure Laravel Reverb

**Files:**
- Modify: `composer.json`, `composer.lock` (via `composer require`)
- Create: `config/reverb.php`, `config/broadcasting.php`, `routes/channels.php` (via the installer)
- Modify: `bootstrap/app.php`, `.env`, `.env.example`
- Modify: `PROJECT-STATUS.md`

**Interfaces:**
- Produces: a running Reverb WebSocket server reachable at the host/port defined by `REVERB_HOST`/`REVERB_PORT` in `.env`, and `BROADCAST_CONNECTION=reverb`. Later tasks (2, 3, 4) dispatch events against this configured broadcaster; Task 5 connects to it from the browser.

- [ ] **Step 1: Require the package**

Run: `composer require laravel/reverb`
Expected: `composer.json` gains `"laravel/reverb": "^1.x"` under `require`; command exits 0.

- [ ] **Step 2: Run the broadcasting installer**

Run: `php artisan install:broadcasting --reverb --no-interaction`
Expected output includes lines confirming Reverb was installed and `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php` were created/published, and `.env` gained `BROADCAST_CONNECTION=reverb` plus `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`.

- [ ] **Step 3: Verify `bootstrap/app.php` now registers the channels route file**

Run: `grep -n "channels" bootstrap/app.php`
Expected: a line inside `->withRouting(...)` reading `channels: __DIR__.'/../routes/channels.php',` (installer adds this automatically). If it's missing, add it manually to the `withRouting()` call in `bootstrap/app.php`:

```php
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
```

- [ ] **Step 4: Mirror the new env keys into `.env.example`**

Open `.env.example` and add (with placeholder values, matching the style of existing entries like `TWELVEDATA_API_KEY=`):

```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 5: Verify the Reverb server boots**

Run (with a timeout, since this is a long-running server): `timeout 5 php artisan reverb:start || true`
Expected: output includes a line like `Starting server on 0.0.0.0:8080 (localhost)` before the timeout kills it. This confirms config is valid — no missing keys, no port conflict.

- [ ] **Step 6: Document the new local process**

Edit `PROJECT-STATUS.md`, in the "Currently running (local)" section, add a line after `php artisan horizon`:

```
php artisan reverb:start          # WebSocket server for live quotes/news/briefs push
```

And in the restart-commands block below it, add:

```bash
php artisan reverb:start &
```

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock config/reverb.php config/broadcasting.php routes/channels.php bootstrap/app.php .env.example PROJECT-STATUS.md
git commit -m "Install and configure Laravel Reverb for broadcasting"
```

---

### Task 2: `QuoteUpdated` broadcast event

**Files:**
- Create: `app/Events/QuoteUpdated.php`
- Modify: `app/Console/Commands/RefreshQuotes.php`
- Test: `tests/Feature/QuoteUpdatedBroadcastTest.php`

**Interfaces:**
- Consumes: `Instrument::quoteSnapshots()` relation (existing), `MarketDataProvider::fetchQuote(string $symbol): array` (existing).
- Produces: `App\Events\QuoteUpdated` — public readonly properties `symbol: string`, `price: float`, `change: float`, `changePercent: float`, `quotedAt: string`. Broadcasts on public channel `quotes` as `.quote.updated` with payload keys `symbol, price, change, change_percent, quoted_at`. Task 6, 7, 8 listen for this.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/QuoteUpdatedBroadcastTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Events\QuoteUpdated;
use App\Models\Instrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QuoteUpdatedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotes_refresh_dispatches_quote_updated_event(): void
    {
        Instrument::create([
            'symbol' => 'XAUUSD',
            'name' => 'Gold',
            'short_name' => 'Gold',
            'asset_class' => 'metals',
            'is_active' => true,
        ]);

        Event::fake([QuoteUpdated::class]);

        Artisan::call('quotes:refresh');

        Event::assertDispatched(QuoteUpdated::class, function (QuoteUpdated $event) {
            return $event->symbol === 'XAUUSD' && $event->price > 0;
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuoteUpdatedBroadcastTest`
Expected: FAIL — `Class "App\Events\QuoteUpdated" not found`.

- [ ] **Step 3: Create the event class**

Create `app/Events/QuoteUpdated.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class QuoteUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $symbol,
        public readonly float $price,
        public readonly float $change,
        public readonly float $changePercent,
        public readonly string $quotedAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('quotes');
    }

    public function broadcastAs(): string
    {
        return 'quote.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'symbol' => $this->symbol,
            'price' => $this->price,
            'change' => $this->change,
            'change_percent' => $this->changePercent,
            'quoted_at' => $this->quotedAt,
        ];
    }
}
```

- [ ] **Step 4: Dispatch it from `RefreshQuotes`**

Modify `app/Console/Commands/RefreshQuotes.php`:

```php
<?php

namespace App\Console\Commands;

use App\Contracts\MarketDataProvider;
use App\Events\QuoteUpdated;
use App\Models\Instrument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quotes:refresh')]
#[Description('Fetch a live quote snapshot for every active instrument')]
class RefreshQuotes extends Command
{
    public function handle(MarketDataProvider $provider): int
    {
        $instruments = Instrument::where('is_active', true)->get();

        foreach ($instruments as $instrument) {
            $quote = $provider->fetchQuote($instrument->symbol);

            $snapshot = $instrument->quoteSnapshots()->create([
                'quoted_at' => $quote['quoted_at'],
                'price' => $quote['price'],
                'change' => $quote['change'],
                'change_percent' => $quote['change_percent'],
                'volume' => $quote['volume'],
            ]);

            QuoteUpdated::dispatch(
                $instrument->symbol,
                (float) $snapshot->price,
                (float) $snapshot->change,
                (float) $snapshot->change_percent,
                $snapshot->quoted_at->toIso8601String(),
            );
        }

        $this->line("Refreshed quotes for {$instruments->count()} instrument(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=QuoteUpdatedBroadcastTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Events/QuoteUpdated.php app/Console/Commands/RefreshQuotes.php tests/Feature/QuoteUpdatedBroadcastTest.php
git commit -m "Broadcast QuoteUpdated event from quotes:refresh"
```

---

### Task 3: `NewsArticleIngested` broadcast event

**Files:**
- Create: `app/Events/NewsArticleIngested.php`
- Modify: `app/Console/Commands/IngestNews.php`
- Test: `tests/Feature/NewsArticleIngestedBroadcastTest.php`

**Interfaces:**
- Consumes: `NewsArticle::updateOrCreate()` (existing), `NewsProvider::fetchNewsForSymbols(array $symbols, int $limit): array` (existing, each item already has a `related_symbols` array).
- Produces: `App\Events\NewsArticleIngested` — public readonly properties `articleId: int`, `headline: string`, `source: string`, `publishedAt: string`, `relatedSymbols: array`. Broadcasts on public channel `news` as `.news.article-ingested` with payload keys `id, headline, source, published_at, instrument_symbols`. Task 8 listens for this.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NewsArticleIngestedBroadcastTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Events\NewsArticleIngested;
use App\Models\Instrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NewsArticleIngestedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_ingest_dispatches_event_for_new_articles(): void
    {
        Instrument::create([
            'symbol' => 'XAUUSD',
            'name' => 'Gold',
            'short_name' => 'Gold',
            'asset_class' => 'metals',
            'is_active' => true,
        ]);

        Event::fake([NewsArticleIngested::class]);

        Artisan::call('news:ingest');

        Event::assertDispatched(NewsArticleIngested::class, function (NewsArticleIngested $event) {
            return in_array('XAUUSD', $event->relatedSymbols, true);
        });
    }

    public function test_news_ingest_does_not_redispatch_for_unchanged_articles(): void
    {
        Instrument::create([
            'symbol' => 'XAUUSD',
            'name' => 'Gold',
            'short_name' => 'Gold',
            'asset_class' => 'metals',
            'is_active' => true,
        ]);

        Artisan::call('news:ingest');

        Event::fake([NewsArticleIngested::class]);

        Artisan::call('news:ingest');

        Event::assertNotDispatched(NewsArticleIngested::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NewsArticleIngestedBroadcastTest`
Expected: FAIL — `Class "App\Events\NewsArticleIngested" not found`.

- [ ] **Step 3: Create the event class**

Create `app/Events/NewsArticleIngested.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class NewsArticleIngested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @param array<int, string> $relatedSymbols */
    public function __construct(
        public readonly int $articleId,
        public readonly string $headline,
        public readonly string $source,
        public readonly string $publishedAt,
        public readonly array $relatedSymbols,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('news');
    }

    public function broadcastAs(): string
    {
        return 'news.article-ingested';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->articleId,
            'headline' => $this->headline,
            'source' => $this->source,
            'published_at' => $this->publishedAt,
            'instrument_symbols' => $this->relatedSymbols,
        ];
    }
}
```

- [ ] **Step 4: Dispatch it from `IngestNews`, only for genuinely new articles**

Modify `app/Console/Commands/IngestNews.php`:

```php
<?php

namespace App\Console\Commands;

use App\Contracts\NewsProvider;
use App\Events\NewsArticleIngested;
use App\Models\Instrument;
use App\Models\NewsArticle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('news:ingest')]
#[Description('Fetch and de-duplicate news for all active instruments')]
class IngestNews extends Command
{
    public function handle(NewsProvider $provider): int
    {
        $instruments = Instrument::where('is_active', true)->get();

        if ($instruments->isEmpty()) {
            return self::SUCCESS;
        }

        $items = $provider->fetchNewsForSymbols($instruments->pluck('symbol')->all(), limit: 50);
        $created = 0;

        foreach ($items as $item) {
            $article = NewsArticle::updateOrCreate(
                ['marketaux_uuid' => $item['uuid']],
                [
                    'title' => $item['title'],
                    'summary' => $item['summary'],
                    'source' => $item['source'],
                    'url' => $item['url'],
                    'published_at' => $item['published_at'],
                ],
            );

            if ($article->wasRecentlyCreated) {
                NewsArticleIngested::dispatch(
                    $article->id,
                    $article->title,
                    $article->source,
                    $article->published_at->toIso8601String(),
                    $item['related_symbols'],
                );
            }

            // NOTE: Eloquent\Collection::only()/except() match by primary key,
            // not array key — even after keyBy(). whereIn() on the attribute
            // is the safe way to filter by symbol here.
            $matchedIds = $instruments->whereIn('symbol', $item['related_symbols'])->pluck('id');
            $article->instruments()->syncWithoutDetaching($matchedIds);

            $created++;
        }

        $this->line("Ingested {$created} news item(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=NewsArticleIngestedBroadcastTest`
Expected: PASS (both test methods).

- [ ] **Step 6: Commit**

```bash
git add app/Events/NewsArticleIngested.php app/Console/Commands/IngestNews.php tests/Feature/NewsArticleIngestedBroadcastTest.php
git commit -m "Broadcast NewsArticleIngested event from news:ingest"
```

---

### Task 4: `BriefGenerated` broadcast event

**Files:**
- Create: `app/Events/BriefGenerated.php`
- Modify: `app/Jobs/GenerateInstrumentBrief.php`
- Test: `tests/Feature/BriefGeneratedBroadcastTest.php`

**Interfaces:**
- Consumes: `Instrument::biasMeta(?string $bias): array` (existing, returns `['en' => ..., 'ar' => ..., 'class' => ...]`), the brief array shape `{title, summary, key_levels, catalysts, risks}` returned by `AiBriefProvider::generateBrief()` (existing, unchanged).
- Produces: `App\Events\BriefGenerated` — public readonly properties `symbol: string`, `tier: string`, `briefEn: array`, `briefAr: array`, `bias: ?string`, `generatedAt: string`. Broadcasts on public channel `briefs` as `.brief.generated` with payload keys `symbol, tier, brief_en, brief_ar, bias, bias_label_en, bias_label_ar, bias_class, generated_at`. Task 8 listens for this.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BriefGeneratedBroadcastTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Events\BriefGenerated;
use App\Jobs\GenerateInstrumentBrief;
use App\Models\Instrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BriefGeneratedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_instrument_brief_dispatches_event(): void
    {
        $instrument = Instrument::create([
            'symbol' => 'XAUUSD',
            'name' => 'Gold',
            'short_name' => 'Gold',
            'asset_class' => 'metals',
            'is_active' => true,
            'is_tier_one' => true,
        ]);

        Artisan::call('ohlc:backfill', ['--symbol' => 'XAUUSD', '--days' => 90]);
        Artisan::call('quotes:refresh');

        Event::fake([BriefGenerated::class]);

        GenerateInstrumentBrief::dispatchSync($instrument->id);

        Event::assertDispatched(BriefGenerated::class, function (BriefGenerated $event) {
            return $event->symbol === 'XAUUSD'
                && $event->tier === 'tier_one'
                && $event->briefEn !== [];
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BriefGeneratedBroadcastTest`
Expected: FAIL — `Class "App\Events\BriefGenerated" not found`.

- [ ] **Step 3: Create the event class**

Create `app/Events/BriefGenerated.php`:

```php
<?php

namespace App\Events;

use App\Models\Instrument;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class BriefGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param array<string, mixed> $briefEn
     * @param array<string, mixed> $briefAr
     */
    public function __construct(
        public readonly string $symbol,
        public readonly string $tier,
        public readonly array $briefEn,
        public readonly array $briefAr,
        public readonly ?string $bias,
        public readonly string $generatedAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('briefs');
    }

    public function broadcastAs(): string
    {
        return 'brief.generated';
    }

    public function broadcastWith(): array
    {
        $biasMeta = Instrument::biasMeta($this->bias);

        return [
            'symbol' => $this->symbol,
            'tier' => $this->tier,
            'brief_en' => $this->briefEn,
            'brief_ar' => $this->briefAr,
            'bias' => $this->bias,
            'bias_label_en' => $biasMeta['en'],
            'bias_label_ar' => $biasMeta['ar'],
            'bias_class' => $biasMeta['class'],
            'generated_at' => $this->generatedAt,
        ];
    }
}
```

- [ ] **Step 4: Dispatch it from `GenerateInstrumentBrief`**

Modify `app/Jobs/GenerateInstrumentBrief.php` — add the import and dispatch call right after the existing `$instrument->update([...])`:

```php
<?php

namespace App\Jobs;

use App\Contracts\AiBriefProvider;
use App\Contracts\PriceForecastProvider;
use App\Events\BriefGenerated;
use App\Models\Instrument;
use App\Services\TechnicalIndicatorsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateInstrumentBrief implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $instrumentId) {}

    public function handle(
        AiBriefProvider $briefProvider,
        PriceForecastProvider $forecastProvider,
        TechnicalIndicatorsService $indicatorsService,
    ): void {
        $instrument = Instrument::find($this->instrumentId);

        if (! $instrument || ! $instrument->is_active) {
            return;
        }

        $ohlc = $instrument->ohlcDaily()
            ->orderByDesc('date')
            ->limit(60)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => [
                'date' => $row->date->toDateString(),
                'open' => (float) $row->open,
                'high' => (float) $row->high,
                'low' => (float) $row->low,
                'close' => (float) $row->close,
            ])
            ->all();

        $context = [
            'ohlc' => $ohlc,

            'quote' => $instrument->quoteSnapshots()
                ->latest('quoted_at')
                ->first()
                ?->only(['price', 'change', 'change_percent']),

            'news' => $instrument->newsArticles()
                ->latest('published_at')
                ->limit(5)
                ->get(['title', 'published_at'])
                ->toArray(),

            'forecast' => empty($ohlc) ? [] : $forecastProvider->forecast($instrument->symbol, $ohlc, horizonDays: 1),

            'indicators' => $indicatorsService->compute($ohlc),
        ];

        if (empty($context['ohlc']) || ! $context['quote']) {
            Log::warning('Skipping brief generation: no OHLC/quote data yet', ['symbol' => $instrument->symbol]);

            return;
        }

        $modelTier = $instrument->is_tier_one ? 'tier_one' : 'standard';
        $brief = $briefProvider->generateBrief($instrument, $context, $modelTier);

        $instrument->update([
            'ai_brief_en' => $brief['en'],
            'ai_brief_ar' => $brief['ar'],
            'ai_bias' => $brief['bias'],
            'analytics_refreshed_at' => now(),
        ]);

        BriefGenerated::dispatch(
            $instrument->symbol,
            $modelTier,
            $brief['en'],
            $brief['ar'],
            $brief['bias'],
            now()->toIso8601String(),
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateInstrumentBrief failed', [
            'instrument_id' => $this->instrumentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=BriefGeneratedBroadcastTest`
Expected: PASS.

- [ ] **Step 6: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: all tests pass, including the pre-existing `ExampleTest` suites and Tasks 2/3's tests.

- [ ] **Step 7: Commit**

```bash
git add app/Events/BriefGenerated.php app/Jobs/GenerateInstrumentBrief.php tests/Feature/BriefGeneratedBroadcastTest.php
git commit -m "Broadcast BriefGenerated event from GenerateInstrumentBrief job"
```

---

### Task 5: Frontend Echo bootstrap

**Files:**
- Modify: `package.json`
- Create: `resources/js/echo.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/layouts/app-head.blade.php`

**Interfaces:**
- Produces: a global `window.Echo` client, connected to the Reverb server configured in Task 1, available on every page that includes `layouts.app-head` (Home, Markets, Heatmap, Instrument). Tasks 6, 7, 8 call `window.Echo.channel(...)` from each page's own inline script.

- [ ] **Step 1: Add the JS dependencies**

Modify `package.json` to add a `dependencies` block (alongside the existing `devDependencies`):

```json
{
    "$schema": "https://www.schemastore.org/package.json",
    "private": true,
    "type": "module",
    "scripts": {
        "build": "vite build",
        "dev": "vite"
    },
    "dependencies": {
        "laravel-echo": "^1.16",
        "pusher-js": "^8.4"
    },
    "devDependencies": {
        "@tailwindcss/vite": "^4.0.0",
        "concurrently": "^9.0.1",
        "laravel-vite-plugin": "^3.1",
        "tailwindcss": "^4.0.0",
        "vite": "^8.0.0"
    }
}
```

Run: `npm install`
Expected: exits 0, `node_modules/laravel-echo` and `node_modules/pusher-js` exist, `package-lock.json` updated.

- [ ] **Step 2: Create the Echo bootstrap module**

Create `resources/js/echo.js`:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

- [ ] **Step 3: Import it from the app entry point**

Replace the contents of `resources/js/app.js` (currently empty) with:

```js
import './echo';
```

- [ ] **Step 4: Load the compiled bundle on every real page**

Modify `resources/views/layouts/app-head.blade.php` — add this line right after the closing `</style>` tag at the end of the file:

```blade
@vite(['resources/js/app.js'])
```

- [ ] **Step 5: Build and verify**

Run: `npm run build`
Expected: exits 0. Then run `find public/build -iname "manifest.json"` to locate the manifest (path varies by Vite version — commonly `public/build/manifest.json` or `public/build/.vite/manifest.json`) and confirm it contains an entry for `resources/js/app.js`.

Run: `php artisan serve --port=8123 &` (if not already running), then `curl -s http://127.0.0.1:8123/markets | grep -o '<script[^>]*app[^>]*></script>'`
Expected: a `<script type="module" ...>` tag referencing a hashed `assets/app-*.js` file under `/build/`, confirming `@vite()` resolved against the manifest.

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json resources/js/echo.js resources/js/app.js resources/views/layouts/app-head.blade.php
git commit -m "Add Laravel Echo bootstrap, loaded on every page via Vite"
```

---

### Task 6: Markets page — live quote updates

**Files:**
- Modify: `resources/views/pages/markets.blade.php`

**Interfaces:**
- Consumes: `window.Echo` (Task 5), channel `quotes` event `.quote.updated` payload `{symbol, price, change, change_percent, quoted_at}` (Task 2).

- [ ] **Step 1: Add symbol/targeting hooks to the table row markup**

In `resources/views/pages/markets.blade.php`, replace the `<tbody>` row markup:

```blade
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
```

with:

```blade
          <tr data-asset-class="{{ $instrument->asset_class }}" data-symbol="{{ $instrument->symbol }}" onclick="window.location.href='{{ route('instrument.show', $instrument->symbol) }}'">
            <td><span class="mkt-symbol">{{ $instrument->symbol }}</span></td>
            <td><span data-name-en="{{ $instrument->name }}" data-name-ar="{{ $instrument->name_localized ?? $instrument->name }}">{{ $instrument->name }}</span></td>
            <td><span class="num mkt-price">{{ $q ? number_format($q->price, 2) : '—' }}</span></td>
            <td class="mkt-change-cell {{ $up ? 'change up' : 'change down' }}">
              <span class="num mkt-change-value">
                @if($q)
                  {{ $up ? '+' : '' }}{{ number_format($q->change_percent, 2) }}%
                @else
                  —
                @endif
              </span>
            </td>
            <td><span class="badge {{ $bias['class'] }}" data-bias-en="{{ $bias['en'] }}" data-bias-ar="{{ $bias['ar'] }}">{{ $bias['en'] }}</span></td>
          </tr>
```

- [ ] **Step 2: Add the Echo listener**

In the same file's `<script>` block, add this right before the final `applyI18n();` line:

```js
  if (window.Echo) {
    window.Echo.channel("quotes").listen(".quote.updated", function (e) {
      var row = document.querySelector('tr[data-symbol="' + e.symbol + '"]');
      if (!row) return;
      var priceEl = row.querySelector(".mkt-price");
      var changeCell = row.querySelector(".mkt-change-cell");
      var changeVal = row.querySelector(".mkt-change-value");
      var up = e.change_percent >= 0;
      priceEl.textContent = Number(e.price).toFixed(2);
      changeCell.classList.toggle("up", up);
      changeCell.classList.toggle("down", !up);
      changeVal.textContent = (up ? "+" : "") + Number(e.change_percent).toFixed(2) + "%";
    });
  }
```

- [ ] **Step 3: Manual verification**

With `php artisan serve`, `php artisan reverb:start`, and `php artisan queue:work` (or Horizon) all running, open `http://127.0.0.1:8123/markets` in a browser, then in another terminal run `php artisan quotes:refresh`. Confirm price/change% cells update in place within a couple of seconds, with no console errors and no page reload.

- [ ] **Step 4: Commit**

```bash
git add resources/views/pages/markets.blade.php
git commit -m "Live-update Markets table rows on quote broadcasts"
```

---

### Task 7: Heatmap page — live quote updates

**Files:**
- Modify: `resources/views/pages/heatmap.blade.php`

**Interfaces:**
- Consumes: `window.Echo` (Task 5), channel `quotes` event `.quote.updated` payload `{symbol, price, change, change_percent, quoted_at}` (Task 2).

- [ ] **Step 1: Add a symbol targeting hook to each tile**

In `resources/views/pages/heatmap.blade.php`, replace:

```blade
          <a href="{{ route('instrument.show', $instrument->symbol) }}" class="heatmap-tile" style="background:{{ $tileColor }};">
            <div class="tile-symbol">{{ $instrument->symbol }}</div>
            <div class="tile-change num">{{ $q ? ($pct >= 0 ? '+' : '').number_format($pct, 2).'%' : '—' }}</div>
          </a>
```

with:

```blade
          <a href="{{ route('instrument.show', $instrument->symbol) }}" class="heatmap-tile" data-symbol="{{ $instrument->symbol }}" style="background:{{ $tileColor }};">
            <div class="tile-symbol">{{ $instrument->symbol }}</div>
            <div class="tile-change num">{{ $q ? ($pct >= 0 ? '+' : '').number_format($pct, 2).'%' : '—' }}</div>
          </a>
```

- [ ] **Step 2: Add the Echo listener**

In the same file's `<script>` block, add this right before the final `applyI18n();` line:

```js
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
```

- [ ] **Step 3: Manual verification**

With the same local stack running as Task 6, open `http://127.0.0.1:8123/heatmap`, run `php artisan quotes:refresh` in another terminal, and confirm tile colors and percentages update in place, no console errors, no reload.

- [ ] **Step 4: Commit**

```bash
git add resources/views/pages/heatmap.blade.php
git commit -m "Live-update Heatmap tiles on quote broadcasts"
```

---

### Task 8: Instrument page — live quote, news, and brief updates

**Files:**
- Modify: `resources/views/pages/instrument.blade.php`

**Interfaces:**
- Consumes: `window.Echo` (Task 5); channel `quotes` event `.quote.updated` (Task 2); channel `news` event `.news.article-ingested` payload `{id, headline, source, published_at, instrument_symbols}` (Task 3); channel `briefs` event `.brief.generated` payload `{symbol, tier, brief_en, brief_ar, bias, bias_label_en, bias_label_ar, bias_class, generated_at}` (Task 4). All three listeners filter to the page's own `$instrument->symbol`.

- [ ] **Step 1: Add targeting ids to the price block**

Replace:

```blade
    <div class="price-block">
      @if($quote)
        <div class="price num">{{ number_format($quote->price, 2) }}</div>
        <div class="change {{ $changeUp ? 'up' : 'down' }} num">{{ $changeUp ? '+' : '' }}{{ number_format($quote->change, 2) }} ({{ $changeUp ? '+' : '' }}{{ number_format($quote->change_percent, 2) }}%)</div>
        <div class="updated"><span data-i18n="updated_label">Updated</span> {{ $quote->quoted_at->diffForHumans() }}</div>
      @else
        <p class="empty-note">No quote yet — run quotes:refresh.</p>
      @endif
    </div>
```

with:

```blade
    <div class="price-block">
      @if($quote)
        <div class="price num" id="instrumentPrice">{{ number_format($quote->price, 2) }}</div>
        <div class="change {{ $changeUp ? 'up' : 'down' }} num" id="instrumentChange">{{ $changeUp ? '+' : '' }}{{ number_format($quote->change, 2) }} ({{ $changeUp ? '+' : '' }}{{ number_format($quote->change_percent, 2) }}%)</div>
        <div class="updated"><span data-i18n="updated_label">Updated</span> <span id="instrumentUpdatedTime">{{ $quote->quoted_at->diffForHumans() }}</span></div>
      @else
        <p class="empty-note">No quote yet — run quotes:refresh.</p>
      @endif
    </div>
```

- [ ] **Step 2: Make the news section always present so new articles can be prepended**

Replace:

```blade
  @if($news->isNotEmpty())
    <section class="news-section">
      <div class="panel-title" style="margin-bottom:12px;"><h2 data-i18n="news_title">Related News</h2></div>
      <div class="news-grid">
        @foreach($news as $article)
          <div class="news-card">
            <div class="news-meta"><span>{{ $article->source }}</span><span>{{ $article->published_at->diffForHumans() }}</span></div>
            <p class="news-headline">{{ $article->title }}</p>
          </div>
        @endforeach
      </div>
    </section>
  @endif
```

with:

```blade
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
```

- [ ] **Step 3: Add the Echo listeners**

In the same file's `<script>` block, add this right before the final `applyI18n();` line:

```js
  var mySymbol = @json($instrument->symbol);

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
```

**Note (accepted scope limit):** if `ai_brief_en` was `null` at page load, the brief panel doesn't render at all (see the `@if($instrument->ai_brief_en)` block above this script), so the `briefEn`/`briefAr`/`biasEnLabel`/`biasArLabel` variables it depends on don't exist and this listener has nothing to update into — a reload is needed the first time a brief appears for an instrument that had none. This matches the spec's explicitly accepted trade-offs.

- [ ] **Step 4: Manual verification**

With the same local stack running as Tasks 6/7, open `http://127.0.0.1:8123/instrument/XAUUSD` (or any seeded symbol) in a browser. In another terminal:
- Run `php artisan quotes:refresh` — confirm the price/change header updates live.
- Run `php artisan news:ingest` — confirm a new news card appears at the top of the Related News grid (for a symbol the stub provider covers, e.g. `XAUUSD`).
- Run `php artisan analytics:refresh-briefs --symbol=XAUUSD` — confirm the AI Brief panel's title/summary/catalysts/risks/bias badge update in place (only if a brief already existed at page load).

Confirm no console errors in all three cases.

- [ ] **Step 5: Commit**

```bash
git add resources/views/pages/instrument.blade.php
git commit -m "Live-update Instrument page quote, news, and brief on broadcasts"
```

---

### Task 9: Final integration pass

**Files:** none (verification only)

**Interfaces:** none — this task only verifies the previous 8 tasks work together.

- [ ] **Step 1: Run the full automated test suite**

Run: `php artisan test`
Expected: all tests pass (pre-existing tests + the three new broadcast tests from Tasks 2–4).

- [ ] **Step 2: Start the full local stack**

```bash
brew services start postgresql@16
brew services start redis
php artisan serve --port=8123 &
php artisan horizon &
php artisan reverb:start &
```

- [ ] **Step 3: End-to-end manual pass across all three pages**

Open three browser tabs: `/markets`, `/heatmap`, and `/instrument/XAUUSD`. In a fourth terminal, run in sequence:

```bash
php artisan quotes:refresh
php artisan news:ingest
php artisan analytics:refresh-briefs --symbol=XAUUSD
```

Confirm: Markets row for XAUUSD updates live; Heatmap tile for XAUUSD updates live; Instrument page's price header, news list, and (if a brief already existed) AI Brief panel all update live. Check each tab's browser console for errors — expect none.

- [ ] **Step 4: Verify the pipeline still works with Reverb stopped (broadcasting is additive, not required)**

Stop the `reverb:start` process. Run `php artisan quotes:refresh` again and confirm it still completes successfully and still updates `quote_snapshots` in the database (e.g. via `php artisan tinker --execute="echo App\Models\Instrument::where('symbol','XAUUSD')->first()->latestQuote->price;"`) — the command must not fail or hang just because Reverb is down.

- [ ] **Step 5: Update `PROJECT-STATUS.md`**

Add a short entry under "What's built and verified" summarizing that Markets/Heatmap/Instrument pages now push live quote/news/brief updates via Reverb, referencing this plan and the spec.
