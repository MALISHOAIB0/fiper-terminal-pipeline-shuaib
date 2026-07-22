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
