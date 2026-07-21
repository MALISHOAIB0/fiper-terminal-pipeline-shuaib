<?php

namespace App\Providers;

use App\Contracts\AiBriefProvider;
use App\Contracts\MarketDataProvider;
use App\Contracts\NewsProvider;
use App\Contracts\PriceForecastProvider;
use App\Services\Providers\Live\AnthropicBriefProvider;
use App\Services\Providers\Live\KronosForecastProvider;
use App\Services\Providers\Live\MarketauxProvider;
use App\Services\Providers\Live\TwelveDataProvider;
use App\Services\Providers\Stub\StubAiBriefProvider;
use App\Services\Providers\Stub\StubForecastProvider;
use App\Services\Providers\Stub\StubMarketDataProvider;
use App\Services\Providers\Stub\StubNewsProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Single switch point for stub vs live data providers. This is the fix for
 * the documented dual-pipeline problem: there is exactly one binding per
 * contract, resolved from config('pipeline.provider_mode') — no second,
 * silently-diverging implementation living elsewhere.
 */
class PipelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $isLive = config('pipeline.provider_mode') === 'live';

        $this->app->bind(MarketDataProvider::class, function () use ($isLive) {
            if (! $isLive) {
                return new StubMarketDataProvider;
            }

            return new TwelveDataProvider(config('pipeline.twelvedata.api_key'));
        });

        $this->app->bind(NewsProvider::class, function () use ($isLive) {
            if (! $isLive) {
                return new StubNewsProvider;
            }

            return new MarketauxProvider(config('pipeline.marketaux.api_key'));
        });

        $this->app->bind(AiBriefProvider::class, function () use ($isLive) {
            if (! $isLive) {
                return new StubAiBriefProvider;
            }

            return new AnthropicBriefProvider(
                config('pipeline.anthropic.api_key'),
                config('pipeline.anthropic.model_tier_one'),
                config('pipeline.anthropic.model_standard'),
            );
        });

        $this->app->bind(PriceForecastProvider::class, function () {
            if (config('pipeline.forecast_mode') !== 'kronos') {
                return new StubForecastProvider;
            }

            return new KronosForecastProvider(config('pipeline.kronos.service_url'));
        });
    }

    public function boot(): void {}
}
